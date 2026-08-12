<x-layouts.app sidebar :title="__('klinic360.appointments.title')">
    <x-ui.page-header :title="__('klinic360.appointments.title')">
        <x-slot:actions>
            @can('appointments.create')
                <button type="button" wire:click="toggleBookingForm"
                        class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-md transition">
                    + {{ __('klinic360.appointments.new') }}
                </button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if(session('appointment-message'))
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-700">
            {{ session('appointment-message') }}
        </div>
    @endif

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <input type="date" wire:model.live="date"
               class="rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm" />
        <input type="text" wire:model.live="search" placeholder="{{ __('klinic360.appointments.search_placeholder') }}"
               class="flex-1 min-w-[200px] rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm" />
        <span class="text-sm text-gray-500">{{ $appointments->total() }} {{ __('klinic360.appointments.for_date') }}</span>
    </div>

    @if($showBookingForm)
        <div class="mb-6 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('klinic360.appointments.book') }}</h2>
            <form wire:submit="book" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('klinic360.appointments.patient') }} *</label>
                        <input type="text" wire:model.live="search" placeholder="{{ __('klinic360.appointments.search_patient') }}"
                               class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        @if($patients->isNotEmpty())
                            <ul class="mt-1 border border-gray-200 rounded-md divide-y divide-gray-100 max-h-40 overflow-auto">
                                @foreach($patients as $p)
                                    <li>
                                        <button type="button" wire:click="$set('patientId', {{ $p->id }}); $set('search', '{{ $p->first_name }} {{ $p->last_name }} ({{ $p->k360_uid }})')"
                                                class="w-full text-left px-3 py-2 hover:bg-brand-50 text-sm">
                                            <span class="font-medium">{{ $p->first_name }} {{ $p->last_name }}</span>
                                            <span class="text-gray-500 ml-2">{{ $p->k360_uid }}</span>
                                            <span class="text-gray-400 ml-2">{{ $p->phone }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @error('patientId') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('klinic360.appointments.doctor') }}</label>
                        <select wire:model="doctorId" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">{{ __('klinic360.appointments.unassigned') }}</option>
                            @foreach($doctors as $doctor)
                                <option value="{{ $doctor->id }}">{{ $doctor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('klinic360.appointments.type') }} *</label>
                        <select wire:model="type" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="WALK_IN">{{ __('klinic360.appointments.types.walk_in') }}</option>
                            <option value="IN_PERSON">{{ __('klinic360.appointments.types.in_person') }}</option>
                            <option value="ONLINE">{{ __('klinic360.appointments.types.online') }}</option>
                            <option value="FOLLOW_UP">{{ __('klinic360.appointments.types.follow_up') }}</option>
                            <option value="TREATMENT">{{ __('klinic360.appointments.types.treatment') }}</option>
                            <option value="IPD_REVIEW">{{ __('klinic360.appointments.types.ipd_review') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('klinic360.appointments.date') }} *</label>
                        <input type="date" wire:model="date" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('klinic360.appointments.start_time') }} *</label>
                        <input type="time" wire:model="startTime" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        @error('startTime') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        @error('start_time') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('klinic360.appointments.duration') }}</label>
                        <select wire:model="durationMinutes" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="15">15 {{ __('klinic360.appointments.min') }}</option>
                            <option value="30">30 {{ __('klinic360.appointments.min') }}</option>
                            <option value="45">45 {{ __('klinic360.appointments.min') }}</option>
                            <option value="60">60 {{ __('klinic360.appointments.min') }}</option>
                        </select>
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('klinic360.appointments.reason') }}</label>
                        <input type="text" wire:model="reason" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-md">
                        {{ __('klinic360.appointments.confirm_book') }}
                    </button>
                    <button type="button" wire:click="toggleBookingForm" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-md">
                        {{ __('klinic360.appointments.cancel') }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('klinic360.appointments.col_time') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('klinic360.appointments.col_patient') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('klinic360.appointments.col_doctor') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('klinic360.appointments.col_type') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('klinic360.appointments.col_token') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('klinic360.appointments.col_status') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('klinic360.appointments.col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($appointments as $appt)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-900 whitespace-nowrap">{{ $appt->start_time }}{{ $appt->end_time ? '–'.$appt->end_time : '' }}</td>
                        <td class="px-4 py-3 text-sm">
                            <a href="{{ route('patients.show', $appt->patient_id) }}" class="text-brand-700 hover:underline font-medium">
                                {{ $appt->patient?->first_name }} {{ $appt->patient?->last_name }}
                            </a>
                            <div class="text-xs text-gray-400">{{ $appt->patient?->k360_uid }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ $appt->doctor?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ __('klinic360.appointments.types.'.strtolower($appt->type)) }}</td>
                        <td class="px-4 py-3 text-sm">
                            @if($appt->token)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono bg-brand-100 text-brand-700">{{ $appt->token->token_number }}</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ \App\Models\Appointment::class === null ? '' : '' }} @switch($appt->status) @case('SCHEDULED') bg-blue-100 text-blue-700 @break @case('CONFIRMED') bg-indigo-100 text-indigo-700 @break @case('CHECKED_IN') bg-amber-100 text-amber-700 @break @case('IN_CONSULTATION') bg-purple-100 text-purple-700 @break @case('COMPLETED') bg-green-100 text-green-700 @break @case('CANCELLED') bg-red-100 text-red-700 @break @case('NO_SHOW') bg-gray-200 text-gray-700 @break @endswitch">{{ $appt->status }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-right">
                            @can('cancel', $appt)
                                @if(!in_array($appt->status, ['COMPLETED','CANCELLED']))
                                    <button wire:click="cancelAppointment({{ $appt->id }})" wire:confirm="{{ __('klinic360.appointments.confirm_cancel') }}"
                                            class="text-xs text-red-600 hover:text-red-800">{{ __('klinic360.appointments.cancel') }}</button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-400">{{ __('klinic360.appointments.none') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $appointments->links() }}
    </div>
</x-layouts.app>
