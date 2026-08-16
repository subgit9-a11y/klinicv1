<?php

namespace Database\Factories;

use App\Models\AiRequest;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiRequest>
 */
class AiRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id(),
            'user_id' => User::factory(),
            'ai_feature_id' => null,
            'ai_prompt_version_id' => null,
            'provider' => 'GEMINI',
            'model' => 'gemini-2.0-flash',
            'contextable_type' => Patient::class,
            'contextable_id' => Patient::factory(),
            'input_summary' => fake()->optional()->sentence(),
            'output' => fake()->optional()->paragraph(),
            'output_status' => 'DRAFT',
            'status' => 'SUCCESS',
            'input_tokens' => fake()->optional()->numberBetween(100, 5000),
            'output_tokens' => fake()->optional()->numberBetween(50, 2000),
            'estimated_cost_cents' => 0,
            'duration_ms' => fake()->optional()->numberBetween(100, 5000),
            'error' => null,
            'approved_at' => null,
            'approved_by' => null,
        ];
    }
}
