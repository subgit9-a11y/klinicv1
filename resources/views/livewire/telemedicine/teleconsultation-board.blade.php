<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Teleconsultations</h1>
            <p class="text-sm text-gray-600">Schedule, start, end or cancel video consultations.</p>
        </div>
        <button wire:click="$toggle('showForm')" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">
            {{ $showForm ? 'Close' : '+ Schedule' }}
        </button>
    </div>

    @if (session('message'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">{{ session('message') }}</div>
    @endif

    @if ($showForm)
        <div class="mb-6 bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h2 class="text-lg font-semibold mb-4">Schedule teleconsultation</h2>
            <form wire:submit="schedule" class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Patient</label>
                    <input wire:model.live.debounce.300ms="patientSearch" type="text" placeholder="Search patient…" class="w-full rounded border-gray-300 shadow-sm">
                    @if ($patients->isNotEmpty())
                        <select wire:model="patient_id" class="w-full mt-1 rounded border-gray-300 shadow-sm">
                            <option value="">Select…</option>
                            @foreach ($patients as $patient)
                                <option value="{{ $patient->id }}">{{ $patient->first_name }} {{ $patient->last_name }}</option>
                            @endforeach
                        </select>
                    @endif
                    @error('patient_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Doctor</label>
                    <select wire:model="user_id" class="w-full rounded border-gray-300 shadow-sm">
                        <option value="">Any doctor</option>
                        @foreach ($doctors as $doctor)
                            <option value="{{ $doctor->id }}">{{ $doctor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Scheduled time (optional)</label>
                    <input wire:model="scheduled_at" type="datetime-local" class="w-full rounded border-gray-300 shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Duration (minutes)</label>
                    <input wire:model="duration_minutes" type="number" min="5" class="w-full rounded border-gray-300 shadow-sm">
                    @error('duration_minutes') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                    <input wire:model="title" type="text" class="w-full rounded border-gray-300 shadow-sm">
                </div>
                <div class="col-span-2">
                    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Schedule</button>
                </div>
            </form>
        </div>
    @endif

    <select wire:model.live="statusFilter" class="mb-4 rounded border-gray-300 shadow-sm">
        <option value="">All statuses</option>
        <option value="SCHEDULED">Scheduled</option>
        <option value="IN_PROGRESS">In progress</option>
        <option value="COMPLETED">Completed</option>
        <option value="CANCELLED">Cancelled</option>
        <option value="NO_SHOW">No show</option>
    </select>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patient</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Doctor</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Meeting</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($items as $item)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $item->patient?->full_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ $item->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs font-medium rounded-full {{ $item->status === 'COMPLETED' ? 'bg-green-100 text-green-700' : ($item->status === 'IN_PROGRESS' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600') }}">
                                {{ $item->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @if ($item->meeting_url)
                                <a href="{{ $item->meeting_url }}" target="_blank" class="text-brand-600 hover:underline">Join</a>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right space-x-1 text-sm">
                            @if ($item->status === 'SCHEDULED')
                                <button wire:click="start({{ $item->id }})" class="text-green-600 hover:underline">Start</button>
                                <button wire:click="cancel({{ $item->id }})" wire:confirm="Cancel this teleconsultation?" class="text-red-600 hover:underline">Cancel</button>
                                <button wire:click="markNoShow({{ $item->id }})" class="text-gray-500 hover:underline">No-show</button>
                            @elseif ($item->status === 'IN_PROGRESS')
                                <button wire:click="end({{ $item->id }})" class="text-brand-600 hover:underline">End</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No teleconsultations.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
