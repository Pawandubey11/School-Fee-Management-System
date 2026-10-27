<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\payment;
use Faker\Provider\ar_EG\Payment as Ar_EGPayment;
use Razorpay\Api\Api;
use Illuminate\Support\Facades\Validator;
class FormController extends Controller
{
    function order(REQUEST $request){
       /* In Ajax its not validate
       $request->validate([
            'student_name'=>'required|string|max:150',
            'email'=>'required|email|max:150',
            'mobile'=>'required|string|max:10',
            'grade'=>'required|integer|between:1,12',  //take 1,2,3 etc not take 1.2,1.6 etc
            'fees'=>'required|numeric|min:2000',  //take both whole and decimal number
            'month'=>'required|integer',
            'address'=>'required|string|max:255'
         ]);*/
        // Clean mobile number: strip spaces, dashes, leading +91, 91, or leading 0
        $cleanedMobile = preg_replace('/[\s\-]/', '', $request->mobile);
        $cleanedMobile = preg_replace('/^(\+91|91|0)(?=\d{10}$)/', '', $cleanedMobile);
        
        $request->merge([
            'mobile' => $cleanedMobile,
        ]);

        $validator = Validator::make($request->all(), [
            'student_name' => 'required|string|regex:/^[a-zA-Z\s]+$/',
            'email' => 'required|email',
            'mobile' => 'required|numeric|digits:10',
            'grade' => 'required|integer|between:1,12',
            'fees' => 'required|numeric|min:1',
            'month' => 'required',
            'address' => 'required|string',
        ]);
        if ($validator->fails()) {
            // Return the validation errors as JSON response
            return response()->json(['status' => 'error', 'errors' => $validator->errors()]);
        }
    
        // Initially store the form record
        $a = new Payment;
        $receipt = 'Fees_' . mt_rand(10000, 99999);
        $a->receipt_no = $receipt;
        $a->student_name = $request->student_name;
        $a->email = $request->email;
        $a->mobile = $cleanedMobile;
        $a->class = $request->grade;
        $a->fees = $request->fees;
        $a->total_fees_paid = $request->fees * $request->month;
        $a->month = $request->month;
        $a->address = $request->address;
        $a->save();
                  
        $amount = $request->fees * 100; // because razorpay takes paise i.e amount*100
        $total_fees_month = $amount * $request->month;
        $key = config('services.razorpay.key', env('RAZORPAY_KEY_ID'));
        $secret = config('services.razorpay.secret', env('RAZORPAY_KEY_SECRET'));

        // If live/test Razorpay keys are configured, create Razorpay order
        if ($key && $secret) {
            try {
                $api = new Api($key, $secret);
                $razororder = $api->order->create([
                    'receipt' => $receipt,
                    'amount' => $total_fees_month,
                    'currency' => 'INR',
                ]);
                $order_id = $razororder->id;
                $currency = $razororder->currency;

                $upd = Payment::find($a->id);
                $upd->order_id = $order_id;
                $upd->currency = $currency;
                $upd->update();

                return response()->json([
                    "status" => "success",
                    "mode" => "live",
                    "order_id" => $order_id,
                    "amount" => $total_fees_month,
                    "student_name" => $request->student_name,
                    "email" => $request->email,
                    "mobile" => $cleanedMobile,
                    "receipt_no" => $receipt
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    "status" => "error",
                    "errors" => ["gateway" => ["Payment gateway error: " . $e->getMessage()]]
                ]);
            }
        } else {
            // Demo mode: complete simulated payment so students can immediately test receipt download
            $order_id = 'order_demo_' . mt_rand(100000, 999999);
            $payment_id = 'pay_demo_' . mt_rand(100000, 999999);
            $upd = Payment::find($a->id);
            $upd->order_id = $order_id;
            $upd->payment_id = $payment_id;
            $upd->payment_status = "Successful";
            $upd->currency = 'INR';
            $upd->update();

            return response()->json([
                "status" => "success",
                "mode" => "demo",
                "order_id" => $order_id,
                "amount" => $total_fees_month,
                "student_name" => $request->student_name,
                "email" => $request->email,
                "mobile" => $cleanedMobile,
                "receipt_no" => $receipt,
                "message" => "Payment completed successfully! Receipt No: " . $receipt . ". You can now click 'Get Receipt' to view and download it."
            ]);
        }

  }

    function paymentstore(REQUEST $request){
      $orderid = $request->razorpay_order_id;
      $paymentid = $request->razorpay_payment_id;
      $signature = $request->razorpay_signature;

      $a = Payment::where('order_id',$orderid)->first();
      if(!$a){
        return redirect('/')->with('error','Payment Failed: Order not found');
      }

      $key = config('services.razorpay.key', env('RAZORPAY_KEY_ID'));
      $secret = config('services.razorpay.secret', env('RAZORPAY_KEY_SECRET'));

      // Server-side payment signature verification
      if ($key && $secret && $signature) {
          try {
              $api = new Api($key, $secret);
              $attributes = [
                  'razorpay_order_id' => $orderid,
                  'razorpay_payment_id' => $paymentid,
                  'razorpay_signature' => $signature
              ];
              $api->utility->verifyPaymentSignature($attributes);
          } catch (\Exception $e) {
              \Illuminate\Support\Facades\Log::error('Razorpay signature verification failed: ' . $e->getMessage());
              return redirect('/')->with('error', 'Payment verification failed: Invalid signature');
          }
      }

      $a->payment_status="Successful";
      $a->payment_id = $paymentid;
      $a->update();

      return redirect('/')->with('success','Payment Sucessfully Completed');
    }


}


