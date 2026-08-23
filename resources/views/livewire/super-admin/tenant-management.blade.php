<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Clinics</h1>
            <p class="text-sm text-gray-600">Create, suspend, activate and archive clinic tenants.</p>
        </div>
        <button wire:click="$toggle('showForm')" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">
            {{ $showForm ? 'Close' : '+ New Clinic' }}
        </button>
    </div>

    @if (session('message'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">{{ session('message') }}</div>
    @endif

    @if ($showForm)
        <div class="mb-6 bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h2 class="text-lg font-semibold mb-4">{{ $editingId ? 'Edit Clinic' : 'New Clinic' }}</h2>
            <form wire:submit="{{ $editingId ? 'saveEdit' : 'createClinic' }}" class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input wire:model="name" type="text" class="w-full rounded border-gray-300 shadow-sm">
                    @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Plan</label>
                    <select wire:model="plan_code" class="w-full rounded border-gray-300 shadow-sm">
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->code }}">{{ $plan->name }}</option>
                        @endforeach
                    </select>
                    @error('plan_code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Medicine system</label>
                    <select wire:model="system" class="w-full rounded border-gray-300 shadow-sm">
                        <option value="AYURVEDA">Ayurveda</option>
                        <option value="SIDDHA">Siddha</option>
                        <option value="HOMEOPATHY">Homeopathy</option>
                        <option value="GENERAL">General</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input wire:model="email" type="email" class="w-full rounded border-gray-300 shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input wire:model="phone" type="text" class="w-full rounded border-gray-300 shadow-sm">
                </div>
                @if (! $editingId)
                    <div class="col-span-2 mt-2 border-t pt-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Clinic owner (optional)</h3>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                <input wire:model="owner_name" type="text" class="w-full rounded border-gray-300 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input wire:model="owner_email" type="email" class="w-full rounded border-gray-300 shadow-sm">
                                @error('owner_email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                <input wire:model="owner_password" type="password" class="w-full rounded border-gray-300 shadow-sm">
                                @error('owner_password') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                @endif
                <div class="col-span-2">
                    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">
                        {{ $editingId ? 'Save changes' : 'Create clinic' }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="mb-4 flex gap-3">
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search name or slug…"
            class="flex-1 rounded border-gray-300 shadow-sm">
        <select wire:model.live="statusFilter" class="rounded border-gray-300 shadow-sm">
            <option value="">All statuses</option>
            <option value="TRIAL">Trial</option>
            <option value="ACTIVE">Active</option>
            <option value="SUSPENDED">Suspended</option>
        </select>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Clinic</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plan</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Users</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patients</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Trial ends</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($tenants as $tenant)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $tenant->name }}</div>
                            <div class="text-xs text-gray-500">{{ $tenant->slug }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ $tenant->plan_code }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs font-medium rounded-full
                                {{ $tenant->status === 'ACTIVE' ? 'bg-green-100 text-green-700' : ($tenant->status === 'SUSPENDED' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700') }}">
                                {{ $tenant->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ $tenant->users_count }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ $tenant->patients_count }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $tenant->trial_ends_at?->format('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right space-x-1">
                            <button wire:click="viewUsage({{ $tenant->id }})" class="text-gray-600 hover:underline text-sm">Usage</button>
                            <button wire:click="edit({{ $tenant->id }})" class="text-brand-600 hover:underline text-sm">Edit</button>
                            @if ($tenant->status === 'SUSPENDED')
                                <button wire:click="activate({{ $tenant->id }})" wire:confirm="Re-activate this clinic?" class="text-green-600 hover:underline text-sm">Activate</button>
                            @else
                                <button wire:click="suspend({{ $tenant->id }})" wire:confirm="Suspend this clinic? Its users will be signed out." class="text-yellow-600 hover:underline text-sm">Suspend</button>
                            @endif
                            <button wire:click="archive({{ $tenant->id }})" wire:confirm="Archive this clinic permanently? All its data will be inaccessible." class="text-red-600 hover:underline text-sm">Archive</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No clinics found.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-200">{{ $tenants->links() }}</div>
    </div>

    @if ($usageTenant && $usage)
        <div class="bg-white rounded-lg shadow p-6" wire:key="usage-panel">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-gray-900">Usage — {{ $usageTenant->name }}</h3>
                <button wire:click="closeUsage" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                <div><div class="text-2xl font-bold text-gray-900">{{ number_format($usage['users']) }}</div><div class="text-gray-500">Users</div></div>
                <div><div class="text-2xl font-bold text-gray-900">{{ number_format($usage['patients']) }}</div><div class="text-gray-500">Patients</div></div>
                <div><div class="text-2xl font-bold text-gray-900">{{ number_format($usage['appointments_total']) }}</div><div class="text-gray-500">Appointments (total)</div></div>
                <div><div class="text-2xl font-bold text-gray-900">{{ number_format($usage['appointments_30d']) }}</div><div class="text-gray-500">Appointments (30d)</div></div>
                <div><div class="text-2xl font-bold text-gray-900">{{ number_format($usage['consultations_total']) }}</div><div class="text-gray-500">Consultations</div></div>
                <div><div class="text-2xl font-bold text-gray-900">{{ number_format($usage['invoices_total']) }}</div><div class="text-gray-500">Invoices</div></div>
                <div><div class="text-2xl font-bold text-gray-900">₹{{ number_format($usage['collected_rupees'], 2) }}</div><div class="text-gray-500">Collected</div></div>
                <div><div class="text-2xl font-bold text-gray-900">{{ number_format($usage['documents']) }}</div><div class="text-gray-500">Documents</div></div>
                <div><div class="text-2xl font-bold text-gray-900">{{ number_format($usage['ai_requests']) }}</div><div class="text-gray-500">AI requests</div></div>
                <div><div class="text-2xl font-bold text-gray-900">{{ number_format($usage['notifications_sent']) }}</div><div class="text-gray-500">Notifications sent</div></div>
            </div>
        </div>
    @endif
</div>
