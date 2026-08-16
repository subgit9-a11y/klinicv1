<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\RequireTwoFactorChallenge;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // SetTenantContext must run on every web request — including Livewire's
        // /livewire/update AJAX endpoint, which is registered by Livewire outside
        // the per-route middleware stack in routes/web.php. Appending it to the
        // web group ensures the TenantContext singleton is populated after the
        // session middleware has resolved the authenticated user.
        $middleware->web(append: [
            SetTenantContext::class,
        ]);

        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
            'active' => EnsureAccountIsActive::class,
            '2fa' => RequireTwoFactorChallenge::class,
            'tenant' => SetTenantContext::class,
            'role' => RequireRole::class,
            'auth.api' => \App\Http\Middleware\AuthenticateApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
