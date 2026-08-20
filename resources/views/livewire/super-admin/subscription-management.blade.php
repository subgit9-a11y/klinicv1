<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Subscriptions</h1>
            <p class="text-sm text-gray-600">SaaS plan lifecycle for every clinic — activate, replace, cancel.</p>
        </div>
        <button wire:click="$toggle('showForm')" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">
            {{ $showForm ? 'Close' : '+ Activate Plan' }}
        </button>
    </div>

    @if (session('message'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">{{ session('message') }}</div>
    @endif

    @if ($showForm)
        <div class="mb-6 bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h2 class="text-lg font-semibold mb-4">Activate / replace subscription</h2>
            <form wire:submit="activate" class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Clinic</label>
                    <select wire:model="tenant_id" class="w-full rounded border-gray-300 shadow-sm">
                        <option value="">Select clinic…</option>
                        @foreach ($tenants as $tenant)
                            <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                        @endforeach
                    </select>
                    @error('tenant_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Plan</label>
                    <select wire:model="plan_code" class="w-full rounded border-gray-300 shadow-sm">
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->code }}">{{ $plan->name }}</option>
                        @endforeach
                    </select>
                    @error('plan_code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="flex items-end">
                    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Activate</button>
                </div>
            </form>
        </div>
    @endif

    <div class="mb-4 flex gap-3">
        <select wire:model.live="statusFilter" class="rounded border-gray-300 shadow-sm">
            <option value="">All statuses</option>
            <option value="ACTIVE">Active</option>
            <option value="TRIAL">Trial</option>
            <option value="CANCELLED">Cancelled</option>
            <option value="EXPIRED">Expired</option>
        </select>
        <select wire:model.live="tenantFilter" class="rounded border-gray-300 shadow-sm">
            <option value="">All clinics</option>
            @foreach ($tenants as $tenant)
                <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Clinic</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plan</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($subscriptions as $subscription)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $subscription->tenant?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ $subscription->plan?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs font-medium rounded-full {{ $subscription->status === 'ACTIVE' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ $subscription->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">
                            {{ $subscription->starts_at?->format('d M Y') ?? '—' }} → {{ $subscription->ends_at?->format('d M Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            ₹{{ number_format(($subscription->plan?->price_cents ?? 0) / 100, 0) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($subscription->status === 'ACTIVE')
                                <div class="flex items-center justify-end gap-2">
                                    <input wire:model="cancel_reason" type="text" placeholder="Reason…" class="rounded border-gray-300 shadow-sm text-sm w-40">
                                    <button wire:click="cancel({{ $subscription->id }})" wire:confirm="Cancel this subscription?" class="text-red-600 hover:underline text-sm">Cancel</button>
                                </div>
                                @error('cancel_reason') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            @else
                                <span class="text-xs text-gray-400">{{ $subscription->cancel_reason ?? '—' }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No subscriptions found.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-200">{{ $subscriptions->links() }}</div>
    </div>
</div>
