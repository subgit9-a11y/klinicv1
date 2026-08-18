<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_endpoint_is_rate_limited(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);
        $user = User::factory()->forTenant($tenant)->create([
            'email' => 'ratelimit@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Send 5 failed login attempts (the throttle:5,1 limit).
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'ratelimit@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        // 6th request within the window should be throttled (429).
        $this->postJson('/api/v1/auth/login', [
            'email' => 'ratelimit@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_authenticated_endpoints_are_rate_limited(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);
        $user = User::factory()->forTenant($tenant)->create(['role' => 'DOCTOR']);

        $issued = app(TokenService::class)->create($user, 'test', ['*']);
        $headers = ['Authorization' => 'Bearer '.$issued['token']];

        // Exhaust the 60/minute limit (60 successful requests).
        for ($i = 0; $i < 61; $i++) {
            $this->withHeaders($headers)->getJson('/api/v1/patients')->assertSuccessful();
        }

        // 62nd request within the window should be throttled (429).
        $this->withHeaders($headers)
            ->getJson('/api/v1/patients')
            ->assertStatus(429);
    }
}
