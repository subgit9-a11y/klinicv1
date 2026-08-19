<?php

declare(strict_types=1);

namespace App\Livewire\Appointments;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Services\Appointments\AppointmentService;
use App\Services\Tenancy\TenantContext;
use App\Support\Sql;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class AppointmentBoard extends Component
{
    use WithPagination;

    public string $date = '';

    public string $search = '';

    public ?int $patientId = null;

    public ?int $doctorId = null;

    public string $type = 'IN_PERSON';

    public string $startTime = '';

    public int $durationMinutes = 15;

    public string $reason = '';

    public bool $showBookingForm = false;

    public function mount(?string $date = null): void
    {
        $this->date = $date ?? now()->format('Y-m-d');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function toggleBookingForm(): void
    {
        $this->showBookingForm = ! $this->showBookingForm;
        if ($this->showBookingForm) {
            $this->resetErrorBag();
        }
    }

    public function book(AppointmentService $service): void
    {
        $validated = $this->validate([
            'patientId' => ['required', 'integer', 'exists:patients,id'],
            'doctorId' => ['nullable', 'integer', 'exists:users,id'],
            'type' => ['required', 'in:WALK_IN,IN_PERSON,ONLINE,FOLLOW_UP,TREATMENT,IPD_REVIEW'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'startTime' => ['required', 'date_format:H:i'],
            'durationMinutes' => ['required', 'integer', 'between:5,480'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $appointment = $service->book([
                'patient_id' => $validated['patientId'],
                'user_id' => $validated['doctorId'],
                'type' => $validated['type'],
                'appointment_date' => $validated['date'],
                'start_time' => $validated['startTime'],
                'duration_minutes' => $validated['durationMinutes'],
                'reason' => $validated['reason'] ?? null,
            ], auth()->user());

            $this->reset(['patientId', 'doctorId', 'startTime', 'reason', 'durationMinutes', 'showBookingForm']);
            $this->durationMinutes = 15;
            $this->type = 'IN_PERSON';

            $this->dispatch('appointment-booked', appointmentId: $appointment->id);

            session()->flash('appointment-message', __('klinic360.appointments.booked'));
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
        }
    }

    public function cancelAppointment(int $appointmentId, AppointmentService $service): void
    {
        $appointment = Appointment::findOrFail($appointmentId);
        $this->authorize('cancel', $appointment);

        try {
            $service->cancel($appointment, auth()->user(), __('klinic360.appointments.cancelled_by', ['user' => auth()->user()->name]));
        } catch (ValidationException $e) {
            $this->addError('appointment', $e->getMessage());
        }
    }

    public function render(AppointmentService $service)
    {
        $tenantId = app(TenantContext::class)->id();

        $dayStart = now()->parse($this->date)->startOfDay();
        $dayEnd = now()->parse($this->date)->endOfDay();

        $appointments = Appointment::query()
            ->whereBetween('appointment_date', [$dayStart, $dayEnd])
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->whereHas('patient', function ($pq) use ($term) {
                    $pq->where('k360_uid', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhereRaw('lower('.Sql::personNameConcat().') like ?', [strtolower($term)]);
                });
            })
            ->with(['patient:id,k360_uid,first_name,last_name,phone', 'doctor:id,name'])
            ->orderBy('start_time')
            ->paginate(20);

        $doctors = $tenantId
            ? User::where('tenant_id', $tenantId)
                ->whereIn('role', ['DOCTOR', 'CLINIC_OWNER'])
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        $patients = collect();
        if ($this->search !== '' && $tenantId) {
            $patients = Patient::where('tenant_id', $tenantId)
                ->where(function ($q) {
                    $q->where('k360_uid', 'like', $this->search.'%')
                        ->orWhere('phone', 'like', '%'.$this->search.'%')
                        ->orWhereRaw('lower('.Sql::personNameConcat().') like ?', ['%'.strtolower($this->search).'%']);
                })
                ->limit(10)
                ->get(['id', 'k360_uid', 'first_name', 'last_name', 'phone']);
        }

        return view('livewire.appointments.appointment-board', [
            'appointments' => $appointments,
            'doctors' => $doctors,
            'patients' => $patients,
        ]);
    }
}
