<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\TwoFactorService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use PragmaRX\Google2FA\Google2FA;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(PermissionService::class);

        $this->app->singleton(TwoFactorService::class, function ($app) {
            return new TwoFactorService(
                new Google2FA,
                $app->make('encrypter'),
            );
        });
    }

    public function boot(): void
    {
        // Before any policy check, resolve granular RBAC permissions through
        // PermissionService. A permission key is a dotted "<module>.<action>"
        // string; anything else falls through to policy resolution.
        Gate::before(function (User $user, string $ability) {
            if (str_contains($ability, '.')) {
                return app(PermissionService::class)->can($user, $ability);
            }

            return null;
        });
    }
}
