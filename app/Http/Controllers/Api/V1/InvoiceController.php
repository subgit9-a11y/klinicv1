<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreInvoiceRequest;
use App\Http\Resources\Api\InvoiceResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\BillingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @group Billing
 */
class InvoiceController extends Controller
{
    public function __construct(private readonly BillingService $billingService) {}

    public function index(): AnonymousResourceCollection
    {
        $invoices = Invoice::query()
            ->with('patient')
            ->when(request()->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when(request()->query('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->latest()
            ->paginate(20);

        return InvoiceResource::collection($invoices);
    }

    public function store(StoreInvoiceRequest $request): Response
    {
        $validated = $request->validated();
        $items = $validated['items'] ?? [];
        unset($validated['items']);

        $invoice = $this->billingService->createInvoice($validated);

        foreach ($items as $item) {
            $this->billingService->addInvoiceItem($invoice, $item);
        }

        $invoice->refresh();

        return response([
            'message' => 'Invoice created.',
            'data' => InvoiceResource::make($invoice->load('patient')),
        ], 201);
    }

    public function show(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        return response(InvoiceResource::make($invoice->load('patient')));
    }

    public function issue(Invoice $invoice): Response
    {
        $this->authorize('update', $invoice);
        $invoice = $this->billingService->issue($invoice);

        return response([
            'message' => 'Invoice issued.',
            'data' => InvoiceResource::make($invoice->load('patient')),
        ]);
    }

    public function recordPayment(Invoice $invoice): Response
    {
        $this->authorize('update', $invoice);

        $validated = request()->validate([
            'method' => ['required', 'in:CASH,UPI,CARD,BANK_TRANSFER,CHEQUE,OTHER'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'gateway' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = $this->billingService->recordPayment($invoice, array_merge($validated, [
            'collected_by' => request()->user()->id,
        ]));

        return response([
            'message' => 'Payment recorded.',
            'data' => [
                'id' => $payment->id,
                'amount_cents' => $payment->amount_cents,
                'method' => $payment->method,
                'status' => $payment->status,
            ],
        ], 201);
    }

    public function refund(Request $request, Invoice $invoice, Payment $payment): Response
    {
        $this->authorize('refund', $invoice);

        if ((int) $payment->invoice_id !== (int) $invoice->id) {
            return response(['message' => 'Payment does not belong to this invoice.'], 422);
        }

        $validated = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
            'gateway_refund_id' => ['nullable', 'string', 'max:255'],
        ]);

        $refund = $this->billingService->refund($payment, $validated);

        return response([
            'message' => 'Refund issued.',
            'data' => [
                'id' => $refund->id,
                'refund_number' => $refund->refund_number,
                'amount_cents' => $refund->amount_cents,
                'status' => $refund->status,
            ],
        ], 201);
    }
}
