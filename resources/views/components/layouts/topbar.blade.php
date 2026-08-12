@php
    $tenant = auth()->user()?->tenant;
@endphp

<header class="bg-white border-b border-gray-200 px-4 sm:px-6 py-3 flex items-center justify-between">
    <div class="flex items-center gap-3">
        <button type="button" class="md:hidden text-gray-500" x-data @click="$dispatch('toggle-sidebar')">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <h2 class="text-lg font-semibold text-gray-900">{{ $title ?? __('klinic360.dashboard') }}</h2>
    </div>
    <div class="flex items-center gap-4">
        @if($tenant ?? null)
            <span class="hidden sm:inline text-sm text-gray-500">{{ $tenant->name ?? '' }}</span>
        @endif
        @auth
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-sm font-semibold">
                    {{ mb_substr(auth()->user()->name ?? 'U', 0, 1) }}
                </div>
            </div>
        @endauth
    </div>
</header>
