<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_creates_audit_log_entry(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'doctor@clinic.test',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'doctor@clinic.test',
            'password' => 'secret123',
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'action' => 'auth.login',
            'category' => 'auth',
        ]);
    }

    public function test_logout_creates_audit_log_entry(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $issued = app(TokenService::class)->create($user, 'test', ['*']);

        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->postJson('/api/v1/auth/logout')
            ->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'auth.logout',
        ]);
    }

    public function test_audit_log_records_ip_and_user_agent(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'audit@clinic.test',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'audit@clinic.test',
            'password' => 'secret123',
        ]);

        /** @var AuditLog $log */
        $log = AuditLog::where('action', 'auth.login')->first();
        $this->assertNotNull($log);
        $this->assertNotNull($log->ip_address);
        $this->assertNotNull($log->user_agent);
    }
}
