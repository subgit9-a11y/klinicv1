@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? __('klinic360.app_name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-br from-brand-50 to-white text-gray-900 font-sans antialiased min-h-screen flex items-center justify-center px-4 py-8">
    <div class="max-w-md w-full bg-white rounded-xl shadow-lg border border-gray-200 p-8">
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-brand-600 text-white font-bold mb-3">K360</div>
            <h1 class="text-xl font-bold text-gray-900">{{ __('klinic360.app_name') }}</h1>
            @isset($title)<p class="text-sm text-gray-500 mt-1">{{ $title }}</p>@endisset
        </div>

        {{ $slot }}
    </div>

</body>
</html>
