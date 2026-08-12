@props(['title' => null])

<div class="mb-6">
    @if($title)
        <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
    @endif
    @if(isset($subtitle))
        <p class="text-sm text-gray-500 mt-1">{{ $subtitle }}</p>
    @endif
</div>
