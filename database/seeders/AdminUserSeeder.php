<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'famillesmoutairou@gmail.com')],
            [
                'name' => 'Administrateur PRO BENIN SARL',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'AlerteMarche2026!')),
                'profile_type' => 'admin_public',
                'is_admin' => true,
                'primary_country' => 'BJ',
                'email_verified_at' => now(),
                'notify_email' => true,
            ]
        );
    }
}
