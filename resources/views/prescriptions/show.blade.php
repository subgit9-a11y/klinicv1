<x-layouts.app sidebar :title="__('klinic360.nav.prescriptions')">
    <x-ui.page-header title="Prescription #{{ $prescription->id }}" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase">Patient</div>
            <div class="text-sm font-medium text-gray-900">{{ $prescription->patient?->name ?? '—' }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase">Prescriber</div>
            <div class="text-sm font-medium text-gray-900">{{ $prescription->prescriber?->name ?? '—' }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase">Issued</div>
            <div class="text-sm font-medium text-gray-900">{{ $prescription->issued_at?->format('d M Y, H:i') }}</div>
        </div>
    </div>

    @if($prescription->notes)
        <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="text-xs font-semibold text-gray-500 uppercase mb-2">Notes</div>
            <p class="text-sm text-gray-700">{{ $prescription->notes }}</p>
        </div>
    @endif

    <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 text-sm font-semibold text-gray-900">Items</div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Medicine</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Form</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Dose</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Frequency</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Duration</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($prescription->items as $item)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $item->medicine }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $item->form ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $item->dose ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $item->frequency ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $item->duration ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        <a href="{{ route('prescriptions.pdf', $prescription) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Download PDF</a>
    </div>
</x-layouts.app>
