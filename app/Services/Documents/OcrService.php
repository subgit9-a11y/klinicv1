<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Contracts\OCRProviderInterface;
use App\Models\Document;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\Log;

/**
 * OCR orchestration service.
 *
 * Wraps the resolved OCRProviderInterface implementation with tenant context
 * and audit logging. Used to digitise lab reports / prescription images into
 * searchable text that attaches to the owning Document's metadata.
 *
 * When no provider is configured, the service reports the not-configured
 * state rather than faking success — preserving the "never fake integrations"
 * constraint.
 */
class OcrService
{
    public function __construct(
        private readonly OCRProviderInterface $provider,
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
     * Extract text from a stored Document's underlying file.
     *
     * @return array{success: bool, text: string, message: string}
     */
    public function extractFromDocument(Document $document): array
    {
        $result = $this->provider->extract($document->disk, $document->path);

        if ($result['success']) {
            // Persist the extracted text onto the document metadata for later search.
            $metadata = is_array($document->metadata) ? $document->metadata : [];
            $metadata['ocr_text'] = $result['text'];
            $metadata['ocr_provider'] = $this->provider->name();
            $metadata['ocr_extracted_at'] = now()->toIso8601String();
            $document->update(['metadata' => $metadata]);
        }

        $this->audit->record(
            $result['success'] ? 'document.ocr_extracted' : 'document.ocr_failed',
            'documents',
            ['after' => ['provider' => $this->provider->name(), 'success' => $result['success'], 'message' => $result['message'], 'chars' => strlen($result['text'])]],
            $document,
        );

        if (! $result['success']) {
            Log::info('OCR extraction did not succeed', ['document_id' => $document->id, 'message' => $result['message']]);
        }

        return $result;
    }
}
