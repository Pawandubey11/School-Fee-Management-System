<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\payment;
use Illuminate\Support\Facades\Validator;

class displayController extends Controller
{   
    function receipt(REQUEST $request){
        // Clean mobile number: strip spaces, dashes, leading +91, 91, or leading 0
        $cleanedMobile = preg_replace('/[\s\-]/', '', $request->fetch_mobile);
        $cleanedMobile = preg_replace('/^(\+91|91|0)(?=\d{10}$)/', '', $cleanedMobile);
        $request->merge(['fetch_mobile' => $cleanedMobile]);

        $validator = Validator::make($request->all(), [
            'fetch_name' => 'required|string|regex:/^[a-zA-Z\s]+$/',
            'fetch_email' => 'required|email',
            'fetch_mobile' => 'required|numeric|digits:10',
        ]);
        if ($validator->fails()) {
            return response()->json(['error'=>'validateerror','message' => $validator->errors(),]);
        }

        $name = trim($request->fetch_name);
        $email = trim($request->fetch_email);
        $mobile = $cleanedMobile;

        // Fetch record from db with case-insensitive matching
        $record = Payment::whereRaw('LOWER(student_name) = ?', [strtolower($name)])
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->where('mobile', $mobile)
            ->first();

        // Fallback: match by email and mobile if name had minor differences
        if (!$record) {
            $record = Payment::whereRaw('LOWER(email) = ?', [strtolower($email)])
                ->where('mobile', $mobile)
                ->first();
        }

        if ($record) {
            return response()->json($record);
        } else {
            return response()->json([
                'error' => 'recorderror',
                'message' => 'Sorry, no payment record was found for this student. Please make sure the fee payment form was submitted successfully and the email and mobile match.'
            ]);
        }
    }

    function receipt_download(REQUEST $request){
    // Retrieve data from the request
    $receipt = $request->query('receipt');
    $studentname = $request->query('studentname');
    $email = $request->query('email');
    $mobile = $request->query('mobile');
    $fees = $request->query('fees');
    $month = $request->query('month');
    $totalfees = $request->query('totalfees');
    $address = $request->query('address');
    $classes = $request->query('classes');
    $paymentstatus = $request->query('paymentstatus');
    $paytime = $request->query('paytime');
// Pass the data to the view
  return view('receipt_download', compact('receipt', 'studentname', 'email', 'mobile', 'classes','fees', 'month', 'totalfees', 'paymentstatus','address','paytime'));
    
}


}
