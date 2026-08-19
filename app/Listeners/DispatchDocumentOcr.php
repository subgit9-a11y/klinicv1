<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DocumentUploaded;
use App\Jobs\ProcessDocumentOcr;
use App\Services\Documents\OcrService;

/**
 * Queues OCR for freshly uploaded documents — but only when an OCR provider
 * is actually configured, so unconfigured installs never enqueue doomed jobs.
 */
class DispatchDocumentOcr
{
    public function handle(DocumentUploaded $event): void
    {
        if (! app(OcrService::class)->isConfigured()) {
            return;
        }

        ProcessDocumentOcr::dispatch($event->document->id);
    }
}
