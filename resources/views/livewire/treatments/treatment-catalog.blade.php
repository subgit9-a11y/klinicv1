<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Treatment Catalogue</h1>
        <p class="text-sm text-gray-600">Services your clinic offers and the rooms they run in.</p>
    </div>

    @if (session('message'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">{{ session('message') }}</div>
    @endif

    <div class="mb-4 flex items-center justify-between">
        <div class="flex gap-2 border-b border-gray-200">
            <button wire:click="setTab('services')" class="px-4 py-2 text-sm font-medium {{ $activeTab === 'services' ? 'text-brand-700 border-b-2 border-brand-600' : 'text-gray-500' }}">Services</button>
            <button wire:click="setTab('rooms')" class="px-4 py-2 text-sm font-medium {{ $activeTab === 'rooms' ? 'text-brand-700 border-b-2 border-brand-600' : 'text-gray-500' }}">Rooms</button>
        </div>
        @if ($activeTab === 'services')
            <button wire:click="$toggle('showServiceForm')" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">{{ $showServiceForm ? 'Close' : '+ New Service' }}</button>
        @else
            <button wire:click="$toggle('showRoomForm')" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">{{ $showRoomForm ? 'Close' : '+ New Room' }}</button>
        @endif
    </div>

    @if ($activeTab === 'services')
        @if ($showServiceForm)
            <div class="mb-6 bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <h2 class="text-lg font-semibold mb-4">New service</h2>
                <form wire:submit="addService" class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input wire:model="service_name" type="text" class="w-full rounded border-gray-300 shadow-sm">
                        @error('service_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <input wire:model="service_category" type="text" class="w-full rounded border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Duration (min)</label>
                        <input wire:model="service_duration_minutes" type="number" min="1" class="w-full rounded border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Price (₹)</label>
                        <input wire:model="service_price_rupees" type="number" min="0" class="w-full rounded border-gray-300 shadow-sm">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea wire:model="service_description" rows="2" class="w-full rounded border-gray-300 shadow-sm"></textarea>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input wire:model="service_requires_therapist" type="checkbox" class="rounded border-gray-300 text-brand-600"> Requires therapist
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input wire:model="service_requires_room" type="checkbox" class="rounded border-gray-300 text-brand-600"> Requires room
                    </label>
                    <div class="col-span-2">
                        <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Create service</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Service</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($services as $service)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $service->name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $service->category ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $service->duration_minutes ? $service->duration_minutes.' min' : '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">₹{{ number_format(($service->price_cents ?? 0) / 100, 0) }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs font-medium rounded-full {{ $service->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $service->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right space-x-1 text-sm">
                                <button wire:click="toggleService({{ $service->id }})" class="text-brand-600 hover:underline">{{ $service->is_active ? 'Deactivate' : 'Activate' }}</button>
                                <button wire:click="deleteService({{ $service->id }})" wire:confirm="Remove this service?" class="text-red-600 hover:underline">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No services defined.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        @if ($showRoomForm)
            <div class="mb-6 bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                <h2 class="text-lg font-semibold mb-4">New room</h2>
                <form wire:submit="addRoom" class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Room number</label>
                        <input wire:model="room_number" type="text" class="w-full rounded border-gray-300 shadow-sm">
                        @error('room_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <input wire:model="room_type" type="text" class="w-full rounded border-gray-300 shadow-sm" placeholder="Panchakarma">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Capacity</label>
                        <input wire:model="room_capacity" type="number" min="1" class="w-full rounded border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Supported treatments</label>
                        <input wire:model="room_supported" type="text" class="w-full rounded border-gray-300 shadow-sm">
                    </div>
                    <div class="col-span-2">
                        <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Create room</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Room</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Capacity</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($rooms as $room)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $room->room_number }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $room->type ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $room->capacity }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs font-medium rounded-full {{ $room->status === 'AVAILABLE' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">{{ $room->status }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="deleteRoom({{ $room->id }})" wire:confirm="Remove this room?" class="text-red-600 hover:underline text-sm">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No rooms defined.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
