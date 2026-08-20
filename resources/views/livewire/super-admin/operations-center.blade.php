<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Operations</h1>
        <p class="text-sm text-gray-600">Cross-clinic operational visibility — read only.</p>
    </div>

    <div class="mb-4 flex flex-wrap gap-2 border-b border-gray-200">
        @foreach (['audit' => 'Audit trail', 'webhooks' => 'Webhooks', 'notifications' => 'Notifications', 'orders' => 'Payment orders'] as $tab => $label)
            <button wire:click="setTab('{{ $tab }}')"
                class="px-4 py-2 text-sm font-medium rounded-t-lg {{ $activeTab === $tab ? 'bg-brand-600 text-white' : 'text-gray-600 hover:text-gray-900' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="mb-4 flex gap-3">
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search…" class="flex-1 rounded border-gray-300 shadow-sm">
        <select wire:model.live="tenantFilter" class="rounded border-gray-300 shadow-sm">
            <option value="">All clinics</option>
            @foreach ($tenants as $tenant)
                <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        @if ($activeTab === 'audit')
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tenant</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $row->created_at->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $row->action }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $row->category }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">#{{ $row->user_id ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">#{{ $row->tenant_id ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No audit entries.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @elseif ($activeTab === 'webhooks')
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Event</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Processed</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $row->created_at->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $row->event_type }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $row->gateway_order_id }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs font-medium rounded-full {{ $row->processed ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                    {{ $row->processed ? 'Processed' : 'Pending' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No webhooks received.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @elseif ($activeTab === 'notifications')
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Channel</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Recipient</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Attempts</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $row->created_at->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $row->channel }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $row->recipient }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs font-medium rounded-full {{ $row->status === 'SENT' || $row->status === 'DELIVERED' ? 'bg-green-100 text-green-700' : ($row->status === 'FAILED' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700') }}">
                                    {{ $row->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $row->attempts }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No deliveries.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @else
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gateway order</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $row->created_at->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $row->internal_order_id }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $row->gateway_order_id }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">₹{{ number_format($row->amount_cents / 100, 2) }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs font-medium rounded-full {{ $row->status === 'PAID' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $row->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No payment orders.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif

        <div class="px-4 py-3 border-t border-gray-200">{{ $rows->links() }}</div>
    </div>
</div>
