<?php

namespace Database\Factories;

use App\Models\DoctorAvailability;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoctorAvailability>
 */
class DoctorAvailabilityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id(),
            'day_of_week' => fake()->randomElement(['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT']),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'break_start_time' => null,
            'break_end_time' => null,
            'is_active' => true,
        ];
    }
}
