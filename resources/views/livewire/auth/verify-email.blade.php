<div class="text-center">
    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-brand-100 text-brand-600 mb-4">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
    </div>

    <p class="text-sm text-gray-600 mb-6">{{ __('klinic360.auth.verify_email_help') }}</p>

    @if (session('status') === 'verification-link-sent')
        <div class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-700 mb-4">
            {{ __('klinic360.auth.verification_link_sent') }}
        </div>
    @endif

    <div class="space-y-3">
        <button type="button" wire:click="resend"
            wire:loading.attr="disabled"
            wire:target="resend"
            class="w-full rounded-md bg-brand-600 text-white py-2.5 text-sm font-medium hover:bg-brand-700 transition disabled:opacity-50">
            <span wire:loading.remove wire:target="resend">{{ __('klinic360.auth.resend_verification') }}</span>
            <span wire:loading wire:target="resend">{{ __('klinic360.auth.sending') }}</span>
        </button>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full text-sm text-gray-500 hover:text-gray-700 py-2">
                {{ __('klinic360.auth.logout') }}
            </button>
        </form>
    </div>
</div>
