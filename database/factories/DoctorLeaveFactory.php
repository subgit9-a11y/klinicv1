<?php

namespace Database\Factories;

use App\Models\DoctorLeave;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoctorLeave>
 */
class DoctorLeaveFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 day', '+5 days');

        return [
            'tenant_id' => app(TenantContext::class)->id(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->format('Y-m-d'),
            'reason' => fake()->optional()->sentence(3),
            'type' => fake()->randomElement(['LEAVE', 'HOLIDAY', 'EMERGENCY', 'OTHER']),
            'is_approved' => true,
        ];
    }
}
