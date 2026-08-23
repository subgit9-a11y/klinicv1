<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Adversarial security sweep: XSS payloads, SQLi attempts, IDOR and
 * privilege-escalation checks against user-facing endpoints.
 */
class SecurityAdversarialTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->forTenant($this->tenant)->role('CLINIC_OWNER')->create();
        app(TenantContext::class)->set($this->tenant->id);
    }

    public function test_xss_payload_in_patient_name_is_escaped_in_ui(): void
    {
        $payload = '<script>alert(1)</script>';
        Patient::factory()->create(['first_name' => $payload, 'last_name' => 'Test']);

        $response = $this->actingAs($this->owner)->get('/patients');

        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $this->assertSame($payload, Patient::first()->first_name); // stored raw, rendered escaped
    }

    public function test_sli_attempt_in_patient_search_is_safe(): void
    {
        $this->actingAs($this->owner)
            ->get("/patients?search='; DROP TABLE patients; --")
            ->assertOk();

        $this->assertDatabaseCount('patients', 0); // table intact, treated as a literal
    }

    public function test_idor_patient_page_of_other_tenant_is_blocked(): void
    {
        $other = Tenant::factory()->create();
        $ctx = app(TenantContext::class);
        $ctx->set($other->id);
        $foreign = Patient::factory()->create();
        $ctx->set($this->tenant->id);

        $this->actingAs($this->owner)
            ->get('/patients/'.$foreign->id)
            ->assertNotFound();
    }

    public function test_receptionist_cannot_manage_rbac(): void
    {
        $receptionist = User::factory()->forTenant($this->tenant)->role('RECEPTIONIST')->create();

        $this->actingAs($receptionist)->get('/super-admin/rbac')->assertForbidden();
        $this->actingAs($receptionist)->get('/super-admin/tenants')->assertForbidden();
        $this->actingAs($receptionist)->get('/super-admin/integrations')->assertForbidden();
    }

    public function test_left_public_booking_status_rate_limited(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $response = $this->get('/book/status?reference=1&phone=000000');
            if ($i < 20) {
                $response->assertOk();
            } else {
                $response->assertStatus(429);
            }
        }
    }

    public function test_integration_account_secrets_never_rendered(): void
    {
        $service = new \App\Services\Integrations\IntegrationAccountService;
        $service->upsert('cashfree', ['app_id' => 'TOP_SECRET_APP_ID', 'secret_key' => 'TOP-SECRET-KEY-xyz']);

        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get('/super-admin/integrations')
            ->assertOk()
            ->assertDontSee('TOP_SECRET_APP_ID')
            ->assertDontSee('TOP-SECRET-KEY-xyz')
            ->assertSee('••••');
    }

    public function test_public_endpoints_require_no_auth_but_do_not_cross_tenants(): void
    {
        // Web login page publicly reachable; clinic data not.
        $this->get('/login')->assertOk();
        $this->get('/patients')->assertRedirect('/login');
        $this->getJson('/api/v1/patients')->assertUnauthorized();
    }

    public function test_tenant_injection_is_force_stamped_on_create(): void
    {
        $other = Tenant::factory()->create();

        // BelongsToTenant creating-hook must override any supplied tenant_id.
        $patient = new Patient([
            'k360_uid' => 'K360-P-TESTSEC01',
            'first_name' => 'Sneaky',
            'last_name' => 'Tenant',
            'phone' => '9000000001',
            'tenant_id' => $other->id, // attempted injection
        ]);
        $patient->save();

        $this->assertSame($this->tenant->id, $patient->refresh()->tenant_id);
    }
}
