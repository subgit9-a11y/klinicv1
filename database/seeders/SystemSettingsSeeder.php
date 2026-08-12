<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'app.name', 'value' => 'Klinic 360', 'category' => 'general', 'is_public' => true],
            ['key' => 'app.currency', 'value' => 'INR', 'category' => 'general', 'is_public' => true],
            ['key' => 'app.country_code', 'value' => 'IN', 'category' => 'general', 'is_public' => true],
            ['key' => 'appointment.default_duration_minutes', 'value' => '15', 'category' => 'appointments', 'is_public' => false],
            ['key' => 'appointment.online_buffer_minutes', 'value' => '30', 'category' => 'appointments', 'is_public' => false],
            ['key' => 'patient.uid_prefix', 'value' => 'K360-P-', 'category' => 'patients', 'is_public' => false],
            ['key' => 'invoice.number_prefix', 'value' => 'INV-', 'category' => 'billing', 'is_public' => false],
            ['key' => 'payment.number_prefix', 'value' => 'PAY-', 'category' => 'billing', 'is_public' => false],
            ['key' => 'refund.number_prefix', 'value' => 'REF-', 'category' => 'billing', 'is_public' => false],
            ['key' => 'ai.draft_only', 'value' => 'true', 'category' => 'ai', 'is_public' => false],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'category' => $setting['category'],
                    'is_public' => $setting['is_public'],
                ]
            );
        }
    }
}
