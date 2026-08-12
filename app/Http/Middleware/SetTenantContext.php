<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant for the request from the authenticated user and
 * loads it into TenantContext. Super Admin (no tenant_id) keeps a null context
 * so it operates globally across tenants. Runs inside the `auth` group so a
 * user is always present; guests are never reached here.
 */
class SetTenantContext
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $this->tenantContext->set($user->tenant_id);
        }

        return $next($request);
    }
}
