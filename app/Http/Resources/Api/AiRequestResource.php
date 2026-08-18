<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of an AI request (governance record).
 *
 * Exposes the draft output and its approval status so a practitioner can
 * review and approve/reject via the API. The `output` field carries the
 * AI-generated draft; `output_status` tracks DRAFT → APPROVED / REJECTED.
 */
class AiRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'feature_id' => $this->ai_feature_id,
            'prompt_version_id' => $this->ai_prompt_version_id,
            'provider' => $this->provider,
            'model' => $this->model,
            'contextable_type' => $this->contextable_type,
            'contextable_id' => $this->contextable_id,
            'input_summary' => $this->input_summary,
            'output' => $this->output,
            'output_status' => $this->output_status,
            'status' => $this->status,
            'error' => $this->error,
            'input_tokens' => $this->input_tokens,
            'output_tokens' => $this->output_tokens,
            'estimated_cost_cents' => $this->estimated_cost_cents,
            'duration_ms' => $this->duration_ms,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'approved_by' => $this->approved_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'is_draft' => $this->output_status === 'DRAFT',
            'is_approved' => $this->output_status === 'APPROVED',
        ];
    }
}
