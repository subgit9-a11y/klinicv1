<div class="space-y-8 max-w-3xl">
    @if (session()->has('message'))
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 px-4 py-2 text-sm">{{ session('message') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-1">Appointment types</h2>
        <p class="text-xs text-gray-500 mb-4">Types and default durations offered when booking appointments.</p>

        <div class="space-y-2">
            @foreach ($types as $i => $type)
                <div class="flex items-center gap-2" wire:key="appt-type-{{ $i }}">
                    <input wire:model="types.{{ $i }}.key" type="text" placeholder="KEY" class="w-40 rounded border-gray-300 shadow-sm text-sm uppercase">
                    <input wire:model="types.{{ $i }}.label" type="text" placeholder="Label" class="flex-1 rounded border-gray-300 shadow-sm text-sm">
                    <input wire:model="types.{{ $i }}.duration_minutes" type="number" min="5" max="480" class="w-24 rounded border-gray-300 shadow-sm text-sm" title="Duration (minutes)">
                    <span class="text-xs text-gray-400">min</span>
                    <button wire:click="removeType({{ $i }})" type="button" class="text-red-600 hover:text-red-800 text-sm px-2" title="Remove">✕</button>
                </div>
                @error("types.{$i}.key") <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                @error("types.{$i}.label") <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                @error("types.{$i}.duration_minutes") <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
            @endforeach
        </div>
        @error('types') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror

        <div class="mt-4 flex gap-2">
            <button wire:click="addType" type="button" class="px-3 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">+ Add type</button>
            <button wire:click="saveTypes" type="button" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Save types</button>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-1">Online booking</h2>
        <p class="text-xs text-gray-500 mb-4">Fee and duration charged for public online-consultation bookings.</p>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Consultation fee (₹)</label>
                <input wire:model="online_fee_rupees" type="number" min="0" class="w-full rounded border-gray-300 shadow-sm">
                @error('online_fee_rupees') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Duration (minutes)</label>
                <input wire:model="online_duration_minutes" type="number" min="5" max="240" class="w-full rounded border-gray-300 shadow-sm">
                @error('online_duration_minutes') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
        </div>

        <button wire:click="saveOnlineBooking" type="button" class="mt-4 px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Save online booking</button>
    </div>
</div>
