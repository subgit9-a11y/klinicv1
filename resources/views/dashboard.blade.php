<x-layouts.app sidebar :title="__('klinic360.dashboard')">
    <x-ui.page-header :title="__('klinic360.dashboard')" />

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        @php
            $stats = [
                ['label' => __('klinic360.nav.patients'), 'value' => '—', 'color' => 'bg-blue-50 text-blue-700'],
                ['label' => __('klinic360.nav.appointments'), 'value' => '—', 'color' => 'bg-green-50 text-green-700'],
                ['label' => __('klinic360.nav.queue'), 'value' => '—', 'color' => 'bg-yellow-50 text-yellow-700'],
                ['label' => __('klinic360.nav.billing'), 'value' => '—', 'color' => 'bg-brand-50 text-brand-700'],
            ];
        @endphp
        @foreach($stats as $stat)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                <div class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $stat['color'] }}">{{ $stat['label'] }}</div>
                <p class="mt-3 text-3xl font-bold text-gray-900">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-2">{{ __('klinic360.app_name') }}</h3>
        <p class="text-sm text-gray-500">{{ __('klinic360.tagline') }}</p>
        <p class="text-xs text-gray-400 mt-3">{{ __('klinic360.plans_summary') }}</p>
    </div>
</x-layouts.app>
