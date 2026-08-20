<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('klinic360.emr.title') }}</h1>
    </div>

    @if(session('emr-message'))
        <div class="rounded-md bg-green-50 p-3 text-sm text-green-700 border border-green-200">{{ session('emr-message') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {{-- Patient selector + history --}}
        <div class="lg:col-span-1 space-y-4">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('klinic360.emr.patient') }}</label>
                <select wire:model.live="patientId" class="w-full rounded-md border-gray-300 text-sm">
                    <option value="">{{ __('klinic360.emr.select_patient') }}</option>
                    @foreach($patients as $p)
                        <option value="{{ $p->id }}">{{ $p->first_name }} {{ $p->last_name }} ({{ $p->uid }})</option>
                    @endforeach
                </select>
            </div>

            @if($patientId)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">{{ __('klinic360.emr.consultation_history') }}</h3>
                    @forelse($consultations as $c)
                        <button wire:click="openConsultation({{ $c->id }})"
                                class="w-full text-left p-2 rounded-md text-xs hover:bg-gray-50 border {{ $c->id === $activeConsultationId ? 'border-brand-500 bg-brand-50' : 'border-transparent' }}">
                            <div class="font-medium text-gray-800">{{ $c->doctor?->name ?? '—' }}</div>
                            <div class="text-gray-500">{{ $c->created_at->format('Y-m-d H:i') }}</div>
                            <span class="inline-block mt-0.5 px-1.5 py-0.5 rounded text-[10px] font-medium
                                @if($c->status === 'COMPLETED') bg-green-100 text-green-700
                                @elseif($c->status === 'DRAFT') bg-amber-100 text-amber-700
                                @else bg-blue-100 text-blue-700 @endif">
                                {{ __('klinic360.emr.'.strtolower($c->status)) }}
                            </span>
                            <span class="ml-1 text-[10px] text-gray-400">{{ $c->medicine_system }}</span>
                        </button>
                    @empty
                        <p class="text-xs text-gray-400 py-2">{{ __('klinic360.emr.no_consultations') }}</p>
                    @endforelse

                    @can('create', \App\Models\Consultation::class)
                        <div class="mt-3 pt-3 border-t border-gray-100">
                            <div class="space-y-2">
                                <select wire:model="medicineSystem" class="w-full rounded-md border-gray-300 text-xs" @if($activeConsultationId) disabled @endif>
                                    @foreach($systems as $s)
                                        <option value="{{ $s }}">{{ $s }}</option>
                                    @endforeach
                                </select>
                                <select wire:model="consultationType" class="w-full rounded-md border-gray-300 text-xs" @if($activeConsultationId) disabled @endif>
                                    @foreach($types as $t)
                                        <option value="{{ $t }}">{{ $t }}</option>
                                    @endforeach
                                </select>
                                <button wire:click="startConsultation" @if($activeConsultationId) disabled @endif
                                        class="w-full px-3 py-1.5 bg-brand-600 hover:bg-brand-700 text-white text-xs font-medium rounded-md transition disabled:opacity-50">
                                    {{ __('klinic360.emr.start_consultation') }}
                                </button>
                            </div>
                        </div>
                    @endcan
                </div>
            @endif
        </div>

        {{-- Active consultation form --}}
        <div class="lg:col-span-3">
            @if($activeConsultation)
                @php $c = $activeConsultation; @endphp
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-5">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ $c->patient?->first_name }} {{ $c->patient?->last_name }}</h2>
                            <p class="text-xs text-gray-500">{{ __('klinic360.emr.doctor') }}: {{ $c->doctor?->name }} · {{ $c->medicine_system }} · {{ $c->consultation_type }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded text-xs font-medium
                                @if($c->status === 'COMPLETED') bg-green-100 text-green-700
                                @elseif($c->status === 'DRAFT') bg-amber-100 text-amber-700
                                @else bg-blue-100 text-blue-700 @endif">
                                {{ __('klinic360.emr.'.strtolower($c->status)) }}
                            </span>
                            @if($c->status === 'COMPLETED')
                                @can('amend', $c)
                                    <button wire:click="openAmendModal" class="px-2 py-0.5 text-xs border border-gray-300 rounded hover:bg-gray-50">{{ __('klinic360.emr.amend') }}</button>
                                @endcan
                            @endif
                        </div>
                    </div>

                    @if($c->status === 'DRAFT')
                        @can('update', $c)
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('klinic360.emr.chief_complaint') }}</label>
                                    <textarea wire:model="chiefComplaint" rows="2" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('klinic360.emr.history') }}</label>
                                    <textarea wire:model="history" rows="2" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('klinic360.emr.examination') }}</label>
                                    <textarea wire:model="examination" rows="2" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('klinic360.emr.assessment') }}</label>
                                    <textarea wire:model="assessment" rows="2" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('klinic360.emr.diagnosis_summary') }}</label>
                                    <textarea wire:model="diagnosisSummary" rows="2" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('klinic360.emr.treatment_plan') }}</label>
                                    <textarea wire:model="treatmentPlan" rows="2" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('klinic360.emr.advice') }}</label>
                                    <textarea wire:model="advice" rows="2" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('klinic360.emr.follow_up_days') }}</label>
                                    <input type="number" wire:model="followUpDays" min="0" max="365" class="w-full rounded-md border-gray-300 text-sm">
                                    <label class="block text-xs font-medium text-gray-500 mb-1 mt-2">{{ __('klinic360.emr.follow_up_instructions') }}</label>
                                    <textarea wire:model="followUpInstructions" rows="1" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                                </div>
                            </div>

                            {{-- System-specific fields --}}
                            @if($medicineSystem !== 'GENERAL')
                                <div class="border-t border-gray-100 pt-4">
                                    <h3 class="text-sm font-semibold text-gray-700 mb-2">{{ __('klinic360.emr.system_specific') }} — {{ $medicineSystem }}</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                        @if($medicineSystem === 'AYURVEDA')
                                            @foreach(['prakriti','vikriti','dosha','srotas','agni','ama','samprapti','rogi_pareeksha','roga_pareeksha'] as $field)
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-500">{{ __("klinic360.emr.ayurveda.$field") }}</label>
                                                    <input type="text" wire:model="systemSpecific.{{ $field }}" class="w-full rounded-md border-gray-300 text-sm">
                                                </div>
                                            @endforeach
                                        @elseif($medicineSystem === 'SIDDHA')
                                            @foreach(['mukkutram','udal_thathukkal','naadi','neerkuri','neikuri','envagai_thervu'] as $field)
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-500">{{ __("klinic360.emr.siddha.$field") }}</label>
                                                    <input type="text" wire:model="systemSpecific.{{ $field }}" class="w-full rounded-md border-gray-300 text-sm">
                                                </div>
                                            @endforeach
                                        @elseif($medicineSystem === 'HOMEOPATHY')
                                            @foreach(['symptoms','modalities','constitution','repertory','remedy','potency','dose'] as $field)
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-500">{{ __("klinic360.emr.homeopathy.$field") }}</label>
                                                    <input type="text" wire:model="systemSpecific.{{ $field }}" class="w-full rounded-md border-gray-300 text-sm">
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- Inline diagnosis form --}}
                            <div class="border-t border-gray-100 pt-4">
                                <h3 class="text-sm font-semibold text-gray-700 mb-2">{{ __('klinic360.emr.add_diagnosis') }}</h3>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
                                    <input type="text" wire:model="dxName" placeholder="Name" class="rounded-md border-gray-300 text-sm">
                                    <input type="text" wire:model="dxCode" placeholder="Code (ICD-10 / system)" class="rounded-md border-gray-300 text-sm">
                                    <select wire:model="dxType" class="rounded-md border-gray-300 text-sm">
                                        @foreach($diagnosisTypes as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                                    </select>
                                    <button wire:click="addDiagnosis" class="px-3 py-1.5 bg-gray-800 hover:bg-gray-900 text-white text-xs font-medium rounded-md">{{ __('klinic360.emr.add_diagnosis') }}</button>
                                </div>
                            </div>

                            {{-- Inline note form --}}
                            <div class="border-t border-gray-100 pt-4">
                                <h3 class="text-sm font-semibold text-gray-700 mb-2">{{ __('klinic360.emr.notes') }}</h3>
                                <div class="flex gap-2">
                                    <select wire:model="noteType" class="rounded-md border-gray-300 text-sm w-40">
                                        @foreach($noteTypes as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                                    </select>
                                    <input type="text" wire:model="noteContent" placeholder="Note…" class="flex-1 rounded-md border-gray-300 text-sm">
                                    <button wire:click="addNote" class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-800 text-xs font-medium rounded-md">{{ __('klinic360.emr.add_note') }}</button>
                                </div>
                            </div>

                            <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                                <button wire:click="saveDraft" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('klinic360.emr.save_draft') }}</button>
                                <button wire:click="completeConsultation" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-md text-sm font-medium">{{ __('klinic360.emr.complete') }}</button>
                            </div>
                        @endcan
                    @else
                        {{-- Read-only completed/amended view --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div><span class="text-gray-500">{{ __('klinic360.emr.chief_complaint') }}:</span> {{ $c->chief_complaint ?: '—' }}</div>
                            <div><span class="text-gray-500">{{ __('klinic360.emr.history') }}:</span> {{ $c->history ?: '—' }}</div>
                            <div><span class="text-gray-500">{{ __('klinic360.emr.examination') }}:</span> {{ $c->examination ?: '—' }}</div>
                            <div><span class="text-gray-500">{{ __('klinic360.emr.assessment') }}:</span> {{ $c->assessment ?: '—' }}</div>
                            <div><span class="text-gray-500">{{ __('klinic360.emr.diagnosis_summary') }}:</span> {{ $c->diagnosis_summary ?: '—' }}</div>
                            <div><span class="text-gray-500">{{ __('klinic360.emr.treatment_plan') }}:</span> {{ $c->treatment_plan ?: '—' }}</div>
                            <div><span class="text-gray-500">{{ __('klinic360.emr.advice') }}:</span> {{ $c->advice ?: '—' }}</div>
                            <div><span class="text-gray-500">{{ __('klinic360.emr.follow_up_days') }}:</span> {{ $c->follow_up_days ?: '—' }}</div>
                        </div>

                        @if($c->diagnoses->isNotEmpty())
                            <div class="border-t border-gray-100 pt-3">
                                <h3 class="text-sm font-semibold text-gray-700 mb-1">{{ __('klinic360.emr.diagnoses') }}</h3>
                                <ul class="text-sm space-y-0.5">
                                    @foreach($c->diagnoses as $dx)
                                        <li><span class="text-xs px-1.5 py-0.5 rounded bg-gray-100">{{ $dx->type }}</span> {{ $dx->name }} @if($dx->code) <span class="text-gray-400">({{ $dx->code }})</span>@endif</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endif
                </div>
            @else
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center text-gray-400">
                    {{ __('klinic360.emr.select_patient') }}
                </div>
            @endif
        </div>
    </div>

    {{-- Amend modal --}}
    @if($showAmendModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('klinic360.emr.amend') }}</h3>
                <p class="text-xs text-gray-500 mb-3">{{ __('klinic360.emr.amend_reason') }}</p>
                <textarea wire:model="amendReason" rows="3" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                <div class="flex justify-end gap-2 mt-3">
                    <button wire:click="$set('showAmendModal', false)" class="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-50">Cancel</button>
                    <button wire:click="amendConsultation" class="px-3 py-1.5 text-sm bg-brand-600 hover:bg-brand-700 text-white rounded-md">{{ __('klinic360.emr.amend') }}</button>
                </div>
            </div>
        </div>
    @endif

    {{-- AI Scribe assistant, bound into the active consultation --}}
    @if($this->activeConsultationId)
        <div class="lg:col-span-3">
            <livewire:emr.ai-scribe-panel :consultation="\App\Models\Consultation::find($this->activeConsultationId)" wire:key="scribe-{{ $this->activeConsultationId }}" />
        </div>
    @endif
</div>
