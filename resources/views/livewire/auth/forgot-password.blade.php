<div>
@if ($sent)
    <div class="text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-100 text-green-600 mb-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </div>
        <p class="text-sm text-gray-600 mb-6">{{ __('klinic360.auth.reset_link_sent') }}</p>
        <a href="{{ route('login') }}" class="inline-block text-sm text-brand-600 hover:text-brand-700 font-medium">
            {{ __('klinic360.auth.back_to_login') }}
        </a>
    </div>
@else
    <form wire:submit="sendResetLink" class="space-y-4">
        <p class="text-sm text-gray-500 mb-4">{{ __('klinic360.auth.forgot_password_help') }}</p>

        <x-ui.input
            label="{{ __('klinic360.auth.email') }}"
            type="email"
            name="email"
            :value="old('email')"
            autofocus
            autocomplete="email"
            required
        />

        <button type="submit"
            wire:loading.attr="disabled"
            wire:target="sendResetLink"
            class="w-full rounded-md bg-brand-600 text-white py-2.5 text-sm font-medium hover:bg-brand-700 transition disabled:opacity-50">
            <span wire:loading.remove wire:target="sendResetLink">{{ __('klinic360.auth.send_reset_link') }}</span>
            <span wire:loading wire:target="sendResetLink">{{ __('klinic360.auth.sending') }}</span>
        </button>
    </form>

    <p class="mt-6 text-center">
        <a href="{{ route('login') }}" class="text-sm text-brand-600 hover:text-brand-700">
            {{ __('klinic360.auth.back_to_login') }}
        </a>
    </p>
@endif
</div>
