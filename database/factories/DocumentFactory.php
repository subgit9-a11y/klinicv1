<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id(),
            'patient_id' => Patient::factory(),
            'consultation_id' => null,
            'ipd_admission_id' => null,
            'name' => fake()->word() . '.pdf',
            'type' => fake()->randomElement(['LAB_REPORT', 'IMAGING', 'PRESCRIPTION', 'DISCHARGE_SUMMARY', 'CONSENT', 'REFERRAL', 'CLINICAL', 'OTHER']),
            'disk' => 'local',
            'path' => 'tenants/' . fake()->numberBetween(1, 100) . '/documents/' . fake()->uuid() . '.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1024, 10485760),
            'metadata' => ['uploaded_via' => 'web'],
            'uploaded_by' => User::factory(),
        ];
    }
}
