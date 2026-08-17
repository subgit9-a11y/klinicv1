<x-layouts.app sidebar :title="__('klinic360.nav.payments')">
    <x-ui.page-header title="Payments" />

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="text-2xl font-bold text-green-600">₹{{ number_format($totals->collected_cents / 100, 2) }}</div>
            <div class="text-xs text-gray-500">Total collected (SUCCESS)</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="text-2xl font-bold text-gray-900">{{ $totals->count }}</div>
            <div class="text-xs text-gray-500">Successful payments</div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Payment #</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Invoice</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Patient</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Method</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Collected by</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Paid at</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($payments as $payment)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $payment->payment_number }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $payment->invoice?->invoice_number ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $payment->patient?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $payment->method }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-green-600">₹{{ number_format($payment->amount_cents / 100, 2) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $payment->collectedBy?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $payment->paid_at?->format('d M Y, H:i') }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded text-xs font-medium {{ $payment->status === 'SUCCESS' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">{{ $payment->status }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">No payments yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $payments->links() }}
</x-layouts.app>
