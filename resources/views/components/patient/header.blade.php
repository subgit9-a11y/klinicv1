@php
/**
 * @var \App\Models\Patient $patient
 * @var string $activeTab
 */
$tabs = [
    ['key' => 'overview', 'label' => __('klinic360.patient360.overview')],
    ['key' => 'timeline', 'label' => __('klinic360.patient360.timeline')],
    ['key' => 'appointments', 'label' => __('klinic360.patient360.appointments')],
    ['key' => 'opd', 'label' => __('klinic360.patient360.opd')],
    ['key' => 'prescriptions', 'label' => __('klinic360.patient360.prescriptions')],
    ['key' => 'treatments', 'label' => __('klinic360.patient360.treatments')],
    ['key' => 'ipd', 'label' => __('klinic360.patient360.ipd')],
    ['key' => 'investigations', 'label' => __('klinic360.patient360.investigations')],
    ['key' => 'documents', 'label' => __('klinic360.patient360.documents')],
    ['key' => 'followups', 'label' => __('klinic360.patient360.followups')],
    ['key' => 'billing', 'label' => __('klinic360.patient360.billing')],
    ['key' => 'payments', 'label' => __('klinic360.patient360.payments')],
    ['key' => 'ai_summary', 'label' => __('klinic360.patient360.ai_summary')],
    ['key' => 'consent', 'label' => __('klinic360.patient360.consent')],
    ['key' => 'abha', 'label' => __('klinic360.patient360.abha')],
];
$activeTab = $activeTab ?? 'overview';
$age = $patient->dob ? \Illuminate\Support\Carbon::parse($patient->dob)->age : null;
@endphp

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="bg-gradient-to-r from-brand-700 to-brand-500 px-6 py-5 text-white">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-full bg-white/20 flex items-center justify-center text-xl font-bold">
                    {{ strtoupper(substr($patient->first_name, 0, 1)) }}
                </div>
                <div>
                    <h1 class="text-xl font-bold">{{ $patient->fullName() }}</h1>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-brand-50">
                        <span class="font-mono">{{ $patient->k360_uid }}</span>
                        @if($patient->phone)
                            <span>· {{ $patient->phone }}</span>
                        @endif
                        @if($patient->gender && $patient->gender !== 'UNKNOWN')
                            <span>· {{ __('klinic360.patients.gender').': '.$patient->gender }}</span>
                        @endif
                        @if($age !== null)
                            <span>· {{ $age }} yrs</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @can('patients.edit', $patient)
                    <a href="{{ route('patients.edit', $patient) }}"
                       class="px-3 py-1.5 rounded-md bg-white/15 hover:bg-white/25 text-sm font-medium transition">
                        {{ __('klinic360.patients.edit') }}
                    </a>
                @endcan
            </div>
        </div>
    </div>

    @if($patient->allergies || $patient->chronic_conditions)
        <div class="bg-amber-50 border-b border-amber-200 px-6 py-3">
            <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                @if($patient->allergies)
                    <div class="flex items-center gap-2">
                        <span class="text-amber-700 font-medium">⚠ Allergies:</span>
                        <span class="text-amber-900">{{ $patient->allergies }}</span>
                    </div>
                @endif
                @if($patient->chronic_conditions)
                    <div class="flex items-center gap-2">
                        <span class="text-amber-700 font-medium">Chronics:</span>
                        <span class="text-amber-900">{{ $patient->chronic_conditions }}</span>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="border-b border-gray-200 bg-gray-50 px-2 overflow-x-auto">
        <nav class="flex gap-1 min-w-max">
            @foreach($tabs as $tab)
                <button type="button"
                        wire:click="setTab('{{ $tab['key'] }}')"
                        class="px-4 py-3 text-sm font-medium border-b-2 transition whitespace-nowrap
                        {{ $activeTab === $tab['key'] ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </nav>
    </div>
</div>
