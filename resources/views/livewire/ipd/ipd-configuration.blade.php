<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">IPD Configuration</h1>
        <p class="text-sm text-gray-600">Wards → rooms → beds. Deletion is blocked while children exist or beds are occupied.</p>
    </div>

    @if (session('message'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">{{ session('message') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 p-3 bg-red-100 text-red-700 rounded">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-3">
            @forelse ($wards as $ward)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <button wire:click="toggleWard({{ $ward->id }})" class="w-full px-4 py-3 flex items-center justify-between text-left">
                        <div>
                            <span class="font-medium text-gray-900">{{ $ward->name }}</span>
                            <span class="ml-2 text-xs text-gray-500">{{ $ward->type }}</span>
                        </div>
                        <span class="text-xs text-gray-400">{{ $expandedWardId === $ward->id ? '▲' : '▼' }}</span>
                    </button>
                    @if ($expandedWardId === $ward->id)
                        <div class="px-4 pb-4 border-t border-gray-100">
                            <div class="flex justify-end pt-2">
                                <button wire:click="deleteWard({{ $ward->id }})" wire:confirm="Delete this ward? Only possible when it has no rooms." class="text-red-600 hover:underline text-sm">Delete ward</button>
                            </div>
                            @foreach ($rooms->where('ipd_ward_id', $ward->id) as $room)
                                <div class="mt-2 p-3 bg-gray-50 rounded">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-gray-800">Room {{ $room->room_number }} ({{ $room->type }})</span>
                                        <button wire:click="deleteRoom({{ $room->id }})" wire:confirm="Delete this room? Only possible when it has no beds." class="text-red-600 hover:underline text-xs">Delete room</button>
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-2">
                                        @forelse ($beds->where('ipd_room_id', $room->id) as $bed)
                                            <span class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-full {{ $bed->status === 'AVAILABLE' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                                {{ $bed->bed_number }} · {{ $bed->status }} · ₹{{ number_format(($bed->daily_rate_cents ?? 0) / 100, 0) }}/day
                                                <button wire:click="deleteBed({{ $bed->id }})" wire:confirm="Delete this bed?" class="text-red-600 font-bold">×</button>
                                            </span>
                                        @empty
                                            <span class="text-xs text-gray-400">No beds</span>
                                        @endforelse
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <div class="bg-gray-50 border border-dashed border-gray-300 rounded-lg p-10 text-center text-gray-400 text-sm">
                    No wards yet — add the first one.
                </div>
            @endforelse
        </div>

        <div class="space-y-6">
            <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-200">
                <h2 class="text-base font-semibold mb-3">Add ward</h2>
                <form wire:submit="addWard" class="space-y-3">
                    <input wire:model="ward_name" type="text" placeholder="Ward name" class="w-full rounded border-gray-300 shadow-sm">
                    @error('ward_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    <select wire:model="ward_type" class="w-full rounded border-gray-300 shadow-sm">
                        <option value="GENERAL">General</option>
                        <option value="PRIVATE">Private</option>
                        <option value="SEMI_PRIVATE">Semi-private</option>
                        <option value="ICU">ICU</option>
                        <option value="SPECIAL">Special</option>
                    </select>
                    <button type="submit" class="w-full px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Add ward</button>
                </form>
            </div>

            <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-200">
                <h2 class="text-base font-semibold mb-3">Add room</h2>
                <form wire:submit="addRoom" class="space-y-3">
                    <select wire:model="room_ward_id" class="w-full rounded border-gray-300 shadow-sm">
                        <option value="">Select ward…</option>
                        @foreach ($wards as $ward)
                            <option value="{{ $ward->id }}">{{ $ward->name }}</option>
                        @endforeach
                    </select>
                    @error('room_ward_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    <input wire:model="room_number" type="text" placeholder="Room number" class="w-full rounded border-gray-300 shadow-sm">
                    @error('room_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    <select wire:model="room_type" class="w-full rounded border-gray-300 shadow-sm">
                        <option value="GENERAL">General</option>
                        <option value="PRIVATE">Private</option>
                        <option value="SEMI_PRIVATE">Semi-private</option>
                        <option value="ICU">ICU</option>
                        <option value="SPECIAL">Special</option>
                    </select>
                    <button type="submit" class="w-full px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Add room</button>
                </form>
            </div>

            <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-200">
                <h2 class="text-base font-semibold mb-3">Add bed</h2>
                <form wire:submit="addBed" class="space-y-3">
                    <select wire:model="bed_room_id" class="w-full rounded border-gray-300 shadow-sm">
                        <option value="">Select room…</option>
                        @foreach ($rooms as $room)
                            <option value="{{ $room->id }}">{{ $room->ward?->name }} — {{ $room->room_number }}</option>
                        @endforeach
                    </select>
                    @error('bed_room_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    <input wire:model="bed_number" type="text" placeholder="Bed number" class="w-full rounded border-gray-300 shadow-sm">
                    @error('bed_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    <input wire:model="bed_daily_rate_rupees" type="number" min="0" placeholder="Daily rate (₹)" class="w-full rounded border-gray-300 shadow-sm">
                    <button type="submit" class="w-full px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Add bed</button>
                </form>
            </div>
        </div>
    </div>
</div>
