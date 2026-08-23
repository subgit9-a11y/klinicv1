<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking status — Klinic 360</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-lg max-w-md w-full p-8">
        <h1 class="text-xl font-bold text-gray-900 mb-1">Check your booking</h1>
        <p class="text-gray-500 text-sm mb-6">Enter your booking reference and registered phone number.</p>

        <form method="GET" action="{{ route('online-booking.status') }}" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Booking reference</label>
                <input name="reference" value="{{ $reference }}" type="text" placeholder="#123" required
                       class="w-full rounded border-gray-300 shadow-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone number</label>
                <input name="phone" value="{{ $phone }}" type="tel" required
                       class="w-full rounded border-gray-300 shadow-sm">
            </div>
            <button type="submit" class="w-full px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">
                Check status
            </button>
        </form>

        @if ($lookup !== null)
            @php
                $statusClasses = [
                    'CONFIRMED' => 'bg-green-100 text-green-800',
                    'SCHEDULED' => 'bg-yellow-100 text-yellow-800',
                    'CANCELLED' => 'bg-red-100 text-red-800',
                    'COMPLETED' => 'bg-gray-100 text-gray-700',
                ];
                $badge = $statusClasses[$lookup['status']] ?? 'bg-blue-100 text-blue-800';
            @endphp
            <div class="mt-6 border border-gray-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold text-gray-900">Booking {{ Str::startsWith($reference, '#') ? $reference : '#'.$reference }}</h2>
                    <span class="text-xs font-medium px-2 py-1 rounded {{ $badge }}">{{ $lookup['status'] }}</span>
                </div>
                <dl class="text-sm space-y-1">
                    <div class="flex justify-between"><dt class="text-gray-500">Patient</dt><dd class="text-gray-900">{{ $lookup['patient'] }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Doctor</dt><dd class="text-gray-900">{{ $lookup['doctor'] }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Date</dt><dd class="text-gray-900">{{ \Illuminate\Support\Carbon::parse($lookup['date'])->format('d M Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Time</dt><dd class="text-gray-900">{{ $lookup['start_time'] }}</dd></div>
                </dl>
                @if ($lookup['status'] === 'SCHEDULED')
                    <p class="mt-3 text-xs text-gray-500">Awaiting confirmation — this completes once your payment is verified.</p>
                @endif
            </div>
        @elseif (request()->filled('reference'))
            <div class="mt-6 rounded-md bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 text-sm">
                No booking matches that reference and phone number. Please check and try again.
            </div>
        @endif

        <a href="{{ route('online-booking.show') }}" class="block text-center text-sm text-brand-600 hover:underline mt-6">← Back to booking</a>
    </div>
</body>
</html>
