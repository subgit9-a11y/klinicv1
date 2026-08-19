<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Document;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after a document has been uploaded and committed. OCR runs off
 * the request path via ProcessDocumentOcr so large PDFs/images never block
 * the uploader.
 */
class DocumentUploaded
{
    use Dispatchable;

    public function __construct(public readonly Document $document) {}
}
