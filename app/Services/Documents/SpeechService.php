<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Contracts\SpeechProviderInterface;
use App\Models\Document;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\Log;

/**
 * Speech-to-text orchestration service.
 *
 * Wraps the resolved SpeechProviderInterface implementation with audit logging.
 * Used for voice-dictated consultation notes: the doctor uploads an audio clip,
 * the service transcribes it to text that the clinician reviews before it
 * becomes part of the official record (mirrors the AI draft→approve invariant).
 *
 * When no provider is configured, the service reports the not-configured state
 * rather than faking success.
 */
class SpeechService
{
    public function __construct(
        private readonly SpeechProviderInterface $provider,
        private readonly AuditService $audit,
    ) {}

    public function providerName(): string
    {
        return $this->provider->name();
    }

    public function isConfigured(): bool
    {
        return $this->provider->isConfigured();
    }

    /**
     * Transcribe the audio file backing a stored Document.
     *
     * @return array{success: bool, text: string, message: string}
     */
    public function transcribeFromDocument(Document $document): array
    {
        $result = $this->provider->transcribe($document->disk, $document->path);

        if ($result['success']) {
            $metadata = is_array($document->metadata) ? $document->metadata : [];
            $metadata['transcription'] = $result['text'];
            $metadata['transcription_provider'] = $this->provider->name();
            $metadata['transcribed_at'] = now()->toIso8601String();
            $document->update(['metadata' => $metadata]);
        }

        $this->audit->record(
            $result['success'] ? 'document.speech_transcribed' : 'document.speech_transcribe_failed',
            'documents',
            ['after' => ['provider' => $this->provider->name(), 'success' => $result['success'], 'message' => $result['message'], 'chars' => strlen($result['text'])]],
            $document,
        );

        if (! $result['success']) {
            Log::info('Speech transcription did not succeed', ['document_id' => $document->id, 'message' => $result['message']]);
        }

        return $result;
    }
}
