<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->randomNumber(4),
            'plan_code' => 'SOLO_DOCTOR',
            'status' => 'TRIAL',
            'system' => fake()->randomElement(['AYURVEDA', 'SIDDHA', 'HOMEOPATHY', 'GENERAL']),
            'country_code' => 'IN',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'phone' => fake()->numerify('9#########'),
            'email' => fake()->safeEmail(),
            'address' => fake()->address(),
            'trial_ends_at' => now()->addDays(14),
        ];
    }

    public function smallClinic(): static
    {
        return $this->state(fn (array $attributes) => ['plan_code' => 'SMALL_CLINIC']);
    }
}
