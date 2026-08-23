<div class="space-y-6">
    @if (session()->has('message'))
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 px-4 py-2 text-sm">{{ session('message') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="rounded-md bg-red-50 border border-red-200 text-red-800 px-4 py-2 text-sm">{{ session('error') }}</div>
    @endif

    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Channel</label>
            <select wire:model.live="filterChannel" class="rounded border-gray-300 shadow-sm text-sm">
                <option value="">All</option>
                @foreach ($channels as $ch)
                    <option value="{{ $ch }}">{{ $ch }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Event key</label>
            <input wire:model.live.debounce.300ms="filterEvent" type="text" placeholder="appointment." class="rounded border-gray-300 shadow-sm text-sm">
        </div>
        <button wire:click="startCreate" class="ml-auto px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">+ New template</button>
    </div>

    @if ($showForm)
        <div class="bg-white rounded-lg shadow p-6 space-y-3">
            <h3 class="font-semibold text-gray-900">{{ $editingId ? 'Edit template' : 'New template' }}</h3>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Event key</label>
                    <input wire:model="event_key" type="text" placeholder="appointment.confirmation" class="w-full rounded border-gray-300 shadow-sm text-sm" @disabled((bool) $editingId)>
                    @error('event_key') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Channel</label>
                    <select wire:model="channel" class="w-full rounded border-gray-300 shadow-sm text-sm" @disabled((bool) $editingId)>
                        @foreach ($channels as $ch)
                            <option value="{{ $ch }}">{{ $ch }}</option>
                        @endforeach
                    </select>
                    @error('channel') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Name</label>
                    <input wire:model="name" type="text" class="w-full rounded border-gray-300 shadow-sm text-sm">
                    @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Subject (email)</label>
                <input wire:model="subject" type="text" class="w-full rounded border-gray-300 shadow-sm text-sm">
                @error('subject') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Body — placeholders like @{{patient_name}}</label>
                <textarea wire:model="body" rows="4" class="w-full rounded border-gray-300 shadow-sm text-sm font-mono"></textarea>
                @error('body') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div class="grid grid-cols-3 gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">WhatsApp template name</label>
                    <input wire:model="whatsapp_template_name" type="text" class="w-full rounded border-gray-300 shadow-sm text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">SMS template ID</label>
                    <input wire:model="sms_template_id" type="text" class="w-full rounded border-gray-300 shadow-sm text-sm">
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input wire:model="is_active" type="checkbox" class="rounded border-gray-300"> Active
                </label>
            </div>
            <div class="flex gap-2">
                <button wire:click="save" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Save</button>
                <button wire:click="$set('showForm', false)" class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-2">Event</th>
                    <th class="px-4 py-2">Channel</th>
                    <th class="px-4 py-2">Name</th>
                    <th class="px-4 py-2">Scope</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($templates as $template)
                    @php $editable = $template->tenant_id !== null && $template->tenant_id === $currentTenantId; @endphp
                    <tr>
                        <td class="px-4 py-2 font-mono text-xs">{{ $template->event_key }}</td>
                        <td class="px-4 py-2">{{ $template->channel }}</td>
                        <td class="px-4 py-2">{{ $template->name }}</td>
                        <td class="px-4 py-2">
                            @if ($template->tenant_id === null)
                                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded">Platform</span>
                            @else
                                <span class="text-xs bg-brand-50 text-brand-700 px-2 py-0.5 rounded">Clinic</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @if ($template->is_active)
                                <span class="text-green-600 text-xs">Active</span>
                            @else
                                <span class="text-gray-400 text-xs">Disabled</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right space-x-2">
                            @if ($editable)
                                <button wire:click="startEdit({{ $template->id }})" class="text-brand-600 hover:underline text-xs">Edit</button>
                                <button wire:click="remove({{ $template->id }})" wire:confirm="Delete this template?" class="text-red-600 hover:underline text-xs">Delete</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No templates found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
