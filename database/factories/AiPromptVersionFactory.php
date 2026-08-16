<?php

namespace Database\Factories;

use App\Models\AiPrompt;
use App\Models\AiPromptVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiPromptVersion>
 */
class AiPromptVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ai_prompt_id' => AiPrompt::factory(),
            'version' => 1,
            'system_prompt' => 'You are a clinical AI assistant for Ayurveda/Siddha/Homeopathy. Generate draft clinical notes only.',
            'user_prompt_template' => 'Generate a clinical summary for patient {{patient_name}} with complaints {{complaints}}.',
            'expected_output_schema' => null,
            'default_model' => 'gemini-2.0-flash',
            'created_by' => User::factory(),
        ];
    }
}
