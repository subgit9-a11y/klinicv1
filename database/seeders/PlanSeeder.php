<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $soloDoctor = Plan::updateOrCreate(
            ['code' => 'SOLO_DOCTOR'],
            [
                'name' => 'Solo Doctor',
                'description' => 'For individual practitioners running a single-doctor clinic.',
                'price_cents' => 99900,
                'currency' => 'INR',
                'billing_cycle' => 'MONTHLY',
                'is_active' => true,
                'max_users' => 1,
                'max_doctors' => 1,
                'max_patients' => null,
                'max_appointments_per_day' => null,
                'ai_request_limit_per_day' => 50,
                'ipd_enabled' => false,
                'treatments_enabled' => true,
            ]
        );

        $smallClinic = Plan::updateOrCreate(
            ['code' => 'SMALL_CLINIC'],
            [
                'name' => 'Small Clinic',
                'description' => 'For small multi-doctor clinics with therapists, IPD, and treatments.',
                'price_cents' => 199900,
                'currency' => 'INR',
                'billing_cycle' => 'MONTHLY',
                'is_active' => true,
                'max_users' => 10,
                'max_doctors' => 5,
                'max_patients' => null,
                'max_appointments_per_day' => null,
                'ai_request_limit_per_day' => 200,
                'ipd_enabled' => true,
                'treatments_enabled' => true,
            ]
        );

        $this->seedFeatures($soloDoctor, [
            ['patients', true, null, null],
            ['appointments', true, null, null],
            ['emr', true, null, null],
            ['prescriptions', true, null, null],
            ['treatments', true, null, null],
            ['ipd', false, null, null],
            ['ai_scribe', true, 50, 'PER_DAY'],
            ['ai_patient_summary', true, 50, 'PER_DAY'],
            ['billing', true, null, null],
            ['documents', true, null, null],
            ['notifications', true, null, null],
            ['reports', true, null, null],
        ]);

        $this->seedFeatures($smallClinic, [
            ['patients', true, null, null],
            ['appointments', true, null, null],
            ['emr', true, null, null],
            ['prescriptions', true, null, null],
            ['treatments', true, null, null],
            ['ipd', true, null, null],
            ['ai_scribe', true, 200, 'PER_DAY'],
            ['ai_patient_summary', true, 200, 'PER_DAY'],
            ['billing', true, null, null],
            ['documents', true, null, null],
            ['notifications', true, null, null],
            ['reports', true, null, null],
        ]);
    }

    private function seedFeatures(Plan $plan, array $features): void
    {
        foreach ($features as [$key, $enabled, $limit, $unit]) {
            PlanFeature::updateOrCreate(
                ['plan_id' => $plan->id, 'feature_key' => $key],
                [
                    'enabled' => $enabled,
                    'limit_value' => $limit,
                    'limit_unit' => $unit,
                ]
            );
        }
    }
}
