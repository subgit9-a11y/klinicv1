<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Invoice;
use App\Models\Prescription;
use App\Services\Documents\PdfService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(private readonly PdfService $pdf) {}

    public function index()
    {
        $documents = Document::query()->latest()->paginate(25);

        return view('documents.index', ['documents' => $documents]);
    }

    public function streamDocument(Document $document): StreamedResponse
    {
        $disk = Storage::disk($document->disk);

        return $disk->download($document->path, $document->name, [
            'Content-Type' => $document->mime_type,
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
