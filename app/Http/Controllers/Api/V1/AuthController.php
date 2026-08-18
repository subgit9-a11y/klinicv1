<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Auth\TokenService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * @group Authentication
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly TenantContext $tenantContext,
        private readonly AuditService $audit,
    ) {}

    /**
     * Exchange credentials for an API token.
     */
    public function login(Request $request): Response
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account is inactive.'],
            ]);
        }

        $issued = $this->tokenService->create(
            $user,
            $validated['device_name'] ?? 'api',
            ['*'],
        );

        if ($user->tenant_id) {
            $this->tenantContext->set($user->tenant_id);
        }

        $this->audit->record('auth.login', 'auth', ['after' => ['user_id' => $user->id]], null, $user, $request);

        return response([
            'message' => 'Authenticated.',
            'token' => $issued['token'],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'tenant_id' => $user->tenant_id,
            ],
        ]);
    }

    /**
     * Revoke the current token.
     */
    public function logout(Request $request): Response
    {
        $token = $request->attributes->get('api_token');

        if ($token) {
            $token->delete();
        }

        $this->audit->record('auth.logout', 'auth', [], null, $request->user(), $request);

        return response(['message' => 'Token revoked.']);
    }
}
