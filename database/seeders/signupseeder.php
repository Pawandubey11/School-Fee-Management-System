<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class signupseeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminEmail = env('ADMIN_EMAIL', 'admin@ethereal.edu.in');
        $adminPassword = env('ADMIN_PASSWORD', 'admin@12345');

        $store = User::where('email', $adminEmail)->first();
        if (!$store) {
            $store = new User;
        }
        $store->name = env('ADMIN_NAME', "Admin");
        $store->email = $adminEmail;
        $store->password = Hash::make($adminPassword);
        $store->save();
    }
}
