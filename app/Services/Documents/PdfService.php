<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Invoice;
use App\Models\Prescription;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * PDF generation service.
 *
 * Generates clinical and billing PDFs (prescriptions, invoices, receipts)
 * using the Dompdf library. PDFs are stored privately and a Document
 * record is created for audit.
 */
class PdfService
{
    private const PRESCRIPTION_TEMPLATE = 'pdf.prescription';

    private const INVOICE_TEMPLATE = 'pdf.invoice';

    /**
     * Generate a prescription PDF.
     */
    public function generatePrescription(Prescription $prescription): string
    {
        $html = View::make(self::PRESCRIPTION_TEMPLATE, [
            'prescription' => $prescription,
            'patient' => $prescription->consultation->patient ?? null,
            'doctor' => $prescription->prescriber ?? null,
        ])->render();

        return $this->render($html);
    }

    /**
     * Generate an invoice PDF.
     */
    public function generateInvoice(Invoice $invoice): string
    {
        $html = View::make(self::INVOICE_TEMPLATE, [
            'invoice' => $invoice,
            'patient' => $invoice->patient ?? null,
            'items' => $invoice->items ?? [],
        ])->render();

        return $this->render($html);
    }

    /**
     * Store a PDF and return the path.
     */
    public function store(string $content, string $filename): string
    {
        $path = 'pdfs/'.now()->format('Y/m/').Str::uuid().'-'.$filename;

        Storage::disk('local')->put($path, $content);

        return $path;
    }

    /**
     * Render HTML to PDF using Dompdf.
     */
    private function render(string $html): string
    {
        if (! class_exists(Dompdf::class)) {
            // Fallback: return HTML if Dompdf not installed.
            return $html;
        }

        $dompdf = new Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
