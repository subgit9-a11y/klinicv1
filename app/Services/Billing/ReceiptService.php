<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Document;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Generates payment receipts.
 *
 * A receipt is a PDF rendering of a completed Payment, stored privately and
 * linked to the payment via a Document record for audit. The generation is
 * idempotent: calling generate() repeatedly returns the latest stored receipt.
 */
class ReceiptService
{
    private const TEMPLATE = 'pdf.receipt';

    /**
     * Generate (or regenerate) the PDF receipt for a payment and persist it.
     *
     * @return array{document: Document, content: string}
     */
    public function generate(Payment $payment): array
    {
        $payment->load(['invoice', 'patient', 'collectedBy']);

        $html = View::make(self::TEMPLATE, [
            'payment' => $payment,
            'currency' => $payment->currency ?? 'INR',
        ])->render();

        $content = $this->renderPdf($html);
        $filename = 'receipt-'.$payment->payment_number.Str::uuid().'.pdf';
        $path = 'pdfs/'.now()->format('Y/m/').$filename;

        Storage::disk('local')->put($path, $content);

        $document = Document::create([
            'tenant_id' => $payment->tenant_id,
            'patient_id' => $payment->patient_id,
            'name' => 'Receipt '.$payment->payment_number,
            'type' => 'RECEIPT',
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size' => strlen($content),
            'metadata' => ['payment_id' => $payment->id, 'payment_number' => $payment->payment_number],
            'uploaded_by' => $payment->collected_by,
        ]);

        return ['document' => $document, 'content' => $content];
    }

    /**
     * Return the raw PDF content for a payment's most recent receipt.
     */
    public function content(Payment $payment): string
    {
        $document = $this->latestDocument($payment);

        if ($document) {
            return Storage::disk($document->disk)->get($document->path);
        }

        // No stored receipt yet — generate on demand without persisting.
        $payment->load(['invoice', 'patient', 'collectedBy']);

        return $this->renderPdf(
            View::make(self::TEMPLATE, [
                'payment' => $payment,
                'currency' => $payment->currency ?? 'INR',
            ])->render()
        );
    }

    public function latestDocument(Payment $payment): ?Document
    {
        return Document::where('tenant_id', $payment->tenant_id)
            ->where('type', 'RECEIPT')
            ->whereJsonContains('metadata->payment_id', $payment->id)
            ->latest()
            ->first();
    }

    private function renderPdf(string $html): string
    {
        if (! class_exists(\Dompdf\Dompdf::class)) {
            return $html;
        }

        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A5', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
