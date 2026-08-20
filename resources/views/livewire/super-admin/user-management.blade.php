<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Users</h1>
            <p class="text-sm text-gray-600">Cross-clinic user accounts — doctors, receptionists, therapists, nurses.</p>
        </div>
        <button wire:click="$toggle('showForm')" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">
            {{ $showForm ? 'Close' : '+ New User' }}
        </button>
    </div>

    @if (session('message'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">{{ session('message') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 p-3 bg-red-100 text-red-700 rounded">{{ session('error') }}</div>
    @endif

    @if ($showForm)
        <div class="mb-6 bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h2 class="text-lg font-semibold mb-4">{{ $editingId ? 'Edit User' : 'New User' }}</h2>
            <form wire:submit="{{ $editingId ? 'saveEdit' : 'createUser' }}" class="grid grid-cols-2 gap-4">
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Clinic</label>
                    <select wire:model="tenant_id" class="w-full rounded border-gray-300 shadow-sm">
                        <option value="">Select clinic…</option>
                        @foreach ($tenants as $tenant)
                            <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                        @endforeach
                    </select>
                    @error('tenant_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <select wire:model="role" class="w-full rounded border-gray-300 shadow-sm">
                        @foreach (\App\Livewire\SuperAdmin\UserManagement::ROLES as $roleOption)
                            <option value="{{ $roleOption }}">{{ $roleOption }}</option>
                        @endforeach
                    </select>
                    @error('role') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $editingId ? 'New password (leave blank to keep)' : 'Password' }}</label>
                    <input wire:model="password" type="password" class="w-full rounded border-gray-300 shadow-sm">
                    @error('password') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="col-span-2">
                    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">
                        {{ $editingId ? 'Save changes' : 'Create user' }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="mb-4 flex gap-3">
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search name or email…" class="flex-1 rounded border-gray-300 shadow-sm">
        <select wire:model.live="roleFilter" class="rounded border-gray-300 shadow-sm">
            <option value="">All roles</option>
            @foreach (\App\Livewire\SuperAdmin\UserManagement::ROLES as $roleOption)
                <option value="{{ $roleOption }}">{{ $roleOption }}</option>
            @endforeach
        </select>
        <select wire:model.live="tenantFilter" class="rounded border-gray-300 shadow-sm">
            <option value="">All clinics</option>
            @foreach ($tenants as $tenant)
                <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Clinic</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($users as $user)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $user->name }}</div>
                            <div class="text-xs text-gray-500">{{ $user->email }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ $user->tenant?->name ?? 'Super Admin' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ $user->role }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs font-medium rounded-full {{ $user->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $user->is_active ? 'Active' : 'Disabled' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-1">
                            <button wire:click="edit({{ $user->id }})" class="text-brand-600 hover:underline text-sm">Edit</button>
                            <button wire:click="toggleActive({{ $user->id }})" wire:confirm="{{ $user->is_active ? 'Disable this user?' : 'Enable this user?' }}" class="{{ $user->is_active ? 'text-red-600' : 'text-green-600' }} hover:underline text-sm">
                                {{ $user->is_active ? 'Disable' : 'Enable' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-200">{{ $users->links() }}</div>
    </div>
</div>
