<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Cash Registers</h1>
        <p class="text-sm text-gray-600">Open drawers, ledger entries, closing counts and variances.</p>
    </div>

    @if (session('message'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">{{ session('message') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 p-3 bg-red-100 text-red-700 rounded">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 font-semibold text-sm">Open registers</div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead><tr>
                        <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Register</th>
                        <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Opened by</th>
                        <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Opening float</th>
                        <th class="px-4 py-2 text-right text-xs text-gray-500 uppercase">Ledger / Close</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($openRegisters as $register)
                            <tr class="{{ $selectedRegisterId === $register->id ? 'bg-brand-50' : '' }}">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $register->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $register->user?->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">₹{{ number_format($register->opening_balance_cents / 100, 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <button wire:click="select({{ $register->id }})" class="text-brand-600 hover:underline">Ledger</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">No open registers.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($selected)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 flex items-center justify-between">
                        <span class="font-semibold text-sm">Ledger — {{ $selected->name }}</span>
                        <span class="text-sm text-gray-600">Balance: ₹{{ number_format(($selectedBalance ?? 0) / 100, 2) }}</span>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead><tr>
                            <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Time</th>
                            <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Method</th>
                            <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Amount</th>
                            <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Description</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($entries as $entry)
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-500">{{ $entry->created_at->format('d M H:i') }}</td>
                                    <td class="px-4 py-2 text-sm font-medium {{ $entry->type === 'CREDIT' ? 'text-green-700' : 'text-red-700' }}">{{ $entry->type }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-700">{{ $entry->method }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-700">₹{{ number_format($entry->amount_cents / 100, 2) }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-500">{{ $entry->description }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No entries yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="px-4 py-4 border-t border-gray-100 grid grid-cols-2 gap-4">
                        <form wire:submit="postAdjustment({{ $selected->id }})" class="space-y-2">
                            <div class="text-xs font-semibold text-gray-500 uppercase">Adjustment</div>
                            <div class="flex gap-2">
                                <select wire:model="adjust_type" class="rounded border-gray-300 shadow-sm text-sm">
                                    <option value="CREDIT">Credit</option>
                                    <option value="DEBIT">Debit</option>
                                </select>
                                <input wire:model="adjust_amount_rupees" type="number" min="1" placeholder="Amount (₹)" class="rounded border-gray-300 shadow-sm text-sm">
                            </div>
                            <input wire:model="adjust_reason" type="text" placeholder="Reason" class="w-full rounded border-gray-300 shadow-sm text-sm">
                            @error('adjust_reason') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            <button type="submit" class="px-3 py-1 bg-gray-800 text-white rounded text-sm">Post adjustment</button>
                        </form>
                        <form wire:submit="close({{ $selected->id }})" class="space-y-2 text-right">
                            <div class="text-xs font-semibold text-gray-500 uppercase">Close register</div>
                            <input wire:model="actual_balance_rupees" type="number" min="0" placeholder="Counted cash (₹)" class="rounded border-gray-300 shadow-sm text-sm">
                            @error('actual_balance_rupees') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                            <button type="submit" wire:confirm="Close this register? This cannot be undone." class="px-3 py-1 bg-red-600 text-white rounded text-sm">Close</button>
                            <p class="text-xs text-gray-400 mt-1">Variance = counted − expected.</p>
                        </form>
                    </div>
                </div>
            @endif

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 font-semibold text-sm">Recent closed</div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead><tr>
                        <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Register</th>
                        <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Expected</th>
                        <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Counted</th>
                        <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">Variance</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($closedRegisters as $register)
                            <tr>
                                <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ $register->name }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700">₹{{ number_format($register->closing_balance_cents / 100, 2) }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700">{{ $register->actual_balance_cents !== null ? '₹'.number_format($register->actual_balance_cents / 100, 2) : '—' }}</td>
                                <td class="px-4 py-2 text-sm {{ $register->variance_cents !== null && $register->variance_cents !== 0 ? 'text-red-600 font-medium' : 'text-gray-700' }}">
                                    {{ $register->variance_cents !== null ? '₹'.number_format($register->variance_cents / 100, 2) : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">No closed registers.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-200">
                <h2 class="text-base font-semibold mb-3">Open register</h2>
                <form wire:submit="open" class="space-y-3">
                    <input wire:model="open_name" type="text" placeholder="Name (optional)" class="w-full rounded border-gray-300 shadow-sm text-sm">
                    <input wire:model="open_balance_rupees" type="number" min="0" placeholder="Opening float (₹)" class="w-full rounded border-gray-300 shadow-sm text-sm">
                    <button type="submit" class="w-full px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Open</button>
                </form>
                <p class="mt-3 text-xs text-gray-400">One open register per user; closing requires a counted balance (optional).</p>
            </div>
        </div>
    </div>
</div>
