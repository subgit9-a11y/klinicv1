<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactorChallenge
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // User has 2FA enabled and hasn't passed the challenge this session yet.
        if ($user->hasTwoFactorEnabled() && ! $request->session()->get('auth.2fa.verified')) {
            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
