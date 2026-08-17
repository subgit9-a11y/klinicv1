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
use Illuminate\Console\Scheduling\Schedule;

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
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Appointment reminders — for appointments in the next 24h.
        $schedule->command('klinic:send-appointment-reminders')->everyFifteenMinutes();
        // Treatment session reminders.
        $schedule->command('klinic:send-treatment-reminders')->everyFifteenMinutes();
        // Follow-up reminders for due follow-ups.
        $schedule->command('klinic:send-followup-reminders')->dailyAt('09:00');
        // Retry pending/failed notification deliveries.
        $schedule->command('klinic:retry-notifications')->everyFiveMinutes();
        // Expire subscriptions past their end date.
        $schedule->command('klinic:check-subscriptions')->dailyAt('00:30');
        // Reconcile pending payments against the gateway.
        $schedule->command('klinic:reconcile-payments')->hourly();
        // Clean up stale/old records (expired tokens, old audit logs per retention).
        $schedule->command('klinic:cleanup')->dailyAt('02:00');
    })
    ->create();
