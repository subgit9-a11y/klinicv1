<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Livewire\Patients\Patient360;
use App\Livewire\Patients\PatientEdit;
use App\Livewire\Patients\PatientList;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PatientLivewireTest extends TestCase
{
    use RefreshDatabase;

    private function tenantUser(Tenant $tenant, string $role = 'CLINIC_OWNER'): User
    {
        return User::factory()->forTenant($tenant)->role($role)->create();
    }

    public function test_patient_list_component_renders(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);
        Patient::factory()->count(3)->create();

        $user = $this->tenantUser($tenant);

        Livewire::actingAs($user)
            ->test(PatientList::class)
            ->assertStatus(200)
            ->assertSee($tenant->patients()->first()->k360_uid);
    }

    public function test_patient_list_search_filters(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);

        $a = Patient::factory()->create(['first_name' => 'UniqueNameA']);
        $b = Patient::factory()->create(['first_name' => 'UniqueNameB']);

        $user = $this->tenantUser($tenant);

        Livewire::actingAs($user)
            ->test(PatientList::class)
            ->set('search', 'UniqueNameA')
            ->assertSee('UniqueNameA')
            ->assertDontSee('UniqueNameB');
    }

    public function test_register_patient_via_livewire_form(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);

        $user = $this->tenantUser($tenant);

        Livewire::actingAs($user)
            ->test(PatientList::class)
            ->call('toggleRegisterForm')
            ->set('first_name', 'TestPatient')
            ->set('phone', '9123456780')
            ->set('gender', 'MALE')
            ->call('registerPatient')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('patients', [
            'tenant_id' => $tenant->id,
            'first_name' => 'TestPatient',
            'phone' => '9123456780',
        ]);

        $patient = Patient::where('first_name', 'TestPatient')->first();
        $this->assertNotEmpty($patient->k360_uid);
        $this->assertMatchesRegularExpression('/^K360-P-\d{10}$/', $patient->k360_uid);
    }

    public function test_register_duplicate_phone_shows_error(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);
        Patient::factory()->create(['phone' => '9999999999']);

        $user = $this->tenantUser($tenant);

        Livewire::actingAs($user)
            ->test(PatientList::class)
            ->call('toggleRegisterForm')
            ->set('first_name', 'Dup')
            ->set('phone', '9999999999')
            ->set('gender', 'MALE')
            ->call('registerPatient')
            ->assertHasErrors(['phone']);
    }

    public function test_register_requires_first_name_and_phone(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);

        $user = $this->tenantUser($tenant);

        Livewire::actingAs($user)
            ->test(PatientList::class)
            ->call('toggleRegisterForm')
            ->call('registerPatient')
            ->assertHasErrors(['first_name', 'phone']);
    }

    public function test_patient_360_component_renders_tabs_and_overview(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);
        $patient = Patient::factory()->create(['first_name' => 'TabTest']);

        $user = $this->tenantUser($tenant);

        Livewire::actingAs($user)
            ->test(Patient360::class, ['patient' => $patient])
            ->assertStatus(200)
            ->assertSee($patient->k360_uid)
            ->assertSee('TabTest')
            ->assertSet('activeTab', 'overview')
            ->call('setTab', 'consent')
            ->assertSet('activeTab', 'consent');
    }

    public function test_patient_edit_component_updates_patient(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);
        $patient = Patient::factory()->create(['first_name' => 'OldName']);

        $user = $this->tenantUser($tenant);

        Livewire::actingAs($user)
            ->test(PatientEdit::class, ['patient' => $patient])
            ->set('first_name', 'NewName')
            ->call('update')
            ->assertHasNoErrors();

        $this->assertSame('NewName', $patient->fresh()->first_name);
        $this->assertSame($patient->k360_uid, $patient->fresh()->k360_uid);
    }

    public function test_patient_360_denies_cross_tenant_user(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        app(TenantContext::class)->set($tenantA->id);
        $patient = Patient::factory()->create();

        $userB = $this->tenantUser($tenantB, 'DOCTOR');

        Livewire::actingAs($userB)
            ->test(Patient360::class, ['patient' => $patient])
            ->assertStatus(403);
    }

    public function test_patient_360_displays_related_appointments_in_tab(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);
        $patient = Patient::factory()->create();

        Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'type' => 'TREATMENT',
            'status' => 'SCHEDULED',
        ]);

        $user = $this->tenantUser($tenant);

        Livewire::actingAs($user)
            ->test(Patient360::class, ['patient' => $patient])
            ->call('setTab', 'appointments')
            ->assertSet('activeTab', 'appointments')
            ->assertSee('TREATMENT')
            ->assertSee('SCHEDULED');
    }

    public function test_patient_360_displays_related_invoices_in_tab(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);
        $patient = Patient::factory()->create();

        Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'invoice_number' => 'K360-INV-TAB001',
        ]);

        $user = $this->tenantUser($tenant);

        Livewire::actingAs($user)
            ->test(Patient360::class, ['patient' => $patient])
            ->call('setTab', 'billing')
            ->assertSee('K360-INV-TAB001');
    }

    public function test_patient_360_refreshes_on_patient_updated_event(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);
        $patient = Patient::factory()->create(['first_name' => 'BeforeRefresh']);

        $user = $this->tenantUser($tenant);

        $component = Livewire::actingAs($user)
            ->test(Patient360::class, ['patient' => $patient]);

        $patient->update(['first_name' => 'AfterRefresh']);

        $component
            ->dispatch('patient-updated')
            ->assertSee('AfterRefresh');
    }

    public function test_patient_360_shows_empty_state_when_no_related_records(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant->id);
        $patient = Patient::factory()->create();

        $user = $this->tenantUser($tenant);

        Livewire::actingAs($user)
            ->test(Patient360::class, ['patient' => $patient])
            ->call('setTab', 'appointments')
            ->assertSee(__('klinic360.no_records'));
    }
}
