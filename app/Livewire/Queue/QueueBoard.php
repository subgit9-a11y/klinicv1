<?php

declare(strict_types=1);

namespace App\Livewire\Queue;

use App\Models\AppointmentToken;
use App\Models\User;
use App\Services\Queue\QueueService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class QueueBoard extends Component
{
    public string $date = '';

    public function mount(?string $date = null): void
    {
        $this->date = $date ?? now()->format('Y-m-d');
    }

    public function callNext(int $doctorId, QueueService $service): void
    {
        $this->authorize('queue.manage');

        $doctor = User::find($doctorId);
        if ($doctor === null) {
            return;
        }

        $token = $service->callNext($doctor, $this->date, auth()->user());

        if ($token === null) {
            session()->flash('queue-message', __('klinic360.queue.no_waiting'));
        } else {
            $this->dispatch('token-called', tokenNumber: $token->token_number);
        }
    }

    public function startConsultation(int $tokenId, QueueService $service): void
    {
        $this->authorize('queue.manage');
        $token = AppointmentToken::findOrFail($tokenId);

        try {
            $service->startConsultation($token, auth()->user());
        } catch (ValidationException $e) {
            session()->flash('queue-error', collect($e->errors())->flatten()->first());
        }
    }

    public function complete(int $tokenId, QueueService $service): void
    {
        $this->authorize('queue.manage');
        $token = AppointmentToken::findOrFail($tokenId);

        try {
            $service->complete($token, auth()->user());
        } catch (ValidationException $e) {
            session()->flash('queue-error', collect($e->errors())->flatten()->first());
        }
    }

    public function skip(int $tokenId, QueueService $service): void
    {
        $this->authorize('queue.manage');
        $token = AppointmentToken::findOrFail($tokenId);

        try {
            $service->skip($token, auth()->user());
        } catch (ValidationException $e) {
            session()->flash('queue-error', collect($e->errors())->flatten()->first());
        }
    }

    public function recall(int $tokenId, QueueService $service): void
    {
        $this->authorize('queue.manage');
        $token = AppointmentToken::findOrFail($tokenId);

        try {
            $service->recall($token, auth()->user());
        } catch (ValidationException $e) {
            session()->flash('queue-error', collect($e->errors())->flatten()->first());
        }
    }

    public function render(QueueService $service)
    {
        $tenantId = app(TenantContext::class)->id();

        $doctors = $tenantId
            ? User::where('tenant_id', $tenantId)
                ->whereIn('role', ['DOCTOR', 'CLINIC_OWNER'])
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        $tokens = $service->forDate($this->date);

        // Group tokens by doctor id for display.
        $byDoctor = $tokens->groupBy('user_id');

        $stats = [];
        foreach ($doctors as $doctor) {
            $stats[$doctor->id] = $service->stats($doctor, $this->date);
        }

        return view('livewire.queue.queue-board', [
            'doctors' => $doctors,
            'tokens' => $tokens,
            'byDoctor' => $byDoctor,
            'stats' => $stats,
        ]);
    }
}
