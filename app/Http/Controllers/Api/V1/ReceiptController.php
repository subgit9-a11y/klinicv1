<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\ReceiptService;
use Illuminate\Http\Response;

/**
 * @group Receipts
 *
 * Payment receipts (PDF generation + download) for invoice payments.
 */
class ReceiptController extends Controller
{
    public function __construct(private readonly ReceiptService $receiptService) {}

    /**
     * Generate and store a receipt PDF for a payment.
     */
    public function generate(Invoice $invoice, Payment $payment): Response
    {
        $this->authorize('view', $invoice);

        if ((int) $payment->invoice_id !== (int) $invoice->id) {
            abort(404, 'Payment does not belong to this invoice.');
        }

        ['document' => $document] = $this->receiptService->generate($payment);

        return response([
            'message' => 'Receipt generated.',
            'data' => [
                'id' => $document->id,
                'name' => $document->name,
                'type' => $document->type,
                'mime_type' => $document->mime_type,
                'size' => $document->size,
                'created_at' => $document->created_at,
            ],
        ], 201);
    }

    /**
     * Download the receipt PDF for a payment (generates on demand if absent).
     */
    public function download(Invoice $invoice, Payment $payment): Response
    {
        $this->authorize('view', $invoice);

        if ((int) $payment->invoice_id !== (int) $invoice->id) {
            abort(404, 'Payment does not belong to this invoice.');
        }

        $content = $this->receiptService->content($payment);
        $filename = 'receipt-'.$payment->payment_number.'.pdf';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => strlen($content),
        ]);
    }
}
