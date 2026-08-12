<x-layouts.app sidebar :title="__('klinic360.patients.title')" :subtitle="$patient->k360_uid">
    <x-patient.header :patient="$patient" :active-tab="$activeTab" />

    <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        @switch($activeTab)
            @case('overview')
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2">
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">
                            {{ __('klinic360.patient360.demographics') }}
                        </h3>
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                            <div>
                                <dt class="text-gray-500">{{ __('klinic360.patients.name') }}</dt>
                                <dd class="font-medium text-gray-900">{{ $patient->fullName() }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">{{ __('klinic360.patients.uid') }}</dt>
                                <dd class="font-mono font-medium text-gray-900">{{ $patient->k360_uid }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">{{ __('klinic360.patients.phone') }}</dt>
                                <dd class="font-medium text-gray-900">{{ $patient->phone }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">{{ __('klinic360.patients.email') }}</dt>
                                <dd class="font-medium text-gray-900">{{ $patient->email ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">{{ __('klinic360.patients.gender') }}</dt>
                                <dd class="font-medium text-gray-900">{{ $patient->gender }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">{{ __('klinic360.patients.dob') }}</dt>
                                <dd class="font-medium text-gray-900">{{ $patient->dob?->format('d M Y') ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">{{ __('klinic360.patients.abha_id') }}</dt>
                                <dd class="font-medium text-gray-900">{{ $patient->abha_id ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">{{ __('klinic360.patients.blood_group') }}</dt>
                                <dd class="font-medium text-gray-900">{{ $patient->blood_group ?: '—' }}</dd>
                            </div>
                        </dl>
                        @if($patient->address)
                            <div class="mt-4 text-sm">
                                <dt class="text-gray-500">{{ __('klinic360.patients.address') }}</dt>
                                <dd class="font-medium text-gray-900">
                                    {{ $patient->address }}{{ $patient->city ? ', '.$patient->city : '' }}{{ $patient->state ? ', '.$patient->state : '' }}{{ $patient->pincode ? ' '.$patient->pincode : '' }}
                                </dd>
                            </div>
                        @endif
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">
                            {{ __('klinic360.patient360.clinical_flags') }}
                        </h3>
                        <div class="space-y-3">
                            <div class="rounded-md bg-red-50 border border-red-200 p-3">
                                <div class="text-xs font-semibold text-red-700">{{ __('klinic360.patients.allergies') }}</div>
                                <div class="text-sm text-red-900 mt-1">{{ $patient->allergies ?: 'None recorded' }}</div>
                            </div>
                            <div class="rounded-md bg-orange-50 border border-orange-200 p-3">
                                <div class="text-xs font-semibold text-orange-700">{{ __('klinic360.patients.chronic_conditions') }}</div>
                                <div class="text-sm text-orange-900 mt-1">{{ $patient->chronic_conditions ?: 'None recorded' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                @break

            @case('consent')
                <div>
                    @forelse($patient->consents as $consent)
                        <div class="border border-gray-200 rounded-lg p-4 mb-3 flex items-center justify-between">
                            <div>
                                <div class="font-medium text-gray-900">{{ $consent->consent_type }}</div>
                                <div class="text-sm text-gray-500">{{ $consent->description ?: '' }}</div>
                            </div>
                            <span class="px-2 py-1 rounded text-xs font-medium {{ $consent->granted ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                {{ $consent->granted ? 'Granted' : 'Not granted' }}
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('klinic360.patient360.no_consent') }}</p>
                    @endforelse
                </div>
                @break

            @default
                <div class="text-center py-12 text-gray-400">
                    <p class="text-sm">This section will be populated as the corresponding modules are built.</p>
                </div>
        @endswitch
    </div>
</x-layouts.app>
