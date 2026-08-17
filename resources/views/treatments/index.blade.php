<x-layouts.app sidebar :title="__('klinic360.nav.treatments')">
    <x-ui.page-header title="Treatments & Therapy Board" />

    @if(session('status'))
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('treatments.index') }}" class="mb-4 flex items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Date</label>
            <input type="date" name="date" value="{{ $date->toDateString() }}" class="border border-gray-300 rounded-md px-3 py-2 text-sm" />
        </div>
        <button type="submit" class="bg-gray-100 border border-gray-300 text-sm font-medium px-4 py-2 rounded-md hover:bg-gray-200">Go</button>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Patient</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Service</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Therapist</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Time</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($bookings as $booking)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $booking->patient?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $booking->service?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $booking->therapist?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $booking->start_time }} – {{ $booking->end_time }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded text-xs font-medium {{ $booking->status === 'COMPLETED' ? 'bg-green-100 text-green-800' : ($booking->status === 'CANCELLED' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">{{ $booking->status }}</span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2">
                            @if($booking->status === 'BOOKED')
                                <form method="POST" action="{{ route('treatments.complete', $booking) }}" class="inline">
                                    @csrf
                                    <button class="text-xs font-medium text-green-600 hover:text-green-800">Complete</button>
                                </form>
                                <form method="POST" action="{{ route('treatments.cancel', $booking) }}" class="inline">
                                    @csrf
                                    <button class="text-xs font-medium text-red-600 hover:text-red-800">Cancel</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No treatments for {{ $date->format('d M Y') }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $bookings->links() }}
</x-layouts.app>
