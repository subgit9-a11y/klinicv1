<div>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">AI Governance — Drafts for Practitioner Review</h1>
            <div class="flex items-center gap-2">
                <label class="text-xs font-medium text-gray-500">Status</label>
                <select wire:model.live="statusFilter" class="rounded-md border-gray-300 text-sm">
                    <option value="DRAFT">Drafts</option>
                    <option value="APPROVED">Approved</option>
                    <option value="REJECTED">Rejected</option>
                    <option value="ERROR">Errored</option>
                    <option value="PENDING">Pending</option>
                </select>
            </div>
        </div>

        @if(session('status'))
            <div class="rounded-md bg-green-50 p-3 text-sm text-green-700 border border-green-200">{{ session('status') }}</div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Request</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Feature</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Output</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($requests as $req)
                        <tr wire:key="ai-{{ $req->id }}">
                            <td class="px-4 py-3 text-sm text-gray-900">#{{ $req->id }} · {{ $req->provider }}/{{ $req->model }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $req->feature?->key ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 max-w-md truncate">{{ $req->output ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $badgeClass = match ($req->output_status) {
                                        'APPROVED' => 'bg-green-100 text-green-800',
                                        'REJECTED' => 'bg-red-100 text-red-800',
                                        'ERROR' => 'bg-gray-100 text-gray-800',
                                        default => 'bg-yellow-100 text-yellow-800',
                                    };
                                @endphp
                                <span class="px-2 py-1 rounded text-xs font-medium {{ $badgeClass }}">{{ $req->output_status }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($req->output_status === 'DRAFT' && $rejectingId !== $req->id)
                                    <button wire:click="approve({{ $req->id }})" wire:loading.attr="disabled"
                                            class="text-xs font-medium text-green-600 hover:text-green-800">Approve</button>
                                    <button wire:click="confirmReject({{ $req->id }})"
                                            class="ml-2 text-xs font-medium text-red-600 hover:text-red-800">Reject</button>
                                @endif
                                @if($rejectingId === $req->id)
                                    <div class="flex flex-col gap-1 items-end">
                                        <input type="text" wire:model="rejectReason" placeholder="Reason (optional)"
                                               class="w-48 border border-gray-300 rounded px-2 py-1 text-xs" />
                                        <div class="flex gap-2">
                                            <button wire:click="reject" class="text-xs font-medium text-red-600 hover:text-red-800">Confirm reject</button>
                                            <button wire:click="cancelReject" class="text-xs font-medium text-gray-500 hover:text-gray-700">Cancel</button>
                                        </div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No AI drafts in this state.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $requests->links() }}
    </div>
</div>
