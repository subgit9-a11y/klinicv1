@props(['title' => null])

<div class="mb-6 flex items-start justify-between gap-4">
    <div>
        @if($title)
            <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
        @endif
        @if(isset($subtitle))
            <p class="text-sm text-gray-500 mt-1">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex items-center gap-2 flex-shrink-0">{{ $actions }}</div>
    @endisset
</div>
