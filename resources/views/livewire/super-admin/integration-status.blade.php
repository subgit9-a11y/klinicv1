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
</div>
