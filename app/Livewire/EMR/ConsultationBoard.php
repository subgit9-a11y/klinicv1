<?php

declare(strict_types=1);

namespace App\Livewire\EMR;

use App\Models\Consultation;
use App\Models\Patient;
use App\Models\User;
use App\Services\EMR\ConsultationService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * EMR consultation board: lists a patient's consultation history and lets a
 * doctor open a new consultation, edit SOAP + system-specific fields, add
 * diagnoses/notes, complete, and amend.
 */
class ConsultationBoard extends Component
{
    public ?int $patientId = null;
    public ?int $consultationId = null;
    public ?int $activeConsultationId = null;
    public string $medicineSystem = 'GENERAL';
    public string $consultationType = 'OPD';
    public string $chiefComplaint = '';
    public string $history = '';
    public string $examination = '';
    public string $assessment = '';
    public string $diagnosisSummary = '';
    public string $treatmentPlan = '';
    public string $advice = '';
    public string $followUpInstructions = '';
    public ?int $followUpDays = null;
    public array $systemSpecific = [];

    // Diagnosis inline form
    public string $dxName = '';
    public string $dxCode = '';
    public string $dxType = 'PRIMARY';
    public string $dxNotes = '';

    // Clinical note inline form
    public string $noteContent = '';
    public string $noteType = 'PROGRESS';

    // Amend modal
    public bool $showAmendModal = false;
    public string $amendReason = '';

    public function mount(?int $patientId = null): void
    {
        $this->patientId = $patientId;
    }

    public function updatedPatientId(): void
    {
        $this->resetForm();
        $this->activeConsultationId = null;
    }

    public function selectPatient(int $patientId): void
    {
        $this->patientId = $patientId;
        $this->resetForm();
        $this->activeConsultationId = null;
    }

    public function startConsultation(ConsultationService $service): void
    {
        $this->authorize('create', Consultation::class);

        $this->validate(['patientId' => ['required', 'integer']]);

        $consultation = $service->start([
            'patient_id' => $this->patientId,
            'medicine_system' => $this->medicineSystem,
            'consultation_type' => $this->consultationType,
        ], auth()->user());

        $this->loadConsultation($consultation);
    }

    public function openConsultation(int $id): void
    {
        $consultation = Consultation::with(['diagnoses', 'vitals.recordedBy', 'doctor', 'patient'])->find($id);
        if ($consultation) {
            $this->authorize('view', $consultation);
            $this->loadConsultation($consultation);
        }
    }

    public function saveDraft(ConsultationService $service): void
    {
        $consultation = $this->resolveConsultation();
        $this->authorize('update', $consultation);

        $service->update($consultation, $this->formAttributes(), auth()->user());
        $this->loadConsultation($consultation->fresh());
        session()->flash('emr-message', __('Saved.'));
    }

