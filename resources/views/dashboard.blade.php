<x-layouts.app sidebar :title="__('klinic360.dashboard')">
    <x-ui.page-header :title="__('klinic360.dashboard')" />

    @php
        $stats = $stats ?? [];
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        @foreach($stats as $stat)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                <div class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $stat['color'] }}">{{ $stat['label'] }}</div>
                <p class="mt-3 text-3xl font-bold text-gray-900">{{ $stat['value'] }}</p>
                @if(!empty($stat['sub']))
                    <p class="mt-1 text-xs text-gray-400">{{ $stat['sub'] }}</p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-2">{{ __('klinic360.app_name') }}</h3>
        <p class="text-sm text-gray-500">{{ __('klinic360.tagline') }}</p>
        <p class="text-xs text-gray-400 mt-3">{{ __('klinic360.plans_summary') }}</p>
    </div>
</x-layouts.app>
