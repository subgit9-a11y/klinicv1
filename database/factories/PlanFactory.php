<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('PLAN_????'),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'price_cents' => fake()->numberBetween(99900, 499900),
            'currency' => 'INR',
            'billing_cycle' => 'MONTHLY',
            'is_active' => true,
            'max_users' => fake()->numberBetween(1, 10),
            'max_doctors' => fake()->numberBetween(1, 5),
            'ipd_enabled' => fake()->boolean(),
            'treatments_enabled' => true,
        ];
    }
}
