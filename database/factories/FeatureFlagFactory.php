<?php

namespace Database\Factories;

use App\Models\FeatureFlag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeatureFlag>
 */
class FeatureFlagFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'description' => fake()->optional()->sentence(),
            'is_global' => true,
            'default_enabled' => fake()->boolean(),
            'tenant_id' => null,
            'enabled' => false,
        ];
    }
}
