<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Billing\BillingService;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(private readonly BillingService $billing)
    {
    }

    public function index(Request $request)
    {
        $query = Invoice::query()->with('patient')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $invoices = $query->paginate(25);

        return view('billing.index', ['invoices' => $invoices]);
    }

    public function createInvoice(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'source' => ['nullable', 'string', 'in:OPD,IPD,PHARMACY,LAB'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $invoice = $this->billing->createInvoice($validated);

        return redirect()->route('billing.index')->with('status', "Invoice {$invoice->invoice_number} created (DRAFT).");
    }

    public function addItem(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:50'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'unit_price_cents' => ['required', 'integer', 'min:0'],
            'discount_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->billing->addInvoiceItem($invoice, $validated);

        return redirect()->route('billing.index')->with('status', "Item added to {$invoice->invoice_number}.");
    }

    public function issue(Invoice $invoice)
    {
        $this->billing->issue($invoice);

        return redirect()->route('billing.index')->with('status', "Invoice {$invoice->invoice_number} issued.");
    }

    public function recordPayment(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', 'in:CASH,CARD,UPI,NETBANKING,WALLET,CHEQUE'],
            'gateway' => ['nullable', 'string', 'max:50'],
            'cheque_number' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->billing->recordPayment($invoice, $validated);

        return redirect()->route('billing.index')->with('status', "Payment recorded for {$invoice->invoice_number}.");
    }

    public function void(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->billing->void($invoice, $validated['reason'] ?? null);

        return redirect()->route('billing.index')->with('status', "Invoice {$invoice->invoice_number} voided.");
    }
}
