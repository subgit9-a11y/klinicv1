<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Issues and validates API tokens.
 *
 * Tokens are returned to the client only once at creation time, as
 * `k360_<plain>`. Internally we store only the sha256 hash, so a
 * database leak cannot be replayed.
 */
class TokenService
{
    private const PREFIX = 'k360_';

    /**
     * Create a new token for a user.
     *
     * @param array<int,string>|null $abilities
     * @return array{token: string, model: ApiToken}
     */
    public function create(User $user, string $name = 'default', ?array $abilities = null, ?\DateTimeInterface $expiresAt = null): array
    {
        $plain = Str::random(60);
        $fullToken = self::PREFIX . $plain;

        $token = ApiToken::create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'name' => $name,
            'token_hash' => hash('sha256', $fullToken),
            'token_prefix' => substr($plain, 0, 8),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        return ['token' => $fullToken, 'model' => $token];
    }

    /**
     * Validate a bearer token and return the matching ApiToken, or null.
     * Updates last_used_at on success.
     */
    public function validate(string $bearerToken): ?ApiToken
    {
        if (!str_starts_with($bearerToken, self::PREFIX)) {
            return null;
        }

        $hash = hash('sha256', $bearerToken);

        /** @var ApiToken|null $token */
        $token = ApiToken::where('token_hash', $hash)->first();

        if (!$token) {
            return null;
        }

        if ($token->isExpired()) {
            return null;
        }

        $token->forceFill(['last_used_at' => now()])->save();

        return $token;
    }

    /**
     * Revoke a token by its id (owned by the given user).
     */
    public function revoke(User $user, int $tokenId): bool
    {
        return (bool) ApiToken::where('user_id', $user->id)->where('id', $tokenId)->delete();
    }
}
