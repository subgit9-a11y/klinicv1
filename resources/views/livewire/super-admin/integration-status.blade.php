<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Integrations</h1>
        <p class="text-sm text-gray-600">Read-only provider status. Credentials are stored in environment config, never displayed here.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($integrations as $integration)
            <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="font-semibold text-gray-900">{{ $integration['name'] }}</h2>
                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $integration['configured'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $integration['configured'] ? 'Configured' : 'Not configured' }}
                    </span>
                </div>
                <div class="text-sm text-gray-600">Provider: {{ $integration['provider'] }}</div>
                <div class="text-xs text-gray-400 mt-2 font-mono">{{ $integration['env'] }}</div>
                <div class="text-xs text-gray-400">{{ $integration['category'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded-lg shadow p-6 space-y-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Managed accounts</h2>
            <p class="text-xs text-gray-500 mt-1">
                Database-stored provider credentials (individually encrypted). Active global accounts override
                the environment configuration for every request. Secrets are write-only — only masked previews are shown.
            </p>
        </div>

        @if (session()->has('message'))
            <div class="rounded-md bg-green-50 border border-green-200 text-green-800 px-4 py-2 text-sm">{{ session('message') }}</div>
        @endif

        <div class="border border-gray-200 rounded-md p-4 space-y-3">
            <div class="grid grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Provider</label>
                    <select wire:model.live="accountProvider" class="w-full rounded border-gray-300 shadow-sm text-sm">
                        @foreach (array_keys($providerKeys) as $provider)
                            <option value="{{ $provider }}">{{ $provider }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Scope</label>
                    <select wire:model="accountTenantId" class="w-full rounded border-gray-300 shadow-sm text-sm">
                        <option value="">All clinics (global)</option>
                        @foreach ($tenants as $tenant)
                            <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Display name (optional)</label>
                    <input wire:model="accountName" type="text" class="w-full rounded border-gray-300 shadow-sm text-sm">
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                @foreach (($providerKeys[$accountProvider] ?? []) as $credKey => $configKey)
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ $credKey }}</label>
                        <input wire:model="credentials.{{ $credKey }}" type="password" autocomplete="new-password"
                               placeholder="leave blank to keep existing" class="w-full rounded border-gray-300 shadow-sm text-sm font-mono">
                    </div>
                @endforeach
            </div>
            @error('credentials') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            <button wire:click="saveAccount" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Save account</button>
        </div>

        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-2">Provider</th>
                    <th class="px-4 py-2">Scope</th>
                    <th class="px-4 py-2">Credentials</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($managedAccounts as $row)
                    <tr wire:key="integration-account-{{ $row['account']->id }}">
                        <td class="px-4 py-2 font-mono text-xs">{{ $row['account']->provider }}</td>
                        <td class="px-4 py-2 text-xs">{{ $row['tenant_name'] }}</td>
                        <td class="px-4 py-2 text-xs font-mono">
                            @foreach ($row['masked'] as $key => $preview)
                                <div>{{ $key }}: {{ $preview }}</div>
                            @endforeach
                        </td>
                        <td class="px-4 py-2">
                            @if ($row['account']->is_active)
                                <span class="text-green-600 text-xs">Active</span>
                            @else
                                <span class="text-gray-400 text-xs">Disabled</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right space-x-2">
                            <button wire:click="toggleAccount({{ $row['account']->id }})" class="text-gray-600 hover:underline text-xs">{{ $row['account']->is_active ? 'Disable' : 'Enable' }}</button>
                            <button wire:click="deleteAccount({{ $row['account']->id }})" wire:confirm="Delete this integration account?" class="text-red-600 hover:underline text-xs">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">No managed accounts — providers use environment configuration.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
