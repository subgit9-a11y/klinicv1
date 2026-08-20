<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Livewire\Onboarding\ClinicSetupWizard;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClinicSetupWizardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['name' => 'Setup Clinic']);
        $this->owner = User::factory()->forTenant($this->tenant)->role('CLINIC_OWNER')->create();
        // DoctorService/catalog services resolve the tenant from context.
        app(TenantContext::class)->set($this->tenant->id);
    }

    public function test_clinic_owner_can_view_setup_wizard(): void
    {
        Livewire::actingAs($this->owner)
            ->test(ClinicSetupWizard::class)
            ->assertStatus(200)
            ->assertSee('set up your clinic')
            ->assertSet('clinic_name', 'Setup Clinic');
    }

    public function test_non_owner_gets_403(): void
    {
        $doctor = User::factory()->forTenant($this->tenant)->role('DOCTOR')->create();

        Livewire::actingAs($doctor)
            ->test(ClinicSetupWizard::class)
            ->assertStatus(403);
    }

    public function test_save_profile_updates_tenant(): void
    {
        Livewire::actingAs($this->owner)
            ->test(ClinicSetupWizard::class)
            ->set('clinic_name', 'Renamed Clinic')
            ->set('clinic_phone', '044-1234')
            ->call('saveProfile')
            ->assertSet('step', 2);

        $this->assertSame('Renamed Clinic', $this->tenant->fresh()->name);
        $this->assertSame('044-1234', $this->tenant->fresh()->phone);
    }

    public function test_add_doctor_creates_doctor_user_in_tenant(): void
    {
        Livewire::actingAs($this->owner)
            ->test(ClinicSetupWizard::class)
            ->set('doctor_name', 'Dr. Meena')
            ->set('doctor_email', 'meena@clinic.test')
            ->set('doctor_password', 'secret123')
            ->set('doctor_specialization', 'Kayachikitsa')
            ->call('addDoctor')
            ->assertSet('step', 3);

        $this->assertDatabaseHas('users', [
            'email' => 'meena@clinic.test',
            'tenant_id' => $this->tenant->id,
            'role' => 'DOCTOR',
            'specialization' => 'Kayachikitsa',
        ]);
    }

    public function test_add_service_creates_treatment_service(): void
    {
        Livewire::actingAs($this->owner)
            ->test(ClinicSetupWizard::class)
            ->set('service_name', 'Abhyanga')
            ->set('service_category', 'Panchakarma')
            ->set('service_duration_minutes', 45)
            ->set('service_price_rupees', 1200)
            ->call('addService')
            ->assertSet('step', 4);

        $this->assertDatabaseHas('treatment_services', [
            'name' => 'Abhyanga',
            'category' => 'Panchakarma',
            'duration_minutes' => 45,
            'price_cents' => 120000,
        ]);
    }

    public function test_add_ward_creates_ipd_ward_and_finishes(): void
    {
        Livewire::actingAs($this->owner)
            ->test(ClinicSetupWizard::class)
            ->set('step', 4)
            ->set('ward_name', 'General Ward A')
            ->set('ward_type', 'GENERAL')
            ->call('addWard')
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('ipd_wards', ['name' => 'General Ward A']);
    }

    public function test_skip_advances_steps_and_finish_redirects(): void
    {
        Livewire::actingAs($this->owner)
            ->test(ClinicSetupWizard::class)
            ->set('step', 2)
            ->call('skipStep')
            ->assertSet('step', 3)
            ->call('skipStep')
            ->assertSet('step', 4)
            ->call('finish')
            ->assertRedirect(route('dashboard'));
    }
}
