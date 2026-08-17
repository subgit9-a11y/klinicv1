<x-layouts.app sidebar :title="__('klinic360.nav.prescriptions')">
    <x-ui.page-header title="Prescriptions" />

    @if(session('status'))
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Patient</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Prescriber</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Issued</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Items</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($prescriptions as $prescription)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $prescription->id }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $prescription->patient?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $prescription->prescriber?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $prescription->issued_at?->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $prescription->items->count() }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded text-xs font-medium {{ $prescription->status === 'ACTIVE' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $prescription->status }}</span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-3">
                            <a href="{{ route('prescriptions.show', $prescription) }}" class="text-xs font-medium text-brand-600 hover:text-brand-800">View</a>
                            <a href="{{ route('prescriptions.pdf', $prescription) }}" class="text-xs font-medium text-gray-500 hover:text-gray-700">PDF</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">No prescriptions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $prescriptions->links() }}
</x-layouts.app>
