<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt - {{ $payment->payment_number ?? $payment->id }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #222; }
        .header { text-align: center; margin-bottom: 18px; border-bottom: 2px solid #1f9482; padding-bottom: 8px; }
        .header h2 { margin: 0; color: #167669; }
        .meta { margin-bottom: 14px; }
        .meta p { margin: 2px 0; }
        .row { display: flex; justify-content: space-between; margin: 2px 0; }
        .amount { text-align: right; margin-top: 12px; font-size: 16px; font-weight: bold; color: #167669; }
        .footer { margin-top: 24px; text-align: center; font-size: 10px; color: #888; border-top: 1px solid #ddd; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Klinic 360 — Payment Receipt</h2>
    </div>
    <div class="meta">
        <div class="row"><span><strong>Receipt #:</strong></span><span>{{ $payment->payment_number ?? $payment->id }}</span></div>
        <div class="row"><span><strong>Date:</strong></span><span>{{ $payment->paid_at?->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A') }}</span></div>
        <div class="row"><span><strong>Invoice #:</strong></span><span>{{ $payment->invoice?->invoice_number ?? 'N/A' }}</span></div>
        @if($payment->patient)
        <div class="row"><span><strong>Patient:</strong></span><span>{{ $payment->patient->name }}</span></div>
        @endif
        <div class="row"><span><strong>Method:</strong></span><span>{{ $payment->method }}</span></div>
        @if($payment->cheque_number)
        <div class="row"><span><strong>Cheque #:</strong></span><span>{{ $payment->cheque_number }}</span></div>
        @endif
        @if($payment->bank_name)
        <div class="row"><span><strong>Bank:</strong></span><span>{{ $payment->bank_name }}</span></div>
        @endif
        <div class="row"><span><strong>Status:</strong></span><span>{{ $payment->status }}</span></div>
        @if($payment->collectedBy)
        <div class="row"><span><strong>Collected by:</strong></span><span>{{ $payment->collectedBy->name }}</span></div>
        @endif
    </div>
    <div class="amount">
        Amount Paid: {{ $currency }} {{ number_format(($payment->amount_cents ?? 0) / 100, 2) }}
    </div>
    @if($payment->notes)
    <p style="margin-top: 14px;"><strong>Notes:</strong> {{ $payment->notes }}</p>
    @endif
    <div class="footer">
        This is a computer-generated receipt. Thank you.
    </div>
</body>
</html>
