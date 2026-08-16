<?php

namespace Database\Factories;

use App\Models\AiPrompt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiPrompt>
 */
class AiPromptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name' => fake()->words(3, true),
            'system' => fake()->randomElement(['GENERAL', 'AYURVEDA', 'SIDDHA', 'HOMEOPATHY']),
            'is_active' => true,
        ];
    }
}
