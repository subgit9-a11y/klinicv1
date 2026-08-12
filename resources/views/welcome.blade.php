<x-layouts.app>
    <div class="min-h-screen flex flex-col items-center justify-center bg-gradient-to-br from-brand-50 to-white px-4">
        <div class="max-w-2xl text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-brand-600 text-white text-2xl font-bold mb-6">
                K360
            </div>
            <h1 class="text-4xl sm:text-5xl font-extrabold text-gray-900 tracking-tight">
                {{ __('klinic360.app_name') }}
            </h1>
            <p class="mt-4 text-lg text-gray-600">
                {{ __('klinic360.tagline') }}
            </p>
            <p class="mt-2 text-sm text-gray-500">
                {{ __('klinic360.plans_summary') }}
            </p>
            <div class="mt-8 flex items-center justify-center gap-4">
                @auth
                    <a href="{{ route('dashboard') }}" class="px-6 py-3 rounded-lg bg-brand-600 text-white font-medium hover:bg-brand-700 transition">
                        {{ __('klinic360.go_to_dashboard') }}
                    </a>
                @else
                    <a href="{{ route('login') }}" class="px-6 py-3 rounded-lg bg-brand-600 text-white font-medium hover:bg-brand-700 transition">
                        {{ __('klinic360.sign_in') }}
                    </a>
                @endauth
            </div>
            <p class="mt-10 text-xs text-gray-400">
                {{ __('klinic360.footer_note') }}
            </p>
        </div>
    </div>
</x-layouts.app>
