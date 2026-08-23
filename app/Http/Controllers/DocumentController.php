<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Invoice;
use App\Models\Prescription;
use App\Services\Documents\PdfService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(private readonly PdfService $pdf) {}

    public function streamDocument(Document $document): StreamedResponse
    {
        app(\App\Services\Audit\AuditService::class)->record(
            'document.downloaded',
            'documents',
            ['after' => ['name' => $document->name]],
            $document,
        );

        // Stream through the storage provider — documents.disk stores the
        // provider label ('LOCAL'/'S3'), not a filesystem disk name.
        $provider = app(\App\Contracts\StorageProviderInterface::class);
        abort_unless($provider->exists($document->path), 404);

        return response()->stream(function () use ($provider, $document) {
            $stream = $provider->stream($document->path);
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $document->mime_type ?? 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$document->name.'"',
        ]);
    }

    public function downloadPrescription(Prescription $prescription)
    {
        $pdfContent = $this->pdf->generatePrescription($prescription);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="prescription-'.$prescription->id.'.pdf"',
        ]);
    }

    public function downloadInvoice(Invoice $invoice)
    {
        $pdfContent = $this->pdf->generateInvoice($invoice);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="invoice-'.($invoice->invoice_number ?? $invoice->id).'.pdf"',
        ]);
    }
}
