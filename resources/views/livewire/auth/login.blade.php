<div>
<form wire:submit="authenticate" class="space-y-4">
    @if (session('status'))
        <div class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-700">
            {{ session('status') }}
        </div>
    @endif

    <x-ui.input
        label="{{ __('klinic360.auth.email') }}"
        type="email"
        name="email"
        :value="old('email')"
        autofocus
        autocomplete="email"
        required
    />

    <x-ui.input
        label="{{ __('klinic360.auth.password') }}"
        type="password"
        name="password"
        autocomplete="current-password"
        required
    />

    <div class="flex items-center justify-between">
        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" wire:model="remember" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
            {{ __('klinic360.auth.remember_me') }}
        </label>
        <a href="{{ route('password.request') }}" class="text-sm text-brand-600 hover:text-brand-700">
            {{ __('klinic360.auth.forgot_password') }}
        </a>
    </div>

    <button type="submit"
        wire:loading.attr="disabled"
        wire:target="authenticate"
        class="w-full rounded-md bg-brand-600 text-white py-2.5 text-sm font-medium hover:bg-brand-700 transition disabled:opacity-50">
        <span wire:loading.remove wire:target="authenticate">{{ __('klinic360.sign_in') }}</span>
        <span wire:loading wire:target="authenticate">{{ __('klinic360.auth.signing_in') }}</span>
    </button>
</form>

<p class="mt-6 text-center text-xs text-gray-400">{{ __('klinic360.footer_note') }}</p>
</div>
