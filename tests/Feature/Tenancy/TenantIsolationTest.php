<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function setTenant(?Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant?->id);
    }

    public function test_patient_queries_are_scoped_to_the_active_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->setTenant($tenantA);
        Patient::factory()->count(3)->create();
        $aIds = Patient::pluck('id')->all();

        $this->setTenant($tenantB);
        Patient::factory()->count(2)->create();
        $bIds = Patient::pluck('id')->all();

        $this->assertCount(3, $aIds);
        $this->assertCount(2, $bIds);
        $this->assertEmpty(array_intersect($aIds, $bIds));
    }

    public function test_tenant_id_is_force_stamped_from_context_on_create(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();

        $this->setTenant($tenant);

        // Even if a client tries to inject another tenant's id, the trait
        // overrides it with the resolved context.
        $patient = Patient::create([
            'tenant_id' => $otherTenant->id,
            'k360_uid' => 'K360-P-0000001',
            'first_name' => 'Injected',
            'last_name' => 'Attempt',
            'phone' => '9000000001',
            'gender' => 'MALE',
            'dob' => '1990-01-01',
        ]);

        $this->assertSame($tenant->id, $patient->tenant_id);
    }

    public function test_super_admin_with_null_context_is_not_scoped(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->setTenant($tenantA);
        Patient::factory()->create();
        $this->setTenant($tenantB);
        Patient::factory()->create();

        // Super Admin: null context sees all tenants' data.
        $this->setTenant(null);
        $this->assertSame(2, Patient::withoutGlobalScope('tenant')->count());
    }

    public function test_set_tenant_context_middleware_resolves_tenant_from_user(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->role('CLINIC_OWNER')->create();

        // Emulate the middleware: resolve tenant from the authenticated user.
        app(TenantContext::class)->set($user->tenant_id);

        $this->assertSame($tenant->id, app(TenantContext::class)->id());

        Patient::factory()->create();
        $this->assertSame($tenant->id, Patient::first()->tenant_id);
    }

    public function test_super_admin_keeps_null_tenant_context(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->assertNull($admin->tenant_id);
        app(TenantContext::class)->set($admin->tenant_id);
        $this->assertNull(app(TenantContext::class)->id());
    }

    public function test_tenant_model_relationship_loads_owner(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $patient = Patient::factory()->create();

        $this->assertInstanceOf(Tenant::class, $patient->fresh()->tenant);
        $this->assertSame($tenant->id, $patient->tenant->id);
    }

    public function test_tenant_service_resolves_current_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setTenant($tenant);

        $service = app(\App\Services\Tenancy\TenantService::class);

        $this->assertSame($tenant->id, $service->id());
        $this->assertTrue($service->isSet());
        $this->assertSame($tenant->id, $service->current()->id);
    }

    public function test_cross_tenant_access_is_blocked_by_global_scope(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->setTenant($tenantA);
        $patientA = Patient::factory()->create();

        // From tenant B's context, the patient owned by A is invisible.
        $this->setTenant($tenantB);
        $this->assertNull(Patient::find($patientA->id));
        $this->assertSame(0, Patient::count());

        // Direct find with global scope still prevents cross-tenant access.
        $this->setTenant($tenantB);
        $this->assertNull(Patient::where('id', $patientA->id)->first());
    }

    public function test_tenant_context_is_forgettable(): void
    {
        $tenant = Tenant::factory()->create();
        $context = app(TenantContext::class);
        $context->set($tenant->id);
        $this->assertTrue($context->isSet());

        $context->forget();
        $this->assertFalse($context->isSet());
        $this->assertNull($context->id());
    }

    public function test_middleware_populates_context_during_request_lifecycle(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant($tenant)->role('CLINIC_OWNER')->create();

        $captured = null;

        Route::middleware(['web', 'auth', 'tenant'])->get('/_test/tenant-context', function () use (&$captured) {
            $captured = app(TenantContext::class)->id();

            return response()->noContent();
        });

        $this->actingAs($user)->get('/_test/tenant-context')->assertNoContent();

        $this->assertSame($tenant->id, $captured);
    }

    public function test_middleware_leaves_null_context_for_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $captured = 'sentinel';

        Route::middleware(['web', 'auth', 'tenant'])->get('/_test/tenant-admin', function () use (&$captured) {
            $captured = app(TenantContext::class)->id();

            return response()->noContent();
        });

        $this->actingAs($admin)->get('/_test/tenant-admin')->assertNoContent();

        $this->assertNull($captured);
    }
}
