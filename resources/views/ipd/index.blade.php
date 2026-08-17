<x-layouts.app sidebar :title="__('klinic360.nav.ipd')">
    <x-ui.page-header title="IPD — Inpatient Management" />

    @if(session('status'))
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="text-2xl font-bold text-gray-900">{{ $activeAdmissions->count() }}</div>
            <div class="text-xs text-gray-500">Active admissions</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="text-2xl font-bold text-green-600">{{ $availableBeds->count() }}</div>
            <div class="text-xs text-gray-500">Available beds</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="text-2xl font-bold text-gray-900">{{ $wards->count() }}</div>
            <div class="text-xs text-gray-500">Wards</div>
        </div>
    </div>

    <div class="mb-6 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-3">Admit a patient</h3>
        <form method="POST" action="{{ route('ipd.admit') }}">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Patient ID</label>
                    <input type="number" name="patient_id" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Bed (optional)</label>
                    <select name="ipd_bed_id" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                        <option value="">No bed (observation)</option>
                        @foreach($availableBeds as $bed)
                            <option value="{{ $bed->id }}">{{ $bed->bed_number }} · {{ $bed->room?->name ?? '—' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Admission type</label>
                    <select name="admission_type" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                        <option value="ROUTINE">Routine</option>
                        <option value="EMERGENCY">Emergency</option>
                        <option value="TRANSFER">Transfer</option>
                    </select>
                </div>
            </div>
            <div class="mt-3">
                <label class="block text-xs font-medium text-gray-500 mb-1">Admission reason</label>
                <input type="text" name="admission_reason" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" />
            </div>
            <button type="submit" class="mt-3 bg-brand-600 text-white text-sm font-medium px-4 py-2 rounded-md hover:bg-brand-700">Admit</button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="px-4 py-3 border-b border-gray-200 text-sm font-semibold text-gray-900">Active admissions</div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">IPD #</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Patient</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Admitted</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($activeAdmissions as $admission)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $admission->ipd_number }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">Patient #{{ $admission->patient_id }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $admission->admitted_at?->format('d M Y, H:i') }}</td>
                        <td class="px-4 py-3 text-right space-x-3">
                            <form method="POST" action="{{ route('ipd.transfer', $admission) }}" class="inline">
                                @csrf
                                <select name="ipd_bed_id" class="border border-gray-300 rounded px-2 py-1 text-xs" onchange="this.form.submit()">
                                    <option value="">Transfer bed…</option>
                                    @foreach($availableBeds as $bed)
                                        <option value="{{ $bed->id }}">{{ $bed->bed_number }}</option>
                                    @endforeach
                                </select>
                            </form>
                            <form method="POST" action="{{ route('ipd.discharge', $admission) }}" class="inline">
                                @csrf
                                <button class="text-xs font-medium text-red-600 hover:text-red-800">Discharge</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">No active admissions.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 text-sm font-semibold text-gray-900">Wards & beds</div>
        @foreach($wards as $ward)
            <div class="px-4 py-3 border-b border-gray-100 last:border-b-0">
                <div class="text-sm font-medium text-gray-900 mb-2">{{ $ward->name }} <span class="text-xs text-gray-400">({{ $ward->type }})</span></div>
                <div class="flex flex-wrap gap-2">
                    @foreach($ward->rooms as $room)
                        @foreach($room->beds as $bed)
                            <span class="px-2 py-1 rounded text-xs font-medium {{ $bed->status === 'AVAILABLE' ? 'bg-green-100 text-green-800' : ($bed->status === 'OCCUPIED' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">{{ $bed->bed_number }} · {{ $bed->status }}</span>
                        @endforeach
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.app>
