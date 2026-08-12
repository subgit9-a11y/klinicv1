<x-layouts.app sidebar :title="__('klinic360.nav.profile')">
    <x-ui.page-header :title="__('klinic360.nav.profile')" />

    <div class="max-w-3xl space-y-6">

        @if(session('profile-updated') !== null || session('password-updated') !== null)
            <div class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-700">
                @if(session('profile-updated')){{ __('klinic360.profile.updated') }}@endif
                @if(session('password-updated')){{ __('klinic360.profile.password_updated') }}@endif
            </div>
        @endif

        {{-- Profile info --}}
        <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('klinic360.profile.info') }}</h3>
            <form wire:submit="updateProfile" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.input label="{{ __('klinic360.auth.name') }}" name="name" required />
                    <x-ui.input label="{{ __('klinic360.auth.email') }}" type="email" name="email" required />
                    <x-ui.input label="{{ __('klinic360.auth.phone') }}" name="phone" />
                    <x-ui.input label="{{ __('klinic360.profile.designation') }}" name="designation" />
                </div>
                <button type="submit" class="rounded-md bg-brand-600 text-white px-4 py-2 text-sm font-medium hover:bg-brand-700">
                    {{ __('klinic360.profile.save') }}
                </button>
            </form>
        </section>

        {{-- Password --}}
        <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('klinic360.profile.change_password') }}</h3>
            <form wire:submit="updatePassword" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.input label="{{ __('klinic360.auth.new_password') }}" type="password" name="password" autocomplete="new-password" />
                    <x-ui.input label="{{ __('klinic360.auth.confirm_password') }}" type="password" name="password_confirmation" autocomplete="new-password" />
                </div>
                <button type="submit" class="rounded-md bg-brand-600 text-white px-4 py-2 text-sm font-medium hover:bg-brand-700">
                    {{ __('klinic360.profile.save_password') }}
                </button>
            </form>
        </section>

        {{-- Two-factor authentication --}}
        <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">{{ __('klinic360.profile.two_factor') }}</h3>
                @php $enabled = auth()->user()->hasTwoFactorEnabled(); @endphp
                <x-ui.status-badge :status="$enabled ? 'green' : 'gray'" :label="$enabled ? __('klinic360.auth.enabled') : __('klinic360.auth.disabled')" />
            </div>

            @if (auth()->user()->hasTwoFactorEnabled())
                <p class="text-sm text-gray-500 mb-4">{{ __('klinic360.profile.2fa_enabled') }}</p>
                <button type="button" wire:click="disableTwoFactor"
                    wire:loading.attr="disabled" wire:target="disableTwoFactor"
                    class="rounded-md border border-red-300 text-red-700 px-4 py-2 text-sm font-medium hover:bg-red-50 disabled:opacity-50">
                    {{ __('klinic360.profile.disable_2fa') }}
                </button>
            @elseif(!empty($qr_svg))
                {{-- Enrollment: show QR + code + confirm form --}}
                <p class="text-sm text-gray-500 mb-4">{{ __('klinic360.profile.2fa_scan_help') }}</p>
                <div class="flex flex-col sm:flex-row gap-6 items-start">
                    <div class="bg-white p-2 border border-gray-200 rounded-md">{!! $qr_svg !!}</div>
                    <div class="flex-1">
                        <p class="text-xs text-gray-500 mb-1">{{ __('klinic360.profile.2fa_manual_key') }}</p>
                        <code class="block bg-gray-50 border border-gray-200 rounded px-3 py-2 text-xs font-mono break-all">{{ $two_factor_secret }}</code>
                        <form wire:submit="confirmTwoFactor" class="mt-4 space-y-3">
                            <x-ui.input label="{{ __('klinic360.auth.2fa_code') }}" name="two_factor_code" inputmode="numeric" placeholder="123456" required />
                            <button type="submit" class="rounded-md bg-brand-600 text-white px-4 py-2 text-sm font-medium hover:bg-brand-700">
                                {{ __('klinic360.auth.verify') }}
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <p class="text-sm text-gray-500 mb-4">{{ __('klinic360.profile.2fa_disabled') }}</p>
                <button type="button" wire:click="enableTwoFactor"
                    wire:loading.attr="disabled" wire:target="enableTwoFactor"
                    class="rounded-md bg-brand-600 text-white px-4 py-2 text-sm font-medium hover:bg-brand-700 disabled:opacity-50">
                    {{ __('klinic360.profile.enable_2fa') }}
                </button>
            @endif
        </section>
    </div>
</x-layouts.app>
