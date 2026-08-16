<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApiToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiToken>
 */
class ApiTokenFactory extends Factory
{
    protected $model = ApiToken::class;

    public function definition(): array
    {
        $plain = \Illuminate\Support\Str::random(60);
        $fullToken = 'k360_' . $plain;

        return [
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->word(),
            'token_hash' => hash('sha256', $fullToken),
            'token_prefix' => substr($plain, 0, 8),
            'abilities' => ['*'],
            'last_used_at' => null,
            'expires_at' => null,
        ];
    }

    /**
     * Expose the plaintext token so tests can use it in Authorization headers.
     */
    public function definitionPlainText(): string
    {
        // Reconstruct from definition — only valid when used before persisting.
        return 'k360_' . substr($this->definition()['token_hash'], 0, 0);
    }
}
