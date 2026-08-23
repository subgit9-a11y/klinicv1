<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Doctors</h1>
            <p class="text-sm text-gray-600">Directory, onboarding, availability and leave.</p>
        </div>
        <button wire:click="$toggle('showOnboardForm')" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">
            {{ $showOnboardForm ? 'Close' : '+ Onboard Doctor' }}
        </button>
    </div>

    @if (session('message'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">{{ session('message') }}</div>
    @endif

    @if ($showOnboardForm)
        <div class="mb-6 bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h2 class="text-lg font-semibold mb-4">Onboard doctor</h2>
            <form wire:submit="onboard" class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input wire:model="name" type="text" class="w-full rounded border-gray-300 shadow-sm">
                    @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input wire:model="email" type="email" class="w-full rounded border-gray-300 shadow-sm">
                    @error('email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input wire:model="phone" type="text" class="w-full rounded border-gray-300 shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Temporary password</label>
                    <input wire:model="password" type="password" class="w-full rounded border-gray-300 shadow-sm">
                    @error('password') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Specialization</label>
                    <input wire:model="specialization" type="text" class="w-full rounded border-gray-300 shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Registration no.</label>
                    <input wire:model="registration_number" type="text" class="w-full rounded border-gray-300 shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Consultation fee (₹)</label>
                    <input wire:model="consultation_fee_rupees" type="number" min="0" class="w-full rounded border-gray-300 shadow-sm">
                </div>
                <div class="col-span-2">
                    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Onboard</button>
                </div>
            </form>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Doctor</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fee</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($doctors as $doctor)
                        <tr class="{{ $selectedId === $doctor->id ? 'bg-brand-50' : '' }}">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">{{ $doctor->name }}</div>
                                <div class="text-xs text-gray-500">{{ $doctor->specialization ?? $doctor->email }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">₹{{ number_format(($doctor->consultation_fee_cents ?? 0) / 100, 0) }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs font-medium rounded-full {{ $doctor->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $doctor->is_active ? 'Active' : 'Disabled' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right space-x-1 text-sm">
                                <button wire:click="select({{ $doctor->id }}, 'profile')" class="text-brand-600 hover:underline">Profile</button>
                                <button wire:click="select({{ $doctor->id }}, 'schedule')" class="text-brand-600 hover:underline">Schedule</button>
                                <button wire:click="select({{ $doctor->id }}, 'leave')" class="text-brand-600 hover:underline">Leave</button>
                                <button wire:click="toggleActive({{ $doctor->id }})" class="{{ $doctor->is_active ? 'text-red-600' : 'text-green-600' }} hover:underline">
                                    {{ $doctor->is_active ? 'Disable' : 'Enable' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No doctors yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            @if ($selected)
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h2 class="text-lg font-semibold mb-1">{{ $selected->name }}</h2>
                    <p class="text-xs text-gray-500 mb-4">{{ $selected->email }} · {{ $selected->leaves_count ?? 0 }} leave records</p>

                    <div class="mb-4 flex gap-2 border-b border-gray-200">
                        @foreach (['profile' => 'Profile', 'schedule' => 'Schedule', 'leave' => 'Leave'] as $p => $label)
                            <button wire:click="select({{ $selected->id }}, '{{ $p }}')"
                                class="px-3 py-2 text-sm font-medium {{ $panel === $p ? 'text-brand-700 border-b-2 border-brand-600' : 'text-gray-500' }}">{{ $label }}</button>
                        @endforeach
                    </div>

                    @if ($panel === 'profile')
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Specialization</label>
                                <input wire:model="edit_specialization" type="text" class="w-full rounded border-gray-300 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Registration no.</label>
                                <input wire:model="edit_registration_number" type="text" class="w-full rounded border-gray-300 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Consultation fee (₹)</label>
                                <input wire:model="edit_consultation_fee_rupees" type="number" min="0" class="w-full rounded border-gray-300 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Consultation duration (minutes)</label>
                                <input wire:model="edit_consultation_duration_minutes" type="number" min="5" max="240" placeholder="Default 30" class="w-full rounded border-gray-300 shadow-sm">
                                @error('edit_consultation_duration_minutes') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Max appointments per day</label>
                                <input wire:model="edit_max_daily_appointments" type="number" min="1" max="200" placeholder="No cap" class="w-full rounded border-gray-300 shadow-sm">
                                @error('edit_max_daily_appointments') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <button wire:click="saveProfile" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Save profile</button>
                        </div>
                    @elseif ($panel === 'schedule')
                        <div class="space-y-3">
                            <p class="text-xs text-gray-500">Enabled days are offered for booking; a break range blocks slots within the day.</p>
                            @foreach ($days as $day)
                                <div class="flex items-center gap-2 text-sm">
                                    <label class="w-16 flex items-center gap-1">
                                        <input type="checkbox" wire:model="schedule.{{ $day }}.enabled" class="rounded border-gray-300 text-brand-600">
                                        <span>{{ $day }}</span>
                                    </label>
                                    <input wire:model="schedule.{{ $day }}.start" type="time" class="rounded border-gray-300 shadow-sm w-28" @disabled(!($schedule[$day]['enabled'] ?? false))>
                                    <span class="text-gray-400">–</span>
                                    <input wire:model="schedule.{{ $day }}.end" type="time" class="rounded border-gray-300 shadow-sm w-28" @disabled(!($schedule[$day]['enabled'] ?? false))>
                                    <span class="text-xs text-gray-400">break</span>
                                    <input wire:model="schedule.{{ $day }}.break_start" type="time" class="rounded border-gray-300 shadow-sm w-28" @disabled(!($schedule[$day]['enabled'] ?? false))>
                                    <input wire:model="schedule.{{ $day }}.break_end" type="time" class="rounded border-gray-300 shadow-sm w-28" @disabled(!($schedule[$day]['enabled'] ?? false))>
                                </div>
                                @error("schedule.{$day}.end") <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                            @endforeach
                            <button wire:click="saveSchedule" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Save schedule</button>
                        </div>
                    @else
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">From</label>
                                    <input wire:model="leave_start" type="date" class="w-full rounded border-gray-300 shadow-sm">
                                    @error('leave_start') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">To</label>
                                    <input wire:model="leave_end" type="date" class="w-full rounded border-gray-300 shadow-sm">
                                    @error('leave_end') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                                    <select wire:model="leave_type" class="w-full rounded border-gray-300 shadow-sm">
                                        <option value="LEAVE">Leave</option>
                                        <option value="HOLIDAY">Holiday</option>
                                        <option value="EMERGENCY">Emergency</option>
                                        <option value="OTHER">Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                                    <input wire:model="leave_reason" type="text" class="w-full rounded border-gray-300 shadow-sm">
                                </div>
                            </div>
                            <button wire:click="addLeave" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Record leave</button>
                        </div>
                    @endif
                </div>
            @else
                <div class="bg-gray-50 border border-dashed border-gray-300 rounded-lg p-10 text-center text-gray-400 text-sm">
                    Select a doctor to edit profile, schedule or leave.
                </div>
            @endif
        </div>
    </div>
</div>
