<x-layouts.app sidebar :title="__('klinic360.patients.edit')" :subtitle="$patient->k360_uid">
    <x-ui.page-header :title="__('klinic360.patients.edit')" :subtitle="$patient->k360_uid" />

    @if(session('patient-message'))
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-700">
            {{ session('patient-message') }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form wire:submit="update" class="space-y-4">
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
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('klinic360.patients.notes') }}</label>
                <textarea wire:model="notes" rows="3" class="w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" wire:loading.attr="disabled"
                        class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-md disabled:opacity-50">
                    <span wire:loading.remove>{{ __('klinic360.patients.save') }}</span>
                    <span wire:loading>{{ __('klinic360.patients.saving') }}</span>
                </button>
                <a href="{{ route('patients.show', $patient) }}"
                   class="px-4 py-2 text-gray-700 hover:bg-gray-100 text-sm font-medium rounded-md">
                    {{ __('klinic360.patients.cancel') }}
                </a>
            </div>
        </form>
    </div>
</x-layouts.app>
