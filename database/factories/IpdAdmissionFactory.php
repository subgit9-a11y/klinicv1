<?php

namespace Database\Factories;

use App\Models\IpdAdmission;
use App\Models\IpdBed;
use App\Models\Patient;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IpdAdmission>
 */
class IpdAdmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id(),
            'patient_id' => Patient::factory(),
            'ipd_bed_id' => IpdBed::factory(),
            'admitting_doctor_id' => null,
            'ipd_number' => fake()->unique()->numerify('K360-IPD-######'),
            'admission_type' => fake()->randomElement(['ROUTINE', 'EMERGENCY', 'TRANSFER']),
            'admission_reason' => fake()->optional()->sentence(),
            'provisional_diagnosis' => fake()->optional()->sentence(),
            'admitted_at' => now(),
            'discharged_at' => null,
            'status' => 'ADMITTED',
            'metadata' => null,
        ];
    }
}
