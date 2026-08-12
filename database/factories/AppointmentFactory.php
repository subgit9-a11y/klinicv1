<?php

namespace Database\Factories;

use App\Models\Appointment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('now', '+30 days');
        $start = fake()->time('H:i');
        $duration = fake()->randomElement([15, 30, 45, 60]);

        return [
            'type' => fake()->randomElement(['WALK_IN', 'IN_PERSON', 'ONLINE', 'FOLLOW_UP']),
            'status' => 'SCHEDULED',
            'appointment_date' => $date->format('Y-m-d'),
            'start_time' => $start,
            'duration_minutes' => $duration,
            'reason' => fake()->optional()->sentence(3),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