    public function completeConsultation(ConsultationService $service): void
    {
        $consultation = $this->resolveConsultation();
        $this->authorize('complete', $consultation);

        try {
            $service->update($consultation, $this->formAttributes(), auth()->user());
            $completed = $service->complete($consultation, auth()->user());
            $this->loadConsultation($completed);
            session()->flash('emr-message', __('Consultation completed.'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->addValidationErrors($e);
        }
    }

    public function openAmendModal(): void
    {
        $this->showAmendModal = true;
        $this->amendReason = '';
    }

    public function amendConsultation(ConsultationService $service): void
    {
        $consultation = $this->resolveConsultation();
        $this->authorize('amend', $consultation);

        $this->validate(['amendReason' => ['required', 'string', 'min:5']]);

        try {
            $amended = $service->amend($consultation, auth()->user(), $this->amendReason);
            $this->loadConsultation($amended);
            $this->showAmendModal = false;
            session()->flash('emr-message', __('Consultation amended.'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->addValidationErrors($e);
        }
    }

    public function addDiagnosis(ConsultationService $service): void
    {
        $consultation = $this->resolveConsultation();
        $this->authorize('manageDiagnoses', $consultation);

        $this->validate([
            'dxName' => ['required', 'string', 'max:255'],
            'dxCode' => ['nullable', 'string', 'max:64'],
            'dxType' => ['required', 'in:PRIMARY,SECONDARY,DIFFERENTIAL,PROVISIONAL'],
        ]);

        $service->addDiagnosis($consultation, [
            'name' => $this->dxName,
            'code' => $this->dxCode,
            'type' => $this->dxType,
            'notes' => $this->dxNotes,
        ], auth()->user());

        $this->reset(['dxName', 'dxCode', 'dxType', 'dxNotes']);
        $this->dxType = 'PRIMARY';
        $this->loadConsultation($consultation->fresh());
    }

    public function addNote(ConsultationService $service): void
    {
        $consultation = $this->resolveConsultation();

        $this->validate([
            'noteContent' => ['required', 'string'],
            'noteType' => ['required', 'in:PROGRESS,NURSING,OBSERVATION,OTHER'],
        ]);

        $service->addNote(
            $consultation->patient_id,
            $this->noteContent,
            auth()->user(),
            $this->noteType,
            $consultation->id,
        );

        $this->reset(['noteContent', 'noteType']);
        $this->noteType = 'PROGRESS';
    }

    public function render(): View
    {
        $patients = collect();
        $history = collect();
        $activeConsultation = null;

        $tenantId = app(TenantContext::class)->id();

        if ($tenantId !== null) {
            $patients = Patient::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('first_name')
                ->limit(100)
                ->get(['id', 'first_name', 'last_name', 'k360_uid']);

            if ($this->patientId !== null) {
                $history = Consultation::query()
                    ->where('patient_id', $this->patientId)
                    ->with(['doctor:id,name', 'diagnoses'])
                    ->orderByDesc('created_at')
                    ->limit(50)
                    ->get();

                if ($this->activeConsultationId !== null) {
                    $activeConsultation = Consultation::with(['diagnoses', 'vitals.recordedBy', 'doctor', 'patient'])
                        ->find($this->activeConsultationId);
                }
            }
        }

        return view('livewire.emr.consultation-board', [
            'patients' => $patients,
            'consultations' => $history,
            'activeConsultation' => $activeConsultation,
            'systems' => ['GENERAL', 'AYURVEDA', 'SIDDHA', 'HOMEOPATHY'],
            'types' => ['OPD', 'ONLINE', 'FOLLOW_UP', 'IPD'],
            'diagnosisTypes' => ['PRIMARY', 'SECONDARY', 'DIFFERENTIAL', 'PROVISIONAL'],
            'noteTypes' => ['PROGRESS', 'NURSING', 'OBSERVATION', 'OTHER'],
        ]);
    }

    private function resolveConsultation(): Consultation
    {
        $consultation = Consultation::find($this->activeConsultationId);
        if ($consultation === null) {
            abort(404, 'Consultation not found.');
        }

        return $consultation;
    }

    private function loadConsultation(Consultation $consultation): void
    {
        $this->activeConsultationId = $consultation->id;
        $this->medicineSystem = $consultation->medicine_system;
        $this->consultationType = $consultation->consultation_type;
        $this->chiefComplaint = $consultation->chief_complaint ?? '';
        $this->history = $consultation->history ?? '';
        $this->examination = $consultation->examination ?? '';
        $this->assessment = $consultation->assessment ?? '';
        $this->diagnosisSummary = $consultation->diagnosis_summary ?? '';
        $this->treatmentPlan = $consultation->treatment_plan ?? '';
        $this->advice = $consultation->advice ?? '';
        $this->followUpInstructions = $consultation->follow_up_instructions ?? '';
        $this->followUpDays = $consultation->follow_up_days;
        $this->systemSpecific = $consultation->system_specific ?? [];
    }

    private function resetForm(): void
    {
        $this->reset([
            'medicineSystem', 'consultationType', 'chiefComplaint', 'history',
            'examination', 'assessment', 'diagnosisSummary', 'treatmentPlan',
            'advice', 'followUpInstructions', 'followUpDays', 'systemSpecific',
            'dxName', 'dxCode', 'dxType', 'dxNotes', 'noteContent', 'noteType',
        ]);
        $this->medicineSystem = 'GENERAL';
        $this->consultationType = 'OPD';
        $this->dxType = 'PRIMARY';
        $this->noteType = 'PROGRESS';
    }

    private function formAttributes(): array
    {
        return [
            'chief_complaint' => $this->chiefComplaint,
            'history' => $this->history,
            'examination' => $this->examination,
            'assessment' => $this->assessment,
            'diagnosis_summary' => $this->diagnosisSummary,
            'treatment_plan' => $this->treatmentPlan,
            'advice' => $this->advice,
            'follow_up_instructions' => $this->followUpInstructions,
            'follow_up_days' => $this->followUpDays,
            'system_specific' => $this->systemSpecific ?: null,
            'medicine_system' => $this->medicineSystem,
            'consultation_type' => $this->consultationType,
        ];
    }

    private function addValidationErrors(\Illuminate\Validation\ValidationException $e): void
    {
        foreach ($e->validator->errors()->all() as $error) {
            $this->addError('emr', $error);
        }
    }
}
