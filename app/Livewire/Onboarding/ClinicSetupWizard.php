<?php

declare(strict_types=1);

namespace App\Livewire\Onboarding;

use App\Models\Tenant;
use App\Services\IPD\IpdConfigurationService;
use App\Services\Staff\DoctorService;
use App\Services\Tenancy\TenantAdminService;
use App\Services\Treatments\TreatmentCatalogService;
use Livewire\Component;

/**
 * Guided post-signup setup for a new clinic (CLINIC_OWNER):
 *   1. Confirm clinic profile → 2. Add a doctor → 3. Add a treatment
 *   service → 4. Optionally add an IPD ward → done.
 * Every operational step is skippable; data goes through the same services
 * the admin screens use (DoctorService / TreatmentCatalogService /
 * IpdConfigurationService), so nothing here is a wizard-only shortcut.
 */
class ClinicSetupWizard extends Component
{
    public int $step = 1;

    // Step 1 — clinic profile
    public string $clinic_name = '';

    public string $clinic_email = '';

    public string $clinic_phone = '';

    // Step 2 — doctor
    public string $doctor_name = '';

    public string $doctor_email = '';

    public string $doctor_password = '';

    public ?string $doctor_specialization = null;

    // Step 3 — treatment service
    public string $service_name = '';

    public ?string $service_category = null;

    public ?int $service_duration_minutes = null;

    public ?int $service_price_rupees = null;

    // Step 4 — IPD ward
    public string $ward_name = '';

    public string $ward_type = 'GENERAL';

    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->isClinicOwner(), 403);

        $tenant = auth()->user()->tenant;
        if ($tenant !== null) {
            $this->clinic_name = $tenant->name;
            $this->clinic_email = (string) $tenant->email;
            $this->clinic_phone = (string) $tenant->phone;
        }
    }

    public function saveProfile(TenantAdminService $admin): void
    {
        $this->validate([
            'clinic_name' => 'required|string|max:120',
            'clinic_email' => 'nullable|email|max:190',
            'clinic_phone' => 'nullable|string|max:20',
        ]);

        $admin->update(auth()->user()->tenant, [
            'name' => $this->clinic_name,
            'email' => $this->clinic_email ?: null,
            'phone' => $this->clinic_phone ?: null,
        ]);

        session()->flash('message', 'Clinic profile saved.');
        $this->step = 2;
    }

    public function addDoctor(DoctorService $doctors): void
    {
        $this->validate([
            'doctor_name' => 'required|string|max:120',
            'doctor_email' => 'required|email|max:150',
            'doctor_password' => 'required|string|min:8',
            'doctor_specialization' => 'nullable|string|max:120',
        ]);

        $doctors->onboard([
            'name' => $this->doctor_name,
            'email' => $this->doctor_email,
            'password' => $this->doctor_password,
            'specialization' => $this->doctor_specialization,
            'role' => 'DOCTOR',
        ]);

        session()->flash('message', "Dr. {$this->doctor_name} added.");
        $this->step = 3;
    }

    public function addService(TreatmentCatalogService $catalog): void
    {
        $this->validate([
            'service_name' => 'required|string|max:150',
            'service_category' => 'nullable|string|max:80',
            'service_duration_minutes' => 'nullable|integer|min:1',
            'service_price_rupees' => 'nullable|integer|min:0',
        ]);

        $catalog->createService([
            'name' => $this->service_name,
            'category' => $this->service_category,
            'duration_minutes' => $this->service_duration_minutes,
            'price_cents' => $this->service_price_rupees !== null ? $this->service_price_rupees * 100 : null,
            'medicine_system' => auth()->user()->tenant?->system,
        ]);

        session()->flash('message', "Service {$this->service_name} added.");
        $this->step = 4;
    }

    public function addWard(IpdConfigurationService $ipd): void
    {
        $this->validate([
            'ward_name' => 'required|string|max:120',
            'ward_type' => 'required|in:GENERAL,PRIVATE,ICU,SEMI_PRIVATE,SPECIAL',
        ]);

        $ipd->createWard(['name' => $this->ward_name, 'type' => $this->ward_type]);

        session()->flash('message', "Ward {$this->ward_name} added.");
        $this->finish();
    }

    public function skipStep(): void
    {
        $this->step = min(5, $this->step + 1);
    }

    public function finish(): void
    {
        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.onboarding.clinic-setup-wizard')
            ->layout('components.layouts.app');
    }
}
