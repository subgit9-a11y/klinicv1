<x-layouts.app sidebar :title="__('klinic360.nav.billing')">
    <x-ui.page-header title="Billing & Cash Register" />

    @if(session('status'))
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif

    <div class="mb-6 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-3">Create invoice</h3>
        <form method="POST" action="{{ route('billing.invoices.create') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Patient ID</label>
                <input type="number" name="patient_id" required class="border border-gray-300 rounded-md px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Source</label>
                <select name="source" class="border border-gray-300 rounded-md px-3 py-2 text-sm">
                    <option value="OPD">OPD</option>
                    <option value="IPD">IPD</option>
                    <option value="PHARMACY">Pharmacy</option>
                    <option value="LAB">Lab</option>
                </select>
            </div>
            <button type="submit" class="bg-brand-600 text-white text-sm font-medium px-4 py-2 rounded-md hover:bg-brand-700">Create</button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Invoice</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Patient</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Total</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Paid</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Due</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($invoices as $invoice)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $invoice->invoice_number }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $invoice->patient?->full_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">₹{{ number_format(($invoice->total_cents ?? 0) / 100, 2) }}</td>
                        <td class="px-4 py-3 text-sm text-green-600">₹{{ number_format(($invoice->amount_paid_cents ?? 0) / 100, 2) }}</td>
                        <td class="px-4 py-3 text-sm text-red-600">₹{{ number_format(($invoice->amount_due_cents ?? 0) / 100, 2) }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded text-xs font-medium {{ collect(['PAID','ISSUED'])->contains($invoice->status) ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">{{ $invoice->status }}</span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2">
                            @if($invoice->status === 'DRAFT')
                                <form method="POST" action="{{ route('billing.issue', $invoice) }}" class="inline">
                                    @csrf
                                    <button class="text-xs font-medium text-brand-600 hover:text-brand-800">Issue</button>
                                </form>
                            @endif
                            @if(in_array($invoice->status, ['ISSUED','PARTIALLY_PAID']))
                                <form method="POST" action="{{ route('billing.pay', $invoice) }}" class="inline">
                                    @csrf
                                    <input type="number" name="amount_cents" placeholder="₹ (cents)" class="w-24 border border-gray-300 rounded px-2 py-1 text-xs" required />
                                    <select name="method" class="border border-gray-300 rounded px-2 py-1 text-xs">
                                        <option value="CASH">Cash</option>
                                        <option value="UPI">UPI</option>
                                        <option value="CARD">Card</option>
                                        <option value="NETBANKING">Netbanking</option>
                                    </select>
                                    <button class="text-xs font-medium text-green-600 hover:text-green-800">Record</button>
                                </form>
                            @endif
                            <a href="{{ route('invoices.pdf', $invoice) }}" class="text-xs font-medium text-gray-500 hover:text-gray-700">PDF</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">No invoices yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $invoices->links() }}
</x-layouts.app>
