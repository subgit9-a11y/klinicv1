<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? __('klinic360.app_name') }} @if(isset($subtitle)) · {{ $subtitle }}@endif</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900 font-sans antialiased min-h-screen flex flex-col">
    @isset($sidebar)
        <div class="flex min-h-screen">
            <aside class="w-64 bg-brand-700 text-white flex-shrink-0 hidden md:flex md:flex-col">
                <x-layouts.sidebar />
            </aside>
            <div class="flex-1 flex flex-col min-w-0">
                <x-layouts.topbar />
                <main class="flex-1 p-4 sm:p-6 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    @else
        <main class="flex-1">
            {{ $slot }}
        </main>
    @endif

</body>
</html>
