@props(['title' => '', 'rows' => []])

<div>
    @if($title)
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">{{ $title }}</div>
    @endif
    <div class="rounded-md border border-gray-200 overflow-hidden">
        @foreach($rows as $label => $value)
            <div class="flex items-center justify-between px-3 py-2 text-sm border-b border-gray-100 last:border-b-0">
                <span class="text-gray-600">{{ $label }}</span>
                <span class="font-medium text-gray-900">{{ is_numeric($value) ? number_format($value) : $value }}</span>
            </div>
        @endforeach
    </div>
</div>
