<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Database\Eloquent\Model;

/**
 * Builds minimal, privacy-safe context for AI requests.
 *
 * Only sends the minimum data needed for the AI to produce a useful
 * draft. PII is kept to the clinical essentials — no addresses,
 * no contact numbers, no financial data is sent to the AI.
 */
class AIContextBuilder
{
    /**
     * Build a minimal context summary for the AI request audit log.
     *
     * @param  array<string, mixed>  $variables
     */
    public function summarize(Model $contextable, array $variables): string
    {
        $summary = match ($contextable->getMorphClass()) {
            'patient' => $this->summarizePatient($contextable),
            'consultation' => $this->summarizeConsultation($contextable),
            'ipd_admission' => $this->summarizeIpdAdmission($contextable),
            default => "Context: {$contextable->getMorphClass()} #{$contextable->id}",
        };

        // Append variable keys (not values) for audit.
        if (!empty($variables)) {
            $summary .= ' | Variables: ' . implode(', ', array_keys($variables));
        }

        return $summary;
    }

    private function summarizePatient(Model $patient): string
    {
        return "Patient #{$patient->id}"
            . (isset($patient->gender) ? " ({$patient->gender})" : '')
            . (isset($patient->system) ? " [{$patient->system}]" : '');
    }

    private function summarizeConsultation(Model $consultation): string
    {
        return "Consultation #{$consultation->id}"
            . (isset($consultation->patient_id) ? " for Patient #{$consultation->patient_id}" : '')
            . (isset($consultation->system) ? " [{$consultation->system}]" : '');
    }

    private function summarizeIpdAdmission(Model $admission): string
    {
        return "IPD Admission #{$admission->id}"
            . (isset($admission->patient_id) ? " for Patient #{$admission->patient_id}" : '');
    }
}
