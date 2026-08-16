<?php

namespace Database\Factories;

use App\Models\AiModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiModel>
 */
class AiModelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider' => 'gemini',
            'model_id' => fake()->unique()->lexify('gemini-?.0-????'),
            'display_name' => fake()->words(3, true),
            'context_window' => fake()->optional()->numerify('##### tokens'),
            'supports_vision' => fake()->boolean(),
            'supports_structured' => fake()->boolean(),
            'input_cost_per_million_cents' => fake()->numberBetween(0, 1000),
            'output_cost_per_million_cents' => fake()->numberBetween(0, 4000),
            'is_active' => true,
        ];
    }
}
