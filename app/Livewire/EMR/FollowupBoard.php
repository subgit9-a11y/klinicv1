<?php

declare(strict_types=1);

namespace App\Livewire\EMR;

use App\Models\Followup;
use App\Models\Patient;
use App\Services\Auth\Permissions;
use App\Services\EMR\FollowupService;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Follow-up tracker: buckets (today / upcoming / missed / completed / all),
 * schedule form, complete / cancel / reschedule actions.
 * Backed by FollowupService; gated by consultations.* permissions.
 */
class FollowupBoard extends Component
{
    public string $bucket = 'pending';

    public bool $showForm = false;

    // Schedule form
    public ?int $patient_id = null;

    public ?string $due_date = null;

    public ?string $instructions = null;

    public string $patientSearch = '';

    // Reschedule state
    public ?int $reschedulingId = null;

    public ?string $rescheduleDate = null;

    public function schedule(FollowupService $followups): void
    {
        abort_unless(auth()->user()->hasPermission(Permissions::CONSULTATIONS_CREATE), 403);

        $this->validate([
            'patient_id' => 'required|exists:patients,id',
            'due_date' => 'required|date|after_or_equal:today',
            'instructions' => 'nullable|string|max:1000',
        ]);

        $followups->schedule([
            'patient_id' => $this->patient_id,
            'due_date' => $this->due_date,
            'instructions' => $this->instructions,
        ]);

        session()->flash('message', 'Follow-up scheduled.');
        $this->reset(['patient_id', 'due_date', 'instructions', 'patientSearch']);
        $this->showForm = false;
    }

    public function complete(int $id, FollowupService $followups): void
    {
        $this->updateStatus($id, 'COMPLETED', $followups, 'Follow-up completed.');
    }

    public function cancel(int $id, FollowupService $followups): void
    {
        $this->updateStatus($id, 'CANCELLED', $followups, 'Follow-up cancelled.');
    }

    public function startReschedule(int $id): void
    {
        $this->reschedulingId = $id;
        $this->rescheduleDate = null;
    }

    public function reschedule(): void
    {
        abort_unless(auth()->user()->hasPermission(Permissions::CONSULTATIONS_EDIT), 403);

        $this->validate(['rescheduleDate' => 'required|date|after_or_equal:today']);

        // findOrFail is tenant-scoped via the BelongsToTenant global scope.
        Followup::findOrFail($this->reschedulingId)->update(['due_date' => $this->rescheduleDate]);

        session()->flash('message', 'Follow-up rescheduled.');
        $this->reset(['reschedulingId', 'rescheduleDate']);
    }

    private function updateStatus(int $id, string $status, FollowupService $followups, string $flash): void
    {
        abort_unless(auth()->user()->hasPermission(Permissions::CONSULTATIONS_EDIT), 403);

        $followup = Followup::findOrFail($id);
        $followups->updateStatus($followup, $status);
        session()->flash('message', $flash);
    }

    public function render()
    {
        abort_unless(auth()->check() && auth()->user()->hasPermission(Permissions::CONSULTATIONS_VIEW), 403);

        $today = Carbon::today();

        $base = Followup::with(['patient:id,first_name,last_name,k360_uid'])
            ->orderBy('due_date');

        $items = match ($this->bucket) {
            'today' => (clone $base)->where('status', 'PENDING')->whereDate('due_date', $today)->get(),
            'upcoming' => (clone $base)->where('status', 'PENDING')->whereDate('due_date', '>', $today)->get(),
            'missed' => (clone $base)->where('status', 'PENDING')->whereDate('due_date', '<', $today)->get(),
            'completed' => (clone $base)->where('status', 'COMPLETED')->latest('updated_at')->limit(50)->get(),
            default => (clone $base)->where('status', 'PENDING')->orderBy('due_date')->limit(100)->get(),
        };

        $counts = [
            'today' => Followup::where('status', 'PENDING')->whereDate('due_date', $today)->count(),
            'upcoming' => Followup::where('status', 'PENDING')->whereDate('due_date', '>', $today)->count(),
            'missed' => Followup::where('status', 'PENDING')->whereDate('due_date', '<', $today)->count(),
        ];

        return view('livewire.emr.followup-board', [
            'items' => $items,
            'counts' => $counts,
            'patients' => $this->patientSearch !== ''
                ? Patient::where('first_name', 'like', "%{$this->patientSearch}%")
                    ->orWhere('last_name', 'like', "%{$this->patientSearch}%")
                    ->orWhere('k360_uid', 'like', "%{$this->patientSearch}%")
                    ->limit(10)->get(['id', 'first_name', 'last_name', 'k360_uid'])
                : collect(),
        ])->layout('components.layouts.app');
    }
}
