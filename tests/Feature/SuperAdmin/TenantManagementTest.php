<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\TenantManagement;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TenantManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlanSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    public function test_super_admin_can_view_tenant_management(): void
    {
        Tenant::factory()->create(['name' => 'Alpha Clinic']);

        Livewire::actingAs($this->admin())
            ->test(TenantManagement::class)
            ->assertStatus(200)
            ->assertSee('Clinics')
            ->assertSee('Alpha Clinic');
    }

    public function test_non_super_admin_gets_403(): void
    {
        $user = User::factory()->forTenant(Tenant::factory()->create())->role('CLINIC_OWNER')->create();

        Livewire::actingAs($user)
            ->test(TenantManagement::class)
            ->assertStatus(403);
    }

    public function test_create_clinic_creates_tenant_owner_and_subscription(): void
    {
        Livewire::actingAs($this->admin())
            ->test(TenantManagement::class)
            ->set('name', 'Wellness Ayur')
            ->set('plan_code', 'SOLO_DOCTOR')
            ->set('system', 'AYURVEDA')
            ->set('owner_name', 'Dr Owner')
            ->set('owner_email', 'owner@wellness.test')
            ->set('owner_password', 'secret123')
            ->call('createClinic');

        $tenant = Tenant::where('name', 'Wellness Ayur')->first();
        $this->assertNotNull($tenant);
        $this->assertSame('TRIAL', $tenant->status);

        $this->assertDatabaseHas('users', [
            'tenant_id' => $tenant->id,
            'email' => 'owner@wellness.test',
            'role' => 'CLINIC_OWNER',
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'status' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.created']);
    }

    public function test_create_clinic_requires_valid_plan_and_name(): void
    {
        Livewire::actingAs($this->admin())
            ->test(TenantManagement::class)
            ->set('name', '')
            ->call('createClinic')
            ->assertHasErrors(['name']);
    }

    public function test_suspend_and_activate_clinic(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'ACTIVE']);

        Livewire::actingAs($this->admin())
            ->test(TenantManagement::class)
            ->call('suspend', $tenant->id);

        $this->assertSame('SUSPENDED', $tenant->fresh()->status);
        $this->assertNotNull($tenant->fresh()->suspended_at);

        Livewire::actingAs($this->admin())
            ->test(TenantManagement::class)
            ->call('activate', $tenant->id);

        $this->assertSame('ACTIVE', $tenant->fresh()->status);
        $this->assertNull($tenant->fresh()->suspended_at);
    }

    public function test_archive_soft_deletes_clinic(): void
    {
        $tenant = Tenant::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(TenantManagement::class)
            ->call('archive', $tenant->id);

        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.archived']);
    }

    public function test_edit_updates_clinic_details(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Old Name']);

        Livewire::actingAs($this->admin())
            ->test(TenantManagement::class)
            ->call('edit', $tenant->id)
            ->set('name', 'New Name')
            ->call('saveEdit');

        $this->assertSame('New Name', $tenant->fresh()->name);
    }

    public function test_status_filter_limits_list(): void
    {
        Tenant::factory()->create(['name' => 'Suspended One', 'status' => 'SUSPENDED']);
        Tenant::factory()->create(['name' => 'Active One', 'status' => 'ACTIVE']);

        Livewire::actingAs($this->admin())
            ->test(TenantManagement::class)
            ->set('statusFilter', 'SUSPENDED')
            ->assertSee('Suspended One')
            ->assertDontSee('Active One');
    }
    public function test_usage_drill_down_shows_metrics(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Usage Clinic']);
        $ctx = app(\App\Services\Tenancy\TenantContext::class);
        $ctx->set($tenant->id);

        $patient = \App\Models\Patient::factory()->create();
        $doctor = User::factory()->forTenant($tenant)->role('DOCTOR')->create();
        \App\Models\Appointment::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id, 'user_id' => $doctor->id]);
        $ctx->forget();

        Livewire::actingAs($this->admin())
            ->test(TenantManagement::class)
            ->call('viewUsage', $tenant->id)
            ->assertSee('Usage — Usage Clinic')
            ->assertSee('Appointments (total)')
            ->assertSee('Notifications sent')
            ->call('closeUsage')
            ->assertDontSee('Appointments (total)');
    }

    public function test_usage_metrics_count_only_target_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $ctx = app(\App\Services\Tenancy\TenantContext::class);
        $ctx->set($tenantB->id);
        \App\Models\Patient::factory()->create();
        $ctx->forget();

        $component = Livewire::actingAs($this->admin())->test(TenantManagement::class)->call('viewUsage', $tenantA->id);

        $usage = $component->viewData('usage');
        $this->assertSame(0, $usage['patients']);
        $this->assertTrue($usage !== null);
    }

}
