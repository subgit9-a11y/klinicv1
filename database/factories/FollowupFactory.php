<?php

namespace Database\Factories;

use App\Models\Consultation;
use App\Models\Followup;
use App\Models\Patient;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Followup>
 */
class FollowupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id(),
            'patient_id' => Patient::factory(),
            'consultation_id' => Consultation::factory(),
            'due_date' => $this->faker->dateTimeBetween('-1 week', '+1 week')->format('Y-m-d'),
            'status' => 'PENDING',
            'instructions' => $this->faker->optional()->sentence(),
        ];
    }
}
