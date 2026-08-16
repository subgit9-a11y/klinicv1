<x-layouts.app sidebar :title="__('klinic360.patients.title')">
    <x-ui.page-header :title="__('klinic360.patients.title')">
        <x-slot:actions>
            @can('patients.create')
                <button type="button" wire:click="toggleRegisterForm"
                        class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-md transition">
                    + {{ __('klinic360.patients.new') }}
                </button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if(session('patient-message'))
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-700">
            {{ session('patient-message') }}
        </div>
    @endif

    @if($showRegisterForm)
        <div class="mb-6 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('klinic360.patients.register') }}</h2>
            <form wire:submit="registerPatient" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('klinic360.patients.first_name') }} *</label>
                        <input type="text" wire:model="first_name" class="w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm" />
                        @error('first_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('klinic360.patients.last_name') }}</label>
                        <input type="text" wire:model="last_name" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('klinic360.patients.phone') }} *</label>
                        <input type="text" wire:model="phone" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        @error('phone') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('klinic360.patients.email') }}</label>
                        <input type="email" wire:model="email" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('klinic360.patients.gender') }}</label>
                        <select wire:model="gender" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="UNKNOWN">Unknown</option>
                            <option value="MALE">Male</option>
                            <option value="FEMALE">Female</option>
                            <option value="OTHER">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('klinic360.patients.dob') }}</label>
                        <input type="date" wire:model="dob" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('klinic360.patients.blood_group') }}</label>
                        <input type="text" wire:model="blood_group" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('klinic360.patients.abha_id') }}</label>
                        <input type="text" wire:model="abha_id" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('klinic360.patients.pincode') }}</label>
                        <input type="text" wire:model="pincode" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('klinic360.patients.address') }}</label>
                        <input type="text" wire:model="address" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('klinic360.patients.city') }}</label>
                        <input type="text" wire:model="city" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('klinic360.patients.allergies') }}</label>
                        <input type="text" wire:model="allergies" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('klinic360.patients.chronic_conditions') }}</label>
                        <input type="text" wire:model="chronic_conditions" class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>
                </div>
                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" wire:loading.attr="disabled"
                            class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-md disabled:opacity-50">
                        <span wire:loading.remove>{{ __('klinic360.patients.save') }}</span>
                        <span wire:loading>{{ __('klinic360.patients.saving') }}</span>
                    </button>
                    <button type="button" wire:click="toggleRegisterForm"
                            class="px-4 py-2 text-gray-700 hover:bg-gray-100 text-sm font-medium rounded-md">
                        {{ __('klinic360.patients.cancel') }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <div class="relative max-w-md">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('klinic360.patients.search_placeholder') }}"
                       class="w-full pl-9 pr-4 py-2 rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm" />
                <span class="absolute left-3 top-2.5 text-gray-400">⌕</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left">{{ __('klinic360.patients.uid') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('klinic360.patients.name') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('klinic360.patients.phone') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('klinic360.patients.gender') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('klinic360.patients.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($patients as $patient)
                        <tr wire:key="patient-{{ $patient->id }}" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $patient->k360_uid }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $patient->fullName() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $patient->phone }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $patient->gender }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('patients.show', $patient) }}"
                                   class="text-brand-600 hover:text-brand-800 font-medium">{{ __('klinic360.patients.view_360') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-gray-400">{{ __('klinic360.patients.no_records') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-gray-200">
            {{ $patients->links() }}
        </div>
    </div>
</x-layouts.app>
