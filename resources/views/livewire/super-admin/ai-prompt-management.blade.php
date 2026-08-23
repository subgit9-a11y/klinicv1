<div class="space-y-6">
    <x-ui.page-header title="AI Prompts">
        <x-slot:actions>
            <button wire:click="$set('showPromptForm', true)" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">+ New prompt</button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session()->has('message'))
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 px-4 py-2 text-sm">{{ session('message') }}</div>
    @endif

    @if ($showPromptForm)
        <div class="bg-white rounded-lg shadow p-6 space-y-3 max-w-xl">
            <h3 class="font-semibold text-gray-900">New prompt</h3>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Key (matches feature default_prompt_key)</label>
                <input wire:model="key" type="text" placeholder="ai_scribe" class="w-full rounded border-gray-300 shadow-sm text-sm font-mono">
                @error('key') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Name</label>
                <input wire:model="name" type="text" class="w-full rounded border-gray-300 shadow-sm text-sm">
                @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Medicine system</label>
                <select wire:model="system" class="w-full rounded border-gray-300 shadow-sm text-sm">
                    @foreach ($systems as $sys)
                        <option value="{{ $sys }}">{{ $sys }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button wire:click="createPrompt" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Create</button>
                <button wire:click="$set('showPromptForm', false)" class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
            </div>
        </div>
    @endif

    @if ($versionPromptId)
        @php $editingPrompt = $prompts->firstWhere('id', $versionPromptId); @endphp
        <div class="bg-white rounded-lg shadow p-6 space-y-3">
            <h3 class="font-semibold text-gray-900">Publish new version — {{ $editingPrompt?->key }}</h3>
            <p class="text-xs text-gray-500">The highest version number is active. Publishing creates version {{ (($editingPrompt?->versions->max('version')) ?? 0) + 1 }}.</p>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">System prompt</label>
                <textarea wire:model="system_prompt" rows="3" class="w-full rounded border-gray-300 shadow-sm text-sm font-mono"></textarea>
                @error('system_prompt') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">User prompt template — placeholders @verbatim{{variable}}@endverbatim</label>
                <textarea wire:model="user_prompt_template" rows="6" class="w-full rounded border-gray-300 shadow-sm text-sm font-mono"></textarea>
                @error('user_prompt_template') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div class="max-w-xs">
                <label class="block text-xs font-medium text-gray-500 mb-1">Default model</label>
                <input wire:model="default_model" type="text" placeholder="gemini-2.0-flash" class="w-full rounded border-gray-300 shadow-sm text-sm font-mono">
                @error('default_model') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div class="flex gap-2">
                <button wire:click="publishVersion" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Publish version</button>
                <button wire:click="$set('versionPromptId', null)" class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
            </div>
        </div>
    @endif

    <div class="space-y-3">
        @forelse ($prompts as $prompt)
            <div class="bg-white rounded-lg shadow p-4" wire:key="prompt-{{ $prompt->id }}">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="font-mono text-sm font-semibold text-gray-900">{{ $prompt->key }}</span>
                        <span class="text-gray-400 mx-1">·</span>
                        <span class="text-sm text-gray-700">{{ $prompt->name }}</span>
                        <span class="ml-2 text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded">{{ $prompt->system }}</span>
                        @if (! $prompt->is_active)
                            <span class="ml-1 text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded">Disabled</span>
                        @endif
                    </div>
                    <div class="space-x-3 text-xs">
                        <button wire:click="startVersion({{ $prompt->id }})" class="text-brand-600 hover:underline">Publish version</button>
                        <button wire:click="togglePrompt({{ $prompt->id }})" class="text-gray-600 hover:underline">{{ $prompt->is_active ? 'Disable' : 'Enable' }}</button>
                    </div>
                </div>
                @if ($prompt->versions->isNotEmpty())
                    <table class="mt-3 min-w-full text-xs">
                        <thead class="text-left text-gray-400 uppercase">
                            <tr>
                                <th class="pr-4 py-1">Version</th>
                                <th class="pr-4 py-1">Model</th>
                                <th class="pr-4 py-1">Template (excerpt)</th>
                                <th class="py-1">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($prompt->versions->sortByDesc('version') as $version)
                                <tr class="border-t border-gray-100">
                                    <td class="pr-4 py-1 font-mono">v{{ $version->version }}</td>
                                    <td class="pr-4 py-1 font-mono">{{ $version->default_model ?? '—' }}</td>
                                    <td class="pr-4 py-1 text-gray-500">{{ \Illuminate\Support\Str::limit($version->user_prompt_template, 80) }}</td>
                                    <td class="py-1">
                                        @if ($version->version === $prompt->versions->max('version') && $prompt->is_active)
                                            <span class="text-green-600">Active</span>
                                        @else
                                            <span class="text-gray-400">Superseded</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="mt-2 text-xs text-gray-400">No versions yet — this prompt cannot be used until one is published.</p>
                @endif
            </div>
        @empty
            <div class="bg-white rounded-lg shadow p-6 text-center text-gray-400 text-sm">No prompts defined.</div>
        @endforelse
    </div>
</div>
