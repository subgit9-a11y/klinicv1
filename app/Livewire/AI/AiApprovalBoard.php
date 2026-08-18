<?php

declare(strict_types=1);

namespace App\Livewire\AI;

use App\Models\AiRequest;
use App\Services\AI\AIManager;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * AI governance board: lists AI drafts awaiting practitioner review and lets
 * an authorised user approve / reject them inline. Reinforces the invariant
 * that AI output is draft-only until a human signs off.
 */
class AiApprovalBoard extends Component
{
    use WithPagination;

    public string $statusFilter = 'DRAFT';

    public string $rejectReason = '';

    public ?int $rejectingId = null;

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function approve(int $aiRequestId): void
    {
        $aiRequest = AiRequest::findOrFail($aiRequestId);
        $this->authorize('approve', $aiRequest);

        app(AIManager::class)->approve($aiRequest, (int) auth()->id());

        $this->dispatch('ai-request-updated');
        session()->flash('status', "AI draft #{$aiRequest->id} approved.");
    }

    public function confirmReject(int $aiRequestId): void
    {
        $aiRequest = AiRequest::findOrFail($aiRequestId);
        $this->authorize('reject', $aiRequest);

        $this->rejectingId = $aiRequestId;
        $this->rejectReason = '';
    }

    public function reject(): void
    {
        if ($this->rejectingId === null) {
            return;
        }

        $aiRequest = AiRequest::findOrFail($this->rejectingId);
        $this->authorize('reject', $aiRequest);

        app(AIManager::class)->reject($aiRequest, $this->rejectReason !== '' ? $this->rejectReason : null);

        $this->rejectingId = null;
        $this->rejectReason = '';
        $this->dispatch('ai-request-updated');
        session()->flash('status', "AI draft #{$aiRequest->id} rejected.");
    }

    public function cancelReject(): void
    {
        $this->rejectingId = null;
        $this->rejectReason = '';
    }

    public function render(): View
    {
        $this->authorize('viewAny', AiRequest::class);

        $query = AiRequest::query()->with('feature')->latest();
        if (in_array($this->statusFilter, ['DRAFT', 'APPROVED', 'REJECTED', 'ERROR', 'PENDING'], true)) {
            $query->where('output_status', $this->statusFilter);
        }

        return view('livewire.ai.approval-board', [
            'requests' => $query->paginate(15),
        ]);
    }
}
