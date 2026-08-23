<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment complete — Klinic 360</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-lg max-w-md w-full p-8 text-center">
        <div class="mx-auto w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h1 class="text-xl font-bold text-gray-900 mb-2">Payment received</h1>
        <p class="text-gray-600 text-sm mb-6">
            Thank you. Your payment is being verified and your appointment will be confirmed shortly.
            You will receive a confirmation message on your registered phone number.
        </p>
        <div class="space-y-2">
            <a href="{{ route('online-booking.status') }}" class="block w-full px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">
                Check booking status
            </a>
            <a href="{{ route('online-booking.show') }}" class="block w-full px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                Book another appointment
            </a>
        </div>
    </div>
</body>
</html>
