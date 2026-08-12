@props([
    'label' => null,
    'type' => 'text',
    'name' => null,
    'value' => null,
    'placeholder' => null,
    'autofocus' => false,
    'autocomplete' => null,
    'required' => false,
    'help' => null,
])

@php
    $id = 'field-'.str_replace(['[', ']'], ['-', ''], $name ?? uniqid());
    $error = $errors->first($name ?? '');
@endphp

<div class="mb-4">
    @if($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</label>
    @endif
    <input
        id="{{ $id }}"
        type="{{ $type }}"
        wire:model="{{ $name }}"
        name="{{ $name }}"
        value="{{ old($name, $value) }}"
        placeholder="{{ $placeholder }}"
        autocomplete="{{ $autocomplete }}"
        @if($autofocus) autofocus @endif
        @if($required) required @endif
        class="w-full rounded-md border px-3 py-2 text-sm transition {{ $error ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-brand-500 focus:ring-brand-500' }} focus:outline-none focus:ring-1"
    />
    @if($help && ! $error)
        <p class="mt-1 text-xs text-gray-400">{{ $help }}</p>
    @endif
    @if($error)
        <p class="mt-1 text-xs text-red-600">{{ $error }}</p>
    @endif
</div>
