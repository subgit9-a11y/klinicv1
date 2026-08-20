<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Document;
use App\Services\Documents\OcrService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs OCR extraction for an uploaded document off the request path.
 * OcrService degrades gracefully (records a failed audit entry) when no
 * provider is configured, so dispatch is always safe.
 */
class ProcessDocumentOcr implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** Long enough for large OCR payloads; synchronized between retries. */
    public int $timeout = 120;

    public int $backoff = 30;

    public function __construct(public readonly int $documentId) {}

    public function handle(OcrService $ocr): void
    {
        // Bypass global scopes: queue workers have no request tenant context,
        // so the scoped lookup would hide the row. Tenant context is restored
        // explicitly from the document.
        $document = Document::withoutGlobalScopes()->find($this->documentId);
        if ($document === null) {
            return;
        }

        if ($document->tenant_id !== null) {
            app(TenantContext::class)->set($document->tenant_id);
        }

        try {
            $ocr->extractFromDocument($document);
        } finally {
            app(TenantContext::class)->forget();
        }
    }
}
