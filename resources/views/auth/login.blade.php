<x-layouts.app :title="__('klinic360.sign_in')">
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-brand-50 to-white px-4">
        <div class="max-w-md w-full bg-white rounded-xl shadow-lg border border-gray-200 p-8">
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-brand-600 text-white font-bold mb-3">K360</div>
                <h1 class="text-xl font-bold text-gray-900">{{ __('klinic360.app_name') }}</h1>
                <p class="text-sm text-gray-500 mt-1">{{ __('klinic360.sign_in') }}</p>
            </div>

            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                    {{ implode(' ', $errors->all()) }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500" required autofocus>
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" name="password" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500" required>
                </div>
                <button type="submit" class="w-full rounded-md bg-brand-600 text-white py-2.5 text-sm font-medium hover:bg-brand-700 transition">
                    {{ __('klinic360.sign_in') }}
                </button>
            </form>
            <p class="mt-6 text-center text-xs text-gray-400">{{ __('klinic360.footer_note') }}</p>
        </div>
    </div>
</x-layouts.app>
