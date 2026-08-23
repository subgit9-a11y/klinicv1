<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Models\User;
use App\Services\Auth\Permissions;
use App\Services\Staff\DoctorService;
use Livewire\Component;

/**
 * Clinic-side doctor management: directory, onboarding, profile edit,
 * weekly availability (with breaks) and leave. Backed by DoctorService —
 * the same service the API uses.
 */
class DoctorDirectory extends Component
{
    public ?int $selectedId = null;

    public string $panel = 'profile'; // profile | schedule | leave

    public bool $showOnboardForm = false;

    // Onboard form
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public ?string $specialization = null;

    public ?string $registration_number = null;

    public ?int $consultation_fee_rupees = null;

    // Profile edit
    public ?int $edit_consultation_fee_rupees = null;

    public string $edit_specialization = '';


    /** @var int|string|null */
    public $edit_consultation_duration_minutes = null;

    /** @var int|string|null */
    public $edit_max_daily_appointments = null;

    public string $edit_registration_number = '';

    // Schedule form (one row per day)
    /** @var array<string, array{enabled: bool, start: string, end: string, break_start: ?string, break_end: ?string}> */
    public array $schedule = [];

    // Leave form
    public string $leave_start = '';

    public string $leave_end = '';

    public string $leave_type = 'LEAVE';

    public ?string $leave_reason = null;

    public function mount(): void
    {
        $this->guard();
        $this->resetSchedule();
    }

