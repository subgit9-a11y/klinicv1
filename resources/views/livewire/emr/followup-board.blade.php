<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Follow-ups</h1>
            <p class="text-sm text-gray-600">Track patient follow-up visits: due today, upcoming, missed.</p>
        </div>
        @can(\App\Services\Auth\Permissions::CONSULTATIONS_CREATE)
            <button wire:click="$toggle('showForm')" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">
                {{ $showForm ? 'Close' : '+ Schedule follow-up' }}
            </button>
        @endcan
    </div>

    @if (session('message'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">{{ session('message') }}</div>
    @endif

    @if ($showForm)
        <div class="mb-6 bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h2 class="text-lg font-semibold mb-4">Schedule follow-up</h2>
            <form wire:submit="schedule" class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Patient</label>
                    <input wire:model.live.debounce.300ms="patientSearch" type="text" placeholder="Search by name or UID…" class="w-full rounded border-gray-300 shadow-sm">
                    @if ($patients->isNotEmpty())
                        <select wire:model="patient_id" class="w-full mt-1 rounded border-gray-300 shadow-sm">
                            <option value="">Select…</option>
                            @foreach ($patients as $patient)
                                <option value="{{ $patient->id }}">{{ $patient->first_name }} {{ $patient->last_name }} ({{ $patient->k360_uid }})</option>
                            @endforeach
                        </select>
                    @endif
                    @error('patient_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Due date</label>
                    <input wire:model="due_date" type="date" class="w-full rounded border-gray-300 shadow-sm">
                    @error('due_date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Instructions</label>
                    <textarea wire:model="instructions" rows="2" class="w-full rounded border-gray-300 shadow-sm" placeholder="e.g. Review blood sugar report, adjust dosage…"></textarea>
                    @error('instructions') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="col-span-2">
                    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Schedule</button>
                </div>
            </form>
        </div>
    @endif

    <div class="mb-4 flex gap-2">
        @foreach ([
            'pending' => 'All pending',
            'today' => 'Due today ('.$counts['today'].')',
            'upcoming' => 'Upcoming ('.$counts['upcoming'].')',
            'missed' => 'Missed ('.$counts['missed'].')',
            'completed' => 'Completed',
        ] as $key => $label)
            <button wire:click="$set('bucket', '{{ $key }}')"
                    class="px-3 py-1.5 rounded-full text-sm font-medium {{ $bucket === $key ? 'bg-brand-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patient</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Instructions</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($items as $followup)
                    @php
                        $isMissed = $followup->status === 'PENDING' && $followup->due_date->isPast() && ! $followup->due_date->isToday();
                        $isToday = $followup->due_date->isToday();
                    @endphp
                    <tr class="{{ $isMissed ? 'bg-red-50' : ($isToday ? 'bg-amber-50' : '') }}">
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $followup->due_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-sm">
                            <a href="{{ route('patients.show', $followup->patient_id) }}" class="text-brand-700 hover:underline font-medium">
                                {{ $followup->patient->first_name }} {{ $followup->patient->last_name }}
                            </a>
                            <span class="block text-xs text-gray-500">{{ $followup->patient->k360_uid }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $followup->instructions ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $followup->status === 'COMPLETED' ? 'bg-green-100 text-green-800' : ($followup->status === 'CANCELLED' ? 'bg-gray-200 text-gray-600' : ($isMissed ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800')) }}">
                                {{ $isMissed ? 'MISSED' : $followup->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-right space-x-2">
                            @if ($followup->status === 'PENDING')
                                @can(\App\Services\Auth\Permissions::CONSULTATIONS_EDIT)
                                    <button wire:click="complete({{ $followup->id }})" class="text-green-700 hover:underline text-xs font-medium">Complete</button>
                                    <button wire:click="startReschedule({{ $followup->id }})" class="text-brand-700 hover:underline text-xs font-medium">Reschedule</button>
                                    <button wire:click="cancel({{ $followup->id }})" wire:confirm="Cancel this follow-up?" class="text-red-600 hover:underline text-xs font-medium">Cancel</button>
                                @endcan
                            @endif
                        </td>
                    </tr>
                    @if ($reschedulingId === $followup->id)
                        <tr>
                            <td colspan="5" class="px-4 py-3 bg-brand-50">
                                <form wire:submit="reschedule" class="flex items-center gap-3">
                                    <label class="text-sm text-gray-700">New due date:</label>
                                    <input wire:model="rescheduleDate" type="date" class="rounded border-gray-300 shadow-sm text-sm">
                                    <button type="submit" class="px-3 py-1.5 bg-brand-600 text-white rounded text-sm">Save</button>
                                    <button type="button" wire:click="$set('reschedulingId', null)" class="px-3 py-1.5 border border-gray-300 rounded text-sm text-gray-600">Close</button>
                                    @error('rescheduleDate') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">No follow-ups in this view.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
