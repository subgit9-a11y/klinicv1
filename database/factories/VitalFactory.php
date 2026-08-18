<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Vital;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vital>
 */
class VitalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id(),
            'patient_id' => Patient::factory(),
            'consultation_id' => null,
            'recorded_by' => null,
            'systolic_bp' => fake()->numerify('1##'),
            'diastolic_bp' => fake()->numerify('##'),
            'pulse' => fake()->numerify('##'),
            'temperature' => fake()->numerify('##.#'),
            'respiratory_rate' => fake()->numerify('##'),
            'spo2' => fake()->numerify('##'),
            'height' => fake()->numerify('###'),
            'weight' => fake()->numerify('##.#'),
            'bmi' => null,
            'pain_score' => fake()->optional()->numerify('#'),
            'custom_vitals' => null,
            'recorded_at' => now(),
        ];
    }
}
