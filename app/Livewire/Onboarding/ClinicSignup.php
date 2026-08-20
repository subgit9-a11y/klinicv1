<?php

declare(strict_types=1);

namespace App\Livewire\Onboarding;

use App\Models\Plan;
use App\Models\User;
use App\Services\Tenancy\TenantAdminService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Public self-service clinic onboarding wizard:
 *   1. Clinic details → 2. Plan selection → 3. Owner account → create.
 * Creates the tenant (TRIAL, 14-day trial), the CLINIC_OWNER user and a
 * trial subscription atomically via TenantAdminService, then signs the
 * owner in and sends them to the guided setup wizard.
 */
class ClinicSignup extends Component
{
    public int $step = 1;

    // Step 1 — clinic
    public string $clinic_name = '';

    public string $system = 'AYURVEDA';

    public string $clinic_email = '';

    public string $clinic_phone = '';

    // Step 2 — plan
    public string $plan_code = 'SOLO_DOCTOR';

    // Step 3 — owner account
    public string $owner_name = '';

    public string $owner_email = '';

    public string $owner_password = '';

    public string $owner_password_confirmation = '';

    public function next(): void
    {
        $this->validateStep($this->step);
        $this->step = min(3, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function submit(TenantAdminService $admin): void
    {
        $this->validateStep(1);
        $this->validateStep(2);
        $this->validateStep(3);

        $tenant = $admin->createClinic([
            'name' => $this->clinic_name,
            'plan_code' => $this->plan_code,
            'system' => $this->system,
            'email' => $this->clinic_email ?: null,
            'phone' => $this->clinic_phone ?: null,
            'owner_name' => $this->owner_name,
            'owner_email' => $this->owner_email,
            'owner_password' => $this->owner_password,
        ]);

        $owner = User::where('tenant_id', $tenant->id)->where('email', $this->owner_email)->firstOrFail();

        Auth::login($owner);
        session()->regenerate();

        $this->redirect(route('onboarding.setup'), navigate: true);
    }

    private function validateStep(int $step): void
    {
        match ($step) {
            1 => $this->validate([
                'clinic_name' => 'required|string|max:120',
                'system' => 'required|in:AYURVEDA,SIDDHA,HOMEOPATHY,GENERAL',
                'clinic_email' => 'nullable|email|max:190',
                'clinic_phone' => 'nullable|string|max:20',
            ]),
            2 => $this->validate([
                'plan_code' => 'required|string|exists:plans,code',
            ]),
            3 => $this->validate([
                'owner_name' => 'required|string|max:120',
                'owner_email' => 'required|email|max:190|unique:users,email',
                'owner_password' => 'required|string|min:8|confirmed',
            ]),
        };
    }

    public function render()
    {
        return view('livewire.onboarding.clinic-signup', [
            'plans' => Plan::where('is_active', true)->orderBy('price_cents')->get(),
        ])->layout('components.layouts.guest');
    }
}
