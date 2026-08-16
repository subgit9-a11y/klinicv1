<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice - {{ $invoice->invoice_number ?? $invoice->id }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .items { width: 100%; border-collapse: collapse; }
        .items th, .items td { border: 1px solid #ccc; padding: 4px; }
        .total { text-align: right; margin-top: 10px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Klinic 360 - Invoice</h2>
    </div>
    <div class="patient-info">
        <p><strong>Patient:</strong> {{ $patient?->first_name }} {{ $patient?->last_name }}</p>
        <p><strong>Invoice #:</strong> {{ $invoice->invoice_number ?? $invoice->id }}</p>
    </div>
    <table class="items">
        <tr><th>Description</th><th>Amount</th></tr>
        @foreach($items as $item)
        <tr><td>{{ $item->description ?? $item->name ?? '' }}</td><td>{{ $item->amount ?? '' }}</td></tr>
        @endforeach
    </table>
</body>
</html>
