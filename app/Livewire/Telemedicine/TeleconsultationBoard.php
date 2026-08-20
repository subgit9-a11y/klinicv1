<?php

declare(strict_types=1);

namespace App\Livewire\Telemedicine;

use App\Models\Patient;
use App\Models\Teleconsultation;
use App\Models\User;
use App\Services\Telemedicine\TeleconsultationService;
use Livewire\Component;

/**
 * Clinic-side teleconsultation operations: schedule, start, end, cancel,
 * mark no-show. Backed by TeleconsultationService.
 */
class TeleconsultationBoard extends Component
{
    public string $statusFilter = '';

    public bool $showForm = false;

    // Schedule form
    public ?int $patient_id = null;

    public ?int $user_id = null;

    public ?string $scheduled_at = null;

    public ?int $duration_minutes = null;

    public ?string $title = null;

    public string $patientSearch = '';

    public function schedule(TeleconsultationService $tele): void
    {
        $this->guard();

        $this->validate([
            'patient_id' => 'required|exists:patients,id',
            'user_id' => 'nullable|exists:users,id',
            'scheduled_at' => 'nullable|date',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'title' => 'nullable|string|max:120',
        ]);

        $tc = $tele->schedule([
            'patient_id' => $this->patient_id,
            'user_id' => $this->user_id,
            'scheduled_at' => $this->scheduled_at,
            'duration_minutes' => $this->duration_minutes,
            'title' => $this->title,
        ]);

        session()->flash('message', 'Teleconsultation scheduled.');
        $this->reset(['patient_id', 'user_id', 'scheduled_at', 'duration_minutes', 'title']);
        $this->showForm = false;
    }

    public function start(int $id, TeleconsultationService $tele): void
    {
        $this->guard();

        $tele->start($this->find($id));
        session()->flash('message', 'Consultation started.');
    }

    public function end(int $id, TeleconsultationService $tele): void
    {
        $this->guard();

        $tele->end($this->find($id));
        session()->flash('message', 'Consultation ended.');
    }

    public function cancel(int $id, TeleconsultationService $tele): void
    {
        $this->guard();

        $tele->cancel($this->find($id));
        session()->flash('message', 'Consultation cancelled.');
    }

    public function markNoShow(int $id, TeleconsultationService $tele): void
    {
        $this->guard();

        $tele->markNoShow($this->find($id));
        session()->flash('message', 'Marked no-show.');
    }

    private function find(int $id): Teleconsultation
    {
        return Teleconsultation::findOrFail($id);
    }

    private function guard(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasPermission(\App\Services\Auth\Permissions::APPOINTMENTS_CREATE), 403);
    }

    public function render()
    {
        $this->guard();

        $items = Teleconsultation::with(['patient:id,first_name,last_name', 'doctor:id,name'])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->limit(50)
            ->get();

        return view('livewire.telemedicine.teleconsultation-board', [
            'items' => $items,
            'patients' => $this->patientSearch !== ''
                ? Patient::where('first_name', 'like', "%{$this->patientSearch}%")->orWhere('last_name', 'like', "%{$this->patientSearch}%")->limit(10)->get(['id', 'first_name', 'last_name'])
                : collect(),
            'doctors' => User::role('DOCTOR')->get(['id', 'name']),
        ])->layout('components.layouts.app');
    }
}
