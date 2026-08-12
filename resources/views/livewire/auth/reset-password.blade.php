<div>
<form wire:submit="resetPassword" class="space-y-4">
    @if (session('status'))
        <div class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-700">
            {{ session('status') }}
        </div>
    @endif

    <x-ui.input
        label="{{ __('klinic360.auth.email') }}"
        type="email"
        name="email"
        :value="old('email', $email)"
        autocomplete="email"
        required
    />

    <x-ui.input
        label="{{ __('klinic360.auth.new_password') }}"
        type="password"
        name="password"
        autocomplete="new-password"
        required
    />

    <x-ui.input
        label="{{ __('klinic360.auth.confirm_password') }}"
        type="password"
        name="password_confirmation"
        autocomplete="new-password"
        required
    />

    <button type="submit"
        wire:loading.attr="disabled"
        wire:target="resetPassword"
        class="w-full rounded-md bg-brand-600 text-white py-2.5 text-sm font-medium hover:bg-brand-700 transition disabled:opacity-50">
        <span wire:loading.remove wire:target="resetPassword">{{ __('klinic360.auth.reset_password') }}</span>
        <span wire:loading wire:target="resetPassword">{{ __('klinic360.auth.resetting') }}</span>
    </button>
</form>
</div>
