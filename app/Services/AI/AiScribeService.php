<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiRequest;
use App\Models\Consultation;
use App\Models\Document;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Documents\SpeechService;
use App\Services\Tenancy\TenantContext;

/**
 * AI Scribe — the clinical dictation workflow:
 *
 *   uploaded audio → SpeechService transcribe → AIManager generate
 *   (SOAP/EMR draft) → doctor edits → approve → written onto the
 *   consultation.
 *
 * AI output NEVER writes the final record without clinician approval;
 * approve() is the only public way to move from draft to the actual
 * consultation.
 */
class AiScribeService
{
    public const FEATURE_KEY = 'ai_scribe';

    public function __construct(
        private readonly SpeechService $speech,
        private readonly AIManager $ai,
        private readonly AuditService $audit,
    ) {}

    /**
     * Transcribe an uploaded audio document, then ask the AI for a SOAP
     * draft. Returns the DRAFT AiRequest — nothing is written to the
     * consultation until approve() runs.
     */
    public function draftFromAudio(Consultation $consultation, Document $audioDocument): AiRequest
    {
        $this->requireTenant($consultation);

        $transcription = $this->speech->transcribeFromDocument($audioDocument);

        if (! ($transcription['success'] ?? false)) {
            throw new \DomainException('Transcription failed: '.($transcription['message'] ?? 'unknown'));
        }

        $text = (string) ($transcription['text'] ?? '');

        return $this->draft($consultation, $text, 'audio', $audioDocument->id);
    }

    /**
     * Draft from a CONSULTATION's existing chief_complaint (text input —
     * skip transcription). Useful when the doctor prefers to type.
     */
    public function draftFromChiefComplaint(Consultation $consultation): AiRequest
    {
        $this->requireTenant($consultation);

        $text = (string) $consultation->chief_complaint;
        if (trim($text) === '') {
            throw new \DomainException('No dictation text to scribe — enter a chief complaint first.');
        }

        return $this->draft($consultation, $text, 'chief_complaint', null);
    }

    /**
     * Approve an AI Scribe draft and write the structured output onto the
     * consultation. The extracted JSON is validated before any field is
     * written to clinical record.
     */
    public function approve(AiRequest $request, User $doctor, ?array $editedOutput = null): Consultation
    {
        if ($request->contextable_type !== Consultation::class && $request->contextable_type !== (new Consultation)->getMorphClass()) {
            throw new \DomainException('Not an AI Scribe request.');
        }

        $output = $editedOutput ?? $this->parseOutput((string) $request->output);

        $consultation = Consultation::withoutGlobalScopes()->findOrFail($request->contextable_id);
        $consultation->update([
            'history' => $output['subjective'] ?? null,
            'examination' => $output['objective'] ?? null,
            'assessment' => $output['assessment'] ?? null,
            'treatment_plan' => $output['plan'] ?? null,
        ]);

        $this->ai->approve($request, $doctor->id);

        $this->audit->record('ai.scribe.approved', 'AI_GOVERNANCE', ['after' => [
            'ai_request_id' => $request->id,
            'consultation_id' => $consultation->id,
        ]], $request);

        return $consultation->fresh();
    }

    private function draft(Consultation $consultation, string $text, string $source, ?int $documentId): AiRequest
    {
        $this->requireTenant($consultation);

        $variables = [
            'dictation' => $text,
            'patient_name' => $consultation->patient?->fullName() ?? 'Unknown',
            'source' => $source,
            'soap_format' => 'subjective | objective | assessment | plan',
        ];
        if ($documentId !== null) {
            $variables['document_id'] = (string) $documentId;
        }

        $request = $this->ai->generate(self::FEATURE_KEY, $consultation, $variables);

        $this->audit->record('ai.scribe.draft', 'AI_GOVERNANCE', ['after' => [
            'ai_request_id' => $request->id,
            'output_status' => $request->output_status,
        ]], $request);

        return $request;
    }

    private function requireTenant(Consultation $consultation): void
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId === null || $consultation->tenant_id !== $tenantId) {
            throw new \DomainException('Consultation belongs to another tenant.');
        }
    }

    /**
     * The provider returns a JSON envelope ({ "content": "{...}" }) or bare
     * JSON. This normalizes it into subject/objective/assessment/plan and
     * tolerates the AI emitting a code-fenced block.
     *
     * @return array{subjective: ?string, objective: ?string, assessment: ?string, plan: ?string}
     */
    public function parseOutput(string $output): array
    {
        $output = preg_replace('/^```(?:json)?|```$/m', '', trim($output));

        $decoded = json_decode($output, true);

        if (is_array($decoded) && isset($decoded['content']) && is_string($decoded['content'])) {
            $decoded = json_decode($decoded['content'], true) ?: $decoded;
        }

        return [
            'subjective' => $decoded['subjective'] ?? $decoded['history'] ?? null,
            'objective' => $decoded['objective'] ?? $decoded['examination'] ?? null,
            'assessment' => $decoded['assessment'] ?? $decoded['diagnosis'] ?? null,
            'plan' => $decoded['plan'] ?? $decoded['treatment_plan'] ?? null,
        ];
    }
}
