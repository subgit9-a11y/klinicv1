<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Teleconsultation;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Teleconsultation>
 */
class TeleconsultationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id(),
            'appointment_id' => null,
            'patient_id' => Patient::factory(),
            'user_id' => User::factory(),
            'meeting_id' => fake()->optional()->numerify('meet-####'),
            'meeting_url' => fake()->optional()->url(),
            'status' => 'SCHEDULED',
            'started_at' => null,
            'ended_at' => null,
        ];
    }
}
