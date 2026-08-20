<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tenant suspension must be enforced, not merely recorded: suspended clinics'
 * users are signed out of the web app and their API tokens stop working.
 */
class TenantSuspensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_tenant_user_is_signed_out_of_web(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'SUSPENDED']);
        $user = User::factory()->forTenant($tenant)->role('CLINIC_OWNER')->create();

        $response = $this->actingAs($user)->get('/patients');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_active_tenant_user_passes_middleware(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'ACTIVE']);
        $user = User::factory()->forTenant($tenant)->role('CLINIC_OWNER')->create();

        // 'active' middleware allows the request (200 or redirect to an
        // authenticated page, but never a forced logout).
        $response = $this->actingAs($user)->get('/patients');

        $response->assertStatus(200);
        $this->assertAuthenticatedAs($user);
    }

    public function test_suspended_tenant_api_token_is_rejected(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'SUSPENDED']);
        $user = User::factory()->forTenant($tenant)->role('CLINIC_OWNER')->create();
        $issued = app(TokenService::class)->create($user, 'api', ['*']);

        $this->withHeaders(['Authorization' => 'Bearer '.$issued['token']])
            ->getJson('/api/v1/patients')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Clinic is suspended.');
    }

    public function test_archived_tenant_api_token_is_rejected(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'ACTIVE']);
        $user = User::factory()->forTenant($tenant)->role('CLINIC_OWNER')->create();
        $issued = app(TokenService::class)->create($user, 'api', ['*']);

        $tenant->delete(); // archived (soft delete)

        $this->withHeaders(['Authorization' => 'Bearer '.$issued['token']])
            ->getJson('/api/v1/patients')
            ->assertStatus(403);
    }

    public function test_super_admin_is_unaffected_by_tenant_suspension(): void
    {
        $admin = User::factory()->superAdmin()->create(); // tenant_id null

        $response = $this->actingAs($admin)->get('/patients');

        // Not signed out — super admin has no tenant to suspend.
        $response->assertDontSee('auth.tenant_suspended');
        $this->assertAuthenticatedAs($admin);
    }
}
