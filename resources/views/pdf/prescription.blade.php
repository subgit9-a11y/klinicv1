<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Prescription - {{ $prescription->id }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .patient-info { margin-bottom: 15px; }
        .medications { width: 100%; border-collapse: collapse; }
        .medications th, .medications td { border: 1px solid #ccc; padding: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Klinic 360 - Prescription</h2>
    </div>
    <div class="patient-info">
        <p><strong>Patient:</strong> {{ $patient?->first_name }} {{ $patient?->last_name }}</p>
        <p><strong>Date:</strong> {{ $prescription->created_at?->format('d M Y') }}</p>
    </div>
</body>
</html>
