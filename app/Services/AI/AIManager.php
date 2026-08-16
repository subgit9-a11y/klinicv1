<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiFeature;
use App\Models\AiPrompt;
use App\Models\AiPromptVersion;
use App\Models\AiRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Central AI orchestrator.
 *
 * Responsibilities:
 *  - Resolve the active prompt version for a given feature/system.
 *  - Build a minimal, privacy-safe context via AIContextBuilder.
 *  - Call the AI provider (Gemini).
 *  - Log every request in ai_requests with full token/cost audit.
 *  - ALWAYS store output as DRAFT — never auto-approve. Human review required.
 *
 * AI output is advisory only. The treating clinician is always the
 * final decision-maker.
 */
class AIManager
{
    public function __construct(
        private readonly GeminiProvider $provider,
        private readonly AIContextBuilder $contextBuilder,
    ) {}

    /**
     * Generate an AI draft for the given feature and context.
     *
     * @param  array<string, mixed>  $variables
     * @return AiRequest The recorded AI request (output_status = DRAFT or ERROR).
     */
    public function generate(
        string $featureKey,
        Model $contextable,
        array $variables = [],
        ?string $system = null
    ): AiRequest {
        $feature = AiFeature::where('key', $featureKey)->where('is_active', true)->first();
        $promptVersion = $this->resolvePromptVersion($feature?->default_prompt_key, $system);

        // Build the request record BEFORE the call so we always have an audit trail.
        $request = AiRequest::create([
            'user_id' => auth()->id(),
            'ai_feature_id' => $feature?->id,
            'ai_prompt_version_id' => $promptVersion?->id,
            'provider' => $this->provider->name(),
            'model' => $promptVersion?->default_model ?? $feature?->default_model,
            'contextable_type' => $contextable->getMorphClass(),
            'contextable_id' => $contextable->id,
            'input_summary' => $this->contextBuilder->summarize($contextable, $variables),
            'output_status' => 'PENDING',
            'status' => 'PENDING',
            'estimated_cost_cents' => 0,
        ]);

        if (!$this->provider->isConfigured()) {
            $request->update([
                'status' => 'ERROR',
                'output_status' => 'ERROR',
                'error' => 'AI provider not configured',
            ]);
            return $request->refresh();
        }

        if ($promptVersion === null) {
            $request->update([
                'status' => 'ERROR',
                'output_status' => 'ERROR',
                'error' => 'No active prompt version found',
            ]);
            return $request->refresh();
        }

        $prompt = $this->renderTemplate($promptVersion->user_prompt_template, $variables);
        $systemPrompt = $promptVersion->system_prompt;

        try {
            $result = $this->provider->complete($prompt, [
                'system' => $systemPrompt,
                'max_tokens' => 2048,
            ], $request->model);

            $inputTokens = $result['usage']['promptTokenCount'] ?? null;
            $outputTokens = $result['usage']['candidatesTokenCount'] ?? null;
            $costCents = $this->estimateCost($inputTokens, $outputTokens);

            $request->update([
                'output' => $result['content'],
                'output_status' => 'DRAFT',
                'status' => 'SUCCESS',
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'estimated_cost_cents' => $costCents,
                'duration_ms' => $result['duration_ms'],
            ]);
        } catch (\Throwable $e) {
            Log::error('AI generation failed', ['feature' => $featureKey, 'error' => $e->getMessage()]);

            $request->update([
                'status' => 'ERROR',
                'output_status' => 'ERROR',
                'error' => $e->getMessage(),
            ]);
        }

        return $request->refresh();
    }

    /**
     * Approve an AI draft (clinician review).
     */
    public function approve(AiRequest $aiRequest, int $userId): AiRequest
    {
        $aiRequest->update([
            'output_status' => 'APPROVED',
            'approved_at' => now(),
            'approved_by' => $userId,
        ]);

        return $aiRequest->refresh();
    }

    /**
     * Reject an AI draft.
     */
    public function reject(AiRequest $aiRequest, ?string $reason = null): AiRequest
    {
        $aiRequest->update([
            'output_status' => 'REJECTED',
            'error' => $reason,
        ]);

        return $aiRequest->refresh();
    }

    /**
     * Resolve the latest active prompt version for a key + system.
     */
    private function resolvePromptVersion(?string $promptKey, ?string $system): ?AiPromptVersion
    {
        if ($promptKey === null) {
            return null;
        }

        $query = AiPrompt::where('key', $promptKey)->where('is_active', true);
        if ($system !== null) {
            $query->where('system', $system);
        }
        $prompt = $query->first();

        return $prompt?->activeVersion();
    }

    /**
     * Render a prompt template by substituting {{variables}}.
     */
    private function renderTemplate(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }

        return $template;
    }

    /**
     * Rough cost estimate in paisa (cents). Gemini Flash is ~$0.075/M input,
     * $0.30/M output. We use a conservative blended rate.
     */
    private function estimateCost(?int $inputTokens, ?int $outputTokens): int
    {
        $inputCost = (int) (($inputTokens ?? 0) * 8 / 1000); // ~$0.08/M input
        $outputCost = (int) (($outputTokens ?? 0) * 24 / 1000); // ~$0.24/M output

        return $inputCost + $outputCost;
    }
}
