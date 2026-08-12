<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin — global, cross-tenant.
        User::updateOrCreate(
            ['email' => 'superadmin@klinic360.test'],
            [
                'name' => 'Super Admin',
                'phone' => '9000000000',
                'password' => Hash::make('password'),
                'role' => 'SUPER_ADMIN',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Demo clinic owner (Small Clinic plan) for development/testing.
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'ayur-clinic-demo'],
            [
                'name' => 'Ayur Clinic (Demo)',
                'plan_code' => 'SMALL_CLINIC',
                'status' => 'TRIAL',
                'system' => 'AYURVEDA',
                'country_code' => 'IN',
                'currency' => 'INR',
                'timezone' => 'Asia/Kolkata',
                'phone' => '9876543210',
                'email' => 'owner@ayurclinic.test',
                'trial_ends_at' => now()->addDays(14),
            ]
        );

        User::updateOrCreate(
            ['email' => 'owner@ayurclinic.test'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Dr. Demo Owner',
                'phone' => '9876543210',
                'password' => Hash::make('password'),
                'role' => 'CLINIC_OWNER',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
