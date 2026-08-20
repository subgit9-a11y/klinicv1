<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $disabled = $user !== null && ! $user->is_active;

        // Tenant suspension is enforced here: a suspended/archived clinic's
        // users are signed out (a null tenant relation means the tenant row
        // was archived via soft delete).
        $tenantSuspended = $user !== null
            && $user->tenant_id !== null
            && ($user->tenant === null || $user->tenant->status === 'SUSPENDED');

        if ($disabled || $tenantSuspended) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = $disabled
                ? __('klinic360.auth.account_disabled')
                : __('klinic360.auth.tenant_suspended');

            return redirect()->route('login')->withErrors(['email' => $message]);
        }

        return $next($request);
    }
}
