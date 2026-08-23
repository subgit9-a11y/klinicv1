<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Services\Settings\ClinicSettingsService;
use Livewire\Component;

/**
 * Clinic-owner configuration screen: appointment types + durations and
 * the online-booking fee/duration (stored per-tenant in tenant_settings).
 */
class ClinicSettings extends Component
{
    /** @var array<int, array{key: string, label: string, duration_minutes: int|string}> */
    public array $types = [];

    /** @var int|string|null */
    public $online_fee_rupees = null;

    /** @var int|string|null */
    public $online_duration_minutes = null;

    public function mount(ClinicSettingsService $settings): void
    {
        $this->types = $settings->appointmentTypes();
        $this->online_fee_rupees = intdiv($settings->onlineBookingFeeCents(), 100);
        $this->online_duration_minutes = $settings->onlineBookingDurationMinutes();
    }

    public function addType(): void
    {
        $this->guard();

        $this->types[] = ['key' => '', 'label' => '', 'duration_minutes' => 30];
    }

    public function removeType(int $index): void
    {
        $this->guard();

        unset($this->types[$index]);
        $this->types = array_values($this->types);
    }

    public function saveTypes(ClinicSettingsService $settings): void
    {
        $this->guard();

        $this->validate([
            'types' => ['required', 'array', 'min:1'],
            'types.*.key' => ['required', 'string', 'regex:/^[A-Z0-9_]+$/', 'max:32', 'distinct'],
            'types.*.label' => ['required', 'string', 'max:60'],
            'types.*.duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
        ]);

        $settings->saveAppointmentTypes($this->types);

        session()->flash('message', 'Appointment types saved.');
    }

    public function saveOnlineBooking(ClinicSettingsService $settings): void
    {
        $this->guard();

        $this->validate([
            'online_fee_rupees' => ['required', 'integer', 'min:0'],
            'online_duration_minutes' => ['required', 'integer', 'min:5', 'max:240'],
        ]);

        $settings->saveOnlineBooking(((int) $this->online_fee_rupees) * 100, (int) $this->online_duration_minutes);

        session()->flash('message', 'Online booking settings saved.');
    }

    public function render()
    {
        $this->guard();

        return view('livewire.settings.clinic-settings');
    }

    private function guard(): void
    {
        abort_unless(auth()->check() && auth()->user()->isClinicOwner(), 403);
    }
}
