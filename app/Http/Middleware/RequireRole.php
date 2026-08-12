<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards a route by role. Usage: `middleware('role:SUPER_ADMIN')` or
 * `middleware('role:CLINIC_OWNER,DOCTOR')` (any of the listed roles).
 * Tenant isolation is already enforced by the `tenant` middleware; this
 * only restricts the role an actor must hold.
 */
class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasRole($roles)) {
            abort(403, 'This action is not authorized for your role.');
        }

        return $next($request);
    }
}
