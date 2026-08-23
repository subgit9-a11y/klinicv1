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

            @case('timeline')
                <div>
                    @php $timeline = collect(); @endphp
                    @foreach($patient->appointments as $a)
                        @php $timeline->push(['date' => $a->appointment_date, 'label' => 'Appointment · '.$a->type, 'detail' => $a->status]); @endphp
                    @endforeach
                    @foreach($patient->consultations as $c)
                        @php $timeline->push(['date' => $c->created_at, 'label' => 'Consultation · '.$c->medicine_system, 'detail' => $c->chief_complaint ?: '']); @endphp
                    @endforeach
                    @foreach($patient->prescriptions as $p)
                        @php $timeline->push(['date' => $p->issued_at ?? $p->created_at, 'label' => 'Prescription #'.$p->id, 'detail' => $p->status]); @endphp
                    @endforeach
                    @php $sorted = $timeline->sortByDesc(fn($x) => $x['date']); @endphp
                    @forelse($sorted as $event)
                        <div class="border-l-2 border-brand-200 pl-4 pb-4">
                            <div class="text-xs text-gray-400">{{ $event['date']?->format('d M Y, H:i') }}</div>
                            <div class="text-sm font-medium text-gray-900">{{ $event['label'] }}</div>
                            @if($event['detail'])<div class="text-sm text-gray-500">{{ $event['detail'] }}</div>@endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('klinic360.no_records') }}</p>
                    @endforelse
                </div>
                @break

            @case('appointments')
                <div>
                    @forelse($patient->appointments as $appt)
                        <div class="flex items-center justify-between border border-gray-200 rounded-lg p-3 mb-2">
                            <div>
                                <div class="text-sm font-medium text-gray-900">{{ $appt->appointment_date?->format('d M Y') }} · {{ $appt->start_time }}</div>
                                <div class="text-xs text-gray-500">{{ $appt->type }} · {{ $appt->duration_minutes }} min</div>
                            </div>
                            <span class="px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-700">{{ $appt->status }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('klinic360.no_records') }}</p>
                    @endforelse
                </div>
                @break

            @case('opd')
                <div>
                    @forelse($patient->consultations as $consult)
                        <div class="border border-gray-200 rounded-lg p-4 mb-3">
                            <div class="flex items-center justify-between">
                                <div class="text-sm font-medium text-gray-900">{{ $consult->consultation_type }} · {{ $consult->medicine_system }}</div>
                                <span class="px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-700">{{ $consult->status }}</span>
                            </div>
                            @if($consult->chief_complaint)
                                <div class="text-sm text-gray-600 mt-2"><span class="font-semibold">Chief complaint:</span> {{ $consult->chief_complaint }}</div>
                            @endif
                            @if($consult->diagnosis_summary)
                                <div class="text-sm text-gray-600 mt-1"><span class="font-semibold">Diagnosis:</span> {{ $consult->diagnosis_summary }}</div>
                            @endif
                            <div class="text-xs text-gray-400 mt-2">{{ $consult->created_at?->format('d M Y, H:i') }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('klinic360.no_records') }}</p>
                    @endforelse
                </div>
                @break

            @case('prescriptions')
                <div>
                    @forelse($patient->prescriptions as $rx)
                        <div class="border border-gray-200 rounded-lg p-4 mb-3">
                            <div class="flex items-center justify-between">
                                <div class="text-sm font-medium text-gray-900">Prescription #{{ $rx->id }}</div>
                                <span class="px-2 py-1 rounded text-xs font-medium {{ $rx->status === 'ACTIVE' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $rx->status }}</span>
                            </div>
                            @if($rx->notes)<div class="text-sm text-gray-600 mt-2">{{ $rx->notes }}</div>@endif
                            <div class="text-xs text-gray-400 mt-2">Issued: {{ $rx->issued_at?->format('d M Y') ?? $rx->created_at?->format('d M Y') }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('klinic360.no_records') }}</p>
                    @endforelse
                </div>
                @break

            @case('treatments')
                <div>
                    @forelse($patient->treatmentBookings as $booking)
                        <div class="border border-gray-200 rounded-lg p-4 mb-3">
                            <div class="flex items-center justify-between">
                                <div class="text-sm font-medium text-gray-900">Treatment #{{ $booking->id }} · {{ $booking->booking_date?->format('d M Y') }}</div>
                                <span class="px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-700">{{ $booking->status }}</span>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">{{ $booking->start_time }}–{{ $booking->end_time }} · {{ $booking->payment_mode }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('klinic360.no_records') }}</p>
                    @endforelse
                </div>
                @break

            @case('ipd')
                <div>
                    @forelse($patient->ipdAdmissions as $admission)
                        <div class="border border-gray-200 rounded-lg p-4 mb-3">
                            <div class="flex items-center justify-between">
                                <div class="text-sm font-medium text-gray-900">Admission #{{ $admission->id }}</div>
                                <span class="px-2 py-1 rounded text-xs font-medium {{ $admission->status === 'DISCHARGED' ? 'bg-gray-100 text-gray-600' : 'bg-blue-100 text-blue-800' }}">{{ $admission->status }}</span>
                            </div>
                            @if($admission->provisional_diagnosis)
                                <div class="text-sm text-gray-600 mt-2">{{ $admission->provisional_diagnosis }}</div>
                            @endif
                            <div class="text-xs text-gray-400 mt-2">Admitted: {{ $admission->admitted_at?->format('d M Y, H:i') }}@if($admission->discharged_at) · Discharged: {{ $admission->discharged_at?->format('d M Y') }}@endif</div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('klinic360.no_records') }}</p>
                    @endforelse
                </div>
                @break

            @case('investigations')
                <div>
                    @forelse($patient->investigations as $inv)
                        <div class="border border-gray-200 rounded-lg p-4 mb-3">
                            <div class="flex items-center justify-between">
                                <div class="text-sm font-medium text-gray-900">{{ $inv->name }} <span class="text-gray-400">· {{ $inv->category }}</span></div>
                                <span class="px-2 py-1 rounded text-xs font-medium {{ $inv->status === 'COMPLETED' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">{{ $inv->status }}</span>
                            </div>
                            <div class="text-xs text-gray-400 mt-2">Requested: {{ $inv->requested_at?->format('d M Y') }}@if($inv->completed_at) · Completed: {{ $inv->completed_at?->format('d M Y') }}@endif</div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('klinic360.no_records') }}</p>
                    @endforelse
                </div>
                @break

            @case('documents')
                <div>
                    @forelse($patient->documents as $doc)
                        <div class="border border-gray-200 rounded-lg p-4 mb-3 flex items-center justify-between">
                            <div>
                                <div class="text-sm font-medium text-gray-900">{{ $doc->name }}</div>
                                <div class="text-xs text-gray-500">{{ $doc->type }} · {{ $doc->mime_type }} · {{ number_format($doc->size / 1024, 1) }} KB</div>
                            </div>
                            <a href="{{ route('documents.stream', ['path' => $doc->path]) }}" target="_blank" class="text-xs font-medium text-brand-600 hover:text-brand-800">View</a>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('klinic360.no_records') }}</p>
                    @endforelse
                </div>
                @break

            @case('followups')
                <div>
                    @forelse($patient->followups as $fu)
                        <div class="border border-gray-200 rounded-lg p-4 mb-3">
                            <div class="flex items-center justify-between">
                                <div class="text-sm font-medium text-gray-900">Due: {{ $fu->due_date?->format('d M Y') }}</div>
                                <span class="px-2 py-1 rounded text-xs font-medium {{ $fu->status === 'COMPLETED' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">{{ $fu->status }}</span>
                            </div>
                            @if($fu->instructions)<div class="text-sm text-gray-600 mt-2">{{ $fu->instructions }}</div>@endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('klinic360.no_records') }}</p>
                    @endforelse
                </div>
                @break

            @case('billing')
                <div>
                    @forelse($patient->invoices as $inv)
                        <div class="border border-gray-200 rounded-lg p-4 mb-3">
                            <div class="flex items-center justify-between">
                                <div class="text-sm font-medium text-gray-900">{{ $inv->invoice_number ?? 'INV-'.$inv->id }} <span class="text-gray-400">· {{ $inv->source }}</span></div>
                                <span class="px-2 py-1 rounded text-xs font-medium {{ $inv->status === 'PAID' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800' }}">{{ $inv->status }}</span>
                            </div>
                            <div class="text-sm text-gray-600 mt-2">
                                Total: {{ $inv->currency }} {{ number_format($inv->total_cents / 100, 2) }} · Due: {{ $inv->currency }} {{ number_format($inv->amount_due_cents / 100, 2) }}
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('klinic360.no_records') }}</p>
                    @endforelse
                </div>
                @break

            @case('payments')
                <div>
                    @forelse($patient->payments as $pay)
                        <div class="border border-gray-200 rounded-lg p-4 mb-3">
                            <div class="flex items-center justify-between">
                                <div class="text-sm font-medium text-gray-900">{{ $pay->payment_number ?? 'PAY-'.$pay->id }}</div>
                                <span class="px-2 py-1 rounded text-xs font-medium {{ $pay->status === 'SUCCESS' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">{{ $pay->status }}</span>
                            </div>
                            <div class="text-sm text-gray-600 mt-2">
                                {{ $pay->currency }} {{ number_format($pay->amount_cents / 100, 2) }} · {{ $pay->method }}@if($pay->gateway) · {{ $pay->gateway }}@endif
                            </div>
                            <div class="text-xs text-gray-400 mt-1">{{ $pay->created_at?->format('d M Y, H:i') }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('klinic360.no_records') }}</p>
                    @endforelse
                </div>
                @break

            @case('ai_summary')
                @can(\App\Services\Auth\Permissions::AI_USE)
                    <livewire:ai.summary-panel
                        context-type="patient"
                        :context-id="$patient->id"
                        :features="['patient_summary' => 'Summarize chart', 'followup_assistant' => 'Follow-up assistant']"
                        wire:key="ai-summary-patient-{{ $patient->id }}" />
                @endcan
                @php
                    $aiRequests = \App\Models\AiRequest::where('contextable_type', \App\Models\Patient::class)
                        ->where('contextable_id', $patient->id)
                        ->latest()->limit(10)->get();
                @endphp
                <div>
                    @forelse($aiRequests as $ai)
                        <div class="border border-gray-200 rounded-lg p-4 mb-3">
                            <div class="flex items-center justify-between">
                                <div class="text-sm font-medium text-gray-900">{{ $ai->feature?->key ?? 'AI' }} · {{ $ai->provider }}/{{ $ai->model }}</div>
                                <span class="px-2 py-1 rounded text-xs font-medium {{ $ai->output_status === 'APPROVED' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">{{ $ai->output_status }}</span>
                            </div>
                            @if($ai->output)<div class="text-sm text-gray-600 mt-2 whitespace-pre-line">{{ $ai->output }}</div>@endif
                            <div class="text-xs text-gray-400 mt-2">{{ $ai->created_at?->format('d M Y, H:i') }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('klinic360.no_records') }}</p>
                    @endforelse
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

            @case('abha')
                <div>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <dt class="text-gray-500">ABHA ID</dt>
                            <dd class="font-medium text-gray-900">{{ $patient->abha_id ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ __('klinic360.patients.phone') }}</dt>
                            <dd class="font-medium text-gray-900">{{ $patient->phone }}</dd>
                        </div>
                    </dl>
                    @if($patient->identifiers->isNotEmpty())
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mt-6 mb-3">Linked Identifiers</h3>
                        @foreach($patient->identifiers as $id)
                            <div class="border border-gray-200 rounded-lg p-3 mb-2">
                                <div class="text-sm font-medium text-gray-900">{{ $id->type ?? $id->identifier_type ?? 'ID' }}: {{ $id->value ?? $id->identifier_value ?? '—' }}</div>
                            </div>
                        @endforeach
                    @else
                        <p class="text-sm text-gray-500 mt-4">No linked identifiers.</p>
                    @endif
                </div>
                @break

            @default
                <div class="text-center py-12 text-gray-400">
                    <p class="text-sm">This section will be populated as the corresponding modules are built.</p>
                </div>
        @endswitch
    </div>
</x-layouts.app>