    public function onboard(DoctorService $doctors): void
    {
        $this->guard();

        $this->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:150',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
            'specialization' => 'nullable|string|max:120',
            'registration_number' => 'nullable|string|max:80',
            'consultation_fee_rupees' => 'nullable|integer|min:0',
        ]);

        $doctor = $doctors->onboard([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'password' => $this->password,
            'specialization' => $this->specialization,
            'registration_number' => $this->registration_number,
            'consultation_fee_cents' => $this->consultation_fee_rupees !== null ? $this->consultation_fee_rupees * 100 : null,
            'role' => 'DOCTOR',
        ]);

        session()->flash('message', "Dr. {$doctor->name} onboarded.");
        $this->reset(['name', 'email', 'phone', 'password', 'specialization', 'registration_number', 'consultation_fee_rupees']);
        $this->showOnboardForm = false;
    }

    public function select(int $doctorId, string $panel = 'profile'): void
    {
        $this->guard();

        $this->selectedId = $doctorId;
        $this->panel = $panel;

        $doctor = $this->doctors()->findOrFail($doctorId);
        $this->edit_specialization = (string) $doctor->specialization;
        $this->edit_registration_number = (string) $doctor->registration_number;
        $this->edit_consultation_fee_rupees = $doctor->consultation_fee_cents !== null
            ? intdiv($doctor->consultation_fee_cents, 100) : null;

        $this->edit_consultation_duration_minutes = $doctor->consultation_duration_minutes;
        $this->edit_max_daily_appointments = $doctor->max_daily_appointments;

        $this->loadSchedule($doctor->availability()->orderBy('day_of_week')->get());
    }

    public function saveProfile(DoctorService $doctors): void
    {
        $this->guard();

        $this->validate([
            'edit_specialization' => 'nullable|string|max:120',
            'edit_registration_number' => 'nullable|string|max:80',
            'edit_consultation_fee_rupees' => 'nullable|integer|min:0',
            'edit_consultation_duration_minutes' => 'nullable|integer|min:5|max:240',
            'edit_max_daily_appointments' => 'nullable|integer|min:1|max:200',
        ]);

        $doctor = $this->doctors()->findOrFail($this->selectedId);
        $doctors->updateProfile($doctor, [
            'specialization' => $this->edit_specialization ?: null,
            'registration_number' => $this->edit_registration_number ?: null,
            'consultation_fee_cents' => $this->edit_consultation_fee_rupees !== null ? $this->edit_consultation_fee_rupees * 100 : null,
            'consultation_duration_minutes' => $this->edit_consultation_duration_minutes,
            'max_daily_appointments' => $this->edit_max_daily_appointments,
        ]);

        session()->flash('message', 'Profile updated.');
    }

    public function saveSchedule(DoctorService $doctors): void
    {
        $this->guard();

        $doctor = $this->doctors()->findOrFail($this->selectedId);

        $slots = [];
        foreach ($this->schedule as $day => $row) {
            if (! ($row['enabled'] ?? false)) {
                continue;
            }
            $this->validate([
                "schedule.{$day}.start" => 'required|date_format:H:i',
                "schedule.{$day}.end" => "required|date_format:H:i|after:schedule.{$day}.start",
                "schedule.{$day}.break_start" => 'nullable|date_format:H:i',
                "schedule.{$day}.break_end" => "nullable|date_format:H:i|after:schedule.{$day}.break_start",
            ]);
            $slots[] = [
                'day_of_week' => $day,
                'start_time' => $row['start'],
                'end_time' => $row['end'],
                'break_start_time' => $row['break_start'] ?: null,
                'break_end_time' => $row['break_end'] ?: null,
            ];
        }

        $doctors->setAvailability($doctor, $slots);

        session()->flash('message', 'Schedule saved.');
    }

    public function addLeave(DoctorService $doctors): void
    {
        $this->guard();

        $this->validate([
            'leave_start' => 'required|date',
            'leave_end' => 'required|date|after_or_equal:leave_start',
            'leave_type' => 'required|in:LEAVE,HOLIDAY,EMERGENCY,OTHER',
            'leave_reason' => 'nullable|string|max:255',
        ]);

        $doctor = $this->doctors()->findOrFail($this->selectedId);
        $doctors->setLeave($doctor, [
            'start_date' => $this->leave_start,
            'end_date' => $this->leave_end,
            'type' => $this->leave_type,
            'reason' => $this->leave_reason,
            'is_approved' => true,
        ]);

        session()->flash('message', 'Leave recorded — slots on those days are no longer offered.');
        $this->reset(['leave_start', 'leave_end', 'leave_reason']);
    }

    public function toggleActive(int $doctorId, DoctorService $doctors): void
    {
        $this->guard();

        $doctor = $this->doctors()->findOrFail($doctorId);
        $doctors->updateProfile($doctor, ['is_active' => ! $doctor->is_active]);

        session()->flash('message', $doctor->fresh()->is_active ? 'Doctor enabled.' : 'Doctor disabled.');
    }

    private function resetSchedule(): void
    {
        $this->schedule = collect(DoctorService::DAYS)
            ->mapWithKeys(fn ($d) => [$d => ['enabled' => false, 'start' => '09:00', 'end' => '17:00', 'break_start' => null, 'break_end' => null]])
            ->all();
    }

    private function loadSchedule($availability): void
    {
        $this->resetSchedule();
        foreach ($availability as $slot) {
            $this->schedule[$slot->day_of_week] = [
                'enabled' => true,
                'start' => substr((string) $slot->start_time, 0, 5),
                'end' => substr((string) $slot->end_time, 0, 5),
                'break_start' => $slot->break_start_time ? substr((string) $slot->break_start_time, 0, 5) : null,
                'break_end' => $slot->break_end_time ? substr((string) $slot->break_end_time, 0, 5) : null,
            ];
        }
    }

    /**
     * User has no tenant global scope, so the filter is explicit — never
     * show another clinic's doctors.
     */
    private function doctors()
    {
        return User::role('DOCTOR')->where('tenant_id', auth()->user()->tenant_id);
    }

    private function guard(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasPermission(Permissions::STAFF_MANAGE), 403);
    }

    public function render()
    {
        $this->guard();

        return view('livewire.staff.doctor-directory', [
            'doctors' => $this->doctors()->withCount('leaves')->orderBy('name')->get(),
            'selected' => $this->selectedId !== null ? User::find($this->selectedId) : null,
            'days' => DoctorService::DAYS,
        ])->layout('components.layouts.app');
    }
}
