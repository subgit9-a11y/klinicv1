<?php

namespace App\Providers;

use App\Services\Auth\TwoFactorService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;
use PragmaRX\Google2FA\Google2FA;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);

        $this->app->singleton(TwoFactorService::class, function ($app) {
            return new TwoFactorService(
                new Google2FA,
                $app->make('encrypter'),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
