<x-layouts.app sidebar :title="__('klinic360.queue.title')">
    <x-ui.page-header :title="__('klinic360.queue.title')">
        <x-slot:actions>
            <input type="date" wire:model.live="date"
                   class="rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm" />
        </x-slot:actions>
    </x-ui.page-header>

    @if(session('queue-message'))
        <div class="mb-4 rounded-md bg-blue-50 border border-blue-200 p-3 text-sm text-blue-700">
            {{ session('queue-message') }}
        </div>
    @endif
    @if(session('queue-error'))
        <div class="mb-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
            {{ session('queue-error') }}
        </div>
    @endif

    @if($doctors->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center text-sm text-gray-400">
            {{ __('klinic360.queue.no_doctors') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach($doctors as $doctor)
            @php
                $docTokens = $byDoctor->get($doctor->id, collect());
                $docStats = $stats[$doctor->id] ?? ['total' => 0, 'waiting' => 0, 'called' => 0, 'in_progress' => 0, 'done' => 0, 'skipped' => 0];
            @endphp
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900 text-sm">{{ $doctor->name }}</h3>
                        @can('queue.manage')
                            <button wire:click="callNext({{ $doctor->id }})" wire:loading.attr="disabled"
                                    class="px-3 py-1.5 bg-brand-600 hover:bg-brand-700 text-white text-xs font-medium rounded-md transition disabled:opacity-50">
                                {{ __('klinic360.queue.call_next') }}
                            </button>
                        @endcan
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2 text-xs">
                        <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-600">{{ __('klinic360.queue.total') }}: {{ $docStats['total'] }}</span>
                        <span class="px-2 py-0.5 rounded bg-amber-100 text-amber-700">{{ __('klinic360.queue.waiting') }}: {{ $docStats['waiting'] }}</span>
                        <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-700">{{ __('klinic360.queue.done') }}: {{ $docStats['done'] }}</span>
                        <span class="px-2 py-0.5 rounded bg-red-100 text-red-700">{{ __('klinic360.queue.skipped') }}: {{ $docStats['skipped'] }}</span>
                    </div>
                </div>

                <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                    @forelse($docTokens as $token)
                        <div class="px-4 py-3 flex items-center gap-3 @if($token->status === 'CALLED') bg-amber-50 @elseif($token->status === 'IN_PROGRESS') bg-purple-50 @endif">
                            <span class="font-mono font-bold text-lg w-10 text-center {{ match($token->status) { 'WAITING' => 'text-gray-400', 'CALLED' => 'text-amber-600', 'IN_PROGRESS' => 'text-purple-600', 'DONE' => 'text-green-600', 'SKIPPED' => 'text-red-400', default => 'text-gray-400' } }}">{{ $token->token_number }}</span>

                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate">
                                    {{ $token->appointment?->patient?->first_name }} {{ $token->appointment?->patient?->last_name }}
                                </div>
                                <div class="text-xs text-gray-400">{{ $token->appointment?->patient?->k360_uid }}</div>
                            </div>

                            <span class="text-xs font-medium px-2 py-0.5 rounded @switch($token->status) @case('WAITING') bg-gray-100 text-gray-600 @break @case('CALLED') bg-amber-100 text-amber-700 @break @case('IN_PROGRESS') bg-purple-100 text-purple-700 @break @case('DONE') bg-green-100 text-green-700 @break @case('SKIPPED') bg-red-100 text-red-700 @break @endswitch">{{ $token->status }}</span>

                            @can('queue.manage')
                                <div class="flex items-center gap-1">
                                    @if($token->status === 'CALLED')
                                        <button wire:click="startConsultation({{ $token->id }})" wire:loading.attr="disabled"
                                                class="text-xs px-2 py-1 bg-purple-100 text-purple-700 rounded hover:bg-purple-200">{{ __('klinic360.queue.start') }}</button>
                                    @endif
                                    @if(in_array($token->status, ['CALLED', 'IN_PROGRESS']))
                                        <button wire:click="complete({{ $token->id }})" wire:loading.attr="disabled"
                                                class="text-xs px-2 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200">{{ __('klinic360.queue.done_btn') }}</button>
                                    @endif
                                    @if(in_array($token->status, ['WAITING', 'CALLED']))
                                        <button wire:click="skip({{ $token->id }})" wire:confirm="{{ __('klinic360.queue.confirm_skip') }}" wire:loading.attr="disabled"
                                                class="text-xs px-2 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200">{{ __('klinic360.queue.skip_btn') }}</button>
                                    @endif
                                    @if($token->status === 'SKIPPED')
                                        <button wire:click="recall({{ $token->id }})" wire:loading.attr="disabled"
                                                class="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200">{{ __('klinic360.queue.recall') }}</button>
                                    @endif
                                </div>
                            @endcan
                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-sm text-gray-300">{{ __('klinic360.queue.empty') }}</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.app>
