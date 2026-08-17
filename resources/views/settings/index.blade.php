<x-layouts.app sidebar :title="__('klinic360.nav.settings')">
    <x-ui.page-header title="Clinic Settings" />

    @if(session('status'))
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('settings.update') }}">
        @csrf
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="px-4 py-3 border-b border-gray-200 text-sm font-semibold text-gray-900">Existing settings</div>
            @forelse($settings as $setting)
                <div class="px-4 py-3 border-b border-gray-100 last:border-b-0 grid grid-cols-1 sm:grid-cols-4 gap-3 items-center">
                    <input type="hidden" name="settings[{{ $loop->index }}][key]" value="{{ $setting->key }}" />
                    <input type="hidden" name="settings[{{ $loop->index }}][category]" value="{{ $setting->category }}" />
                    <div class="text-sm font-medium text-gray-900">{{ $setting->key }}</div>
                    <div class="text-xs text-gray-400">{{ $setting->category ?? 'general' }}</div>
                    <div class="sm:col-span-2">
                        <input type="text" name="settings[{{ $loop->index }}][value]" value="{{ $setting->value }}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" />
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-center text-sm text-gray-500">No settings yet. Add one below.</div>
            @endforelse
        </div>
        <button type="submit" class="bg-brand-600 text-white text-sm font-medium px-4 py-2 rounded-md hover:bg-brand-700">Save changes</button>
    </form>

    <div class="mt-8 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-3">Add setting</h3>
        <form method="POST" action="{{ route('settings.store') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Key</label>
                <input type="text" name="key" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Category</label>
                <input type="text" name="category" placeholder="general" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" />
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-500 mb-1">Value</label>
                <input type="text" name="value" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" />
            </div>
            <div class="sm:col-span-4">
                <button type="submit" class="bg-gray-100 border border-gray-300 text-sm font-medium px-4 py-2 rounded-md hover:bg-gray-200">Add</button>
            </div>
        </form>
    </div>
</x-layouts.app>
