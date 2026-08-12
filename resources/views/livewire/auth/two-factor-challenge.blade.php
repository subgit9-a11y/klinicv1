<div>
<div class="text-center">
    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-brand-100 text-brand-600 mb-4">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
    </div>
    <p class="text-sm text-gray-600 mb-6">{{ __('klinic360.auth.2fa_help') }}</p>
</div>

<form wire:submit="challenge" class="space-y-4">
    <x-ui.input
        label="{{ __('klinic360.auth.2fa_code') }}"
        type="text"
        name="code"
        inputmode="numeric"
        placeholder="123456"
        autofocus
        autocomplete="one-time-code"
        required
        :help="__('klinic360.auth.2fa_code_help')"
    />

    <button type="submit"
        wire:loading.attr="disabled"
        wire:target="challenge"
        class="w-full rounded-md bg-brand-600 text-white py-2.5 text-sm font-medium hover:bg-brand-700 transition disabled:opacity-50">
        <span wire:loading.remove wire:target="challenge">{{ __('klinic360.auth.verify') }}</span>
        <span wire:loading wire:target="challenge">{{ __('klinic360.auth.verifying') }}</span>
    </button>
</form>

<form method="POST" action="{{ route('logout') }}" class="mt-6 text-center">
    @csrf
    <button type="submit" class="text-sm text-gray-500 hover:text-gray-700">
        {{ __('klinic360.auth.logout') }}
    </button>
</form>
</div>
