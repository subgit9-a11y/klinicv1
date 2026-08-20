<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates API requests via Bearer token.
 *
 * On success, sets the authenticated user on the request, loads the
 * ApiToken model, and initialises the TenantContext so tenant-scoped
 * queries work transparently in API controllers.
 */
class AuthenticateApiToken
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (! $bearer) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $apiToken = $this->tokenService->validate($bearer);

        if (! $apiToken) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        /** @var User|null $user */
        $user = User::find($apiToken->user_id);

        if (! $user || ! $user->is_active) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Suspended/archived tenant: tokens are unusable until re-activated.
        // A null relation means the tenant row was archived (soft delete).
        if ($user->tenant_id !== null && ($user->tenant === null || $user->tenant->status === 'SUSPENDED')) {
            return response()->json(['message' => 'Clinic is suspended.'], 403);
        }

        auth()->setUser($user);
        $request->attributes->set('api_token', $apiToken);

        if ($apiToken->tenant_id) {
            $this->tenantContext->set($apiToken->tenant_id);
        }

        return $next($request);
    }
}
