<?php

namespace Database\Factories;

use App\Models\AiFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiFeature>
 */
class AiFeatureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'category' => fake()->randomElement(['CLINICAL', 'ASSISTANT']),
            'default_prompt_key' => fake()->optional()->slug(2),
            'default_model' => 'gemini-2.0-flash',
            'is_active' => true,
        ];
    }
}
