@props(['title' => ''])

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ $title }}</h3>
    <div class="space-y-3">
        {{ $slot }}
    </div>
</div>
