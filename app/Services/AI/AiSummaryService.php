<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiRequest;
use App\Models\Document;
use App\Models\Followup;
use App\Models\Investigation;
use App\Models\IpdAdmission;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Dedicated AI summary products. Each method builds a privacy-safe variable
 * set (no phone/address/financials) and delegates to AIManager, which always
 * stores output as DRAFT — practitioner approval is required before any
 * summary becomes part of the official record.
 */
class AiSummaryService
{
    public function __construct(private readonly AIManager $ai) {}

    /**
     * Whole-patient chart summary: demographics, recent consultations,
     * diagnoses and active prescriptions.
     */
    public function summarizePatient(Patient $patient): AiRequest
    {
        $this->assertSameTenant($patient);

        $consultations = $patient->consultations()->latest()->limit(5)->get();

        return $this->ai->generate('patient_summary', $patient, [
            'patient_name' => $patient->name,
            'patient_meta' => trim(($patient->gender ?? '').' '.($patient->date_of_birth ? 'DOB '.$patient->date_of_birth : '')),
            'history' => $consultations->map(fn ($c) => implode(' | ', array_filter([
                $c->created_at?->format('d M Y'),
                $c->chief_complaint,
                $c->assessment,
            ])))->implode("\n") ?: 'No prior consultations.',
        ]);
    }

    /**
     * Follow-up assistant: drafts a follow-up plan from pending/missed
     * follow-ups and the latest consultation context.
     */
    public function followupAssistant(Patient $patient): AiRequest
    {
        $this->assertSameTenant($patient);

        $followups = Followup::where('patient_id', $patient->id)
            ->orderByDesc('due_date')->limit(5)->get();
        $lastConsultation = $patient->consultations()->latest()->first();

        return $this->ai->generate('followup_assistant', $patient, [
            'patient_name' => $patient->name,
            'last_consultation' => $lastConsultation
                ? ($lastConsultation->created_at?->format('d M Y').' — '.($lastConsultation->assessment ?? $lastConsultation->chief_complaint ?? 'no notes'))
                : 'None',
            'followups' => $followups->map(fn ($f) => "{$f->due_date->format('d M Y')} [{$f->status}] ".($f->instructions ?? ''))->implode("\n") ?: 'No follow-ups scheduled.',
        ]);
    }

    /**
     * Lab summary: interprets structured results + OCR'd report text for an
     * investigation.
     */
    public function summarizeInvestigation(Investigation $investigation): AiRequest
    {
        $this->assertSameTenant($investigation);

        $results = $investigation->results()->get();
        $reportText = Document::where('metadata->investigation_id', $investigation->id)
            ->get(['metadata'])
            ->map(fn ($d) => $d->metadata['ocr_text'] ?? null)
            ->filter()
            ->implode("\n---\n");

        return $this->ai->generate('lab_summary', $investigation, [
            'test_name' => $investigation->name,
            'category' => $investigation->category,
            'results' => $results->map(fn ($r) => "{$r->parameter}: {$r->value} {$r->unit} (ref {$r->reference_range}) [{$r->flag}]")->implode("\n") ?: 'No structured results.',
            'report_text' => $reportText !== '' ? $reportText : 'No report text available.',
        ]);
    }

    /**
     * Document summary: summarizes OCR-extracted text of a document.
     */
    public function summarizeDocument(Document $document): AiRequest
    {
        $this->assertSameTenant($document);

        return $this->ai->generate('document_summary', $document, [
            'document_name' => $document->name,
            'document_type' => $document->type,
            'ocr_text' => $document->metadata['ocr_text'] ?? 'No OCR text available — run OCR first.',
        ]);
    }

    /**
     * Treatment plan summary: sessions, services and progress.
     */
    public function summarizeTreatmentPlan(TreatmentPlan $plan): AiRequest
    {
        $this->assertSameTenant($plan);

        $bookings = \App\Models\TreatmentBooking::where('treatment_plan_id', $plan->id)
            ->orderByDesc('booking_date')->limit(10)->get();
        $patientName = Patient::find($plan->patient_id)?->name ?? "Patient #{$plan->patient_id}";

        return $this->ai->generate('treatment_summary', $plan, [
            'patient_name' => $patientName,
            'plan' => ($plan->name ?? 'Treatment plan').' — '.($plan->description ?? 'no description')
                ." ({$plan->completed_sessions}/{$plan->total_sessions} sessions, {$plan->status})",
            'sessions' => $bookings->map(fn ($b) => ($b->booking_date?->format('d M Y') ?? '?').' ['.$b->status.']')->implode("\n") ?: 'No sessions yet.',
        ]);
    }

    /**
     * IPD admission summary: course in hospital from daily notes.
     */
    public function summarizeAdmission(IpdAdmission $admission): AiRequest
    {
        $this->assertSameTenant($admission);

        $notes = $admission->dailyNotes()->orderByDesc('note_date')->limit(10)->get();

        return $this->ai->generate('ipd_summary', $admission, [
            'patient_name' => $admission->patient?->name ?? "Patient #{$admission->patient_id}",
            'admission' => $admission->ipd_number.' admitted '.$admission->admitted_at?->format('d M Y').' — '.($admission->admission_reason ?? ''),
            'daily_notes' => $notes->map(fn ($n) => ($n->note_date?->format('d M Y') ?? '?').': '.$n->content)->implode("\n") ?: 'No daily notes.',
        ]);
    }

    private function assertSameTenant(Model $model): void
    {
        $tenantId = app(TenantContext::class)->id();
        if ($tenantId !== null && $model->tenant_id !== null && $model->tenant_id !== $tenantId) {
            throw ValidationException::withMessages(['tenant' => 'Record belongs to a different tenant.']);
        }
    }
}
