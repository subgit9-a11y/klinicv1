@php
    $nav = [
        ['route' => 'dashboard', 'label' => __('klinic360.dashboard'), 'icon' => 'M'],
        ['route' => 'patients.index', 'label' => __('klinic360.nav.patients'), 'icon' => 'P'],
        ['route' => 'appointments.index', 'label' => __('klinic360.nav.appointments'), 'icon' => 'A'],
        ['route' => 'queue.index', 'label' => __('klinic360.nav.queue'), 'icon' => 'Q'],
        ['route' => 'emr.index', 'label' => __('klinic360.nav.emr'), 'icon' => 'E'],
        ['route' => 'prescriptions.index', 'label' => __('klinic360.nav.prescriptions'), 'icon' => 'Rx'],
        ['route' => 'treatments.index', 'label' => __('klinic360.nav.treatments'), 'icon' => 'T'],
        ['route' => 'ipd.index', 'label' => __('klinic360.nav.ipd'), 'icon' => 'I'],
        ['route' => 'billing.index', 'label' => __('klinic360.nav.billing'), 'icon' => '₹'],
        ['route' => 'payments.index', 'label' => __('klinic360.nav.payments'), 'icon' => '$'],
        ['route' => 'documents.index', 'label' => __('klinic360.nav.documents'), 'icon' => 'D'],
        ['route' => 'reports.index', 'label' => __('klinic360.nav.reports'), 'icon' => 'R'],
        ['route' => 'notifications.index', 'label' => __('klinic360.nav.notifications'), 'icon' => 'N'],
        ['route' => 'ai.index', 'label' => __('klinic360.nav.ai'), 'icon' => 'AI'],
        ['route' => 'ai.board', 'label' => 'AI Board', 'icon' => '⚖'],
        ['route' => 'settings.index', 'label' => __('klinic360.nav.settings'), 'icon' => 'S'],
    ];
@endphp

<div class="flex flex-col h-full">
    <div class="px-5 py-5 border-b border-brand-600">
        <div class="flex items-center gap-2">
            <div class="w-9 h-9 rounded-lg bg-brand-500 text-white flex items-center justify-center font-bold text-sm">K360</div>
            <span class="font-bold text-lg">{{ __('klinic360.app_name') }}</span>
        </div>
    </div>
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        @foreach($nav as $item)
            @php $active = request()->routeIs($item['route']); @endphp
            @if(\Illuminate\Support\Facades\Route::has($item['route']))
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition {{ $active ? 'bg-brand-800 text-white' : 'text-brand-50 hover:bg-brand-600' }}">
                    <span class="w-6 text-center text-xs font-mono">{{ $item['icon'] }}</span>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endif
        @endforeach
    </nav>
    <div class="px-3 py-4 border-t border-brand-600">
        @auth
            <div class="px-3 py-2 text-sm text-brand-50 truncate">{{ auth()->user()->name ?? 'User' }}</div>
            <a href="{{ route('profile') }}" class="block w-full text-left px-3 py-2 rounded-md text-sm text-brand-50 hover:bg-brand-600">
                {{ __('klinic360.nav.profile') }}
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full text-left px-3 py-2 rounded-md text-sm text-brand-50 hover:bg-brand-600">
                    {{ __('klinic360.sign_out') }}
                </button>
            </form>
        @endauth
    </div>
</div>
