<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreatmentPlan>
 */
class TreatmentPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id(),
            'patient_id' => Patient::factory(),
            'consultation_id' => null,
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'total_sessions' => fake()->numberBetween(3, 21),
            'completed_sessions' => 0,
            'status' => 'ACTIVE',
            'starts_at' => now(),
            'ends_at' => null,
        ];
    }
}
