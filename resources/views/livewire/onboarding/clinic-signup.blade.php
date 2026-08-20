<div class="max-w-2xl mx-auto">
    <div class="mb-8 text-center">
        <h1 class="text-2xl font-bold text-gray-900">Set up your clinic</h1>
        <p class="text-sm text-gray-600">Three quick steps and your clinic is live with a 14-day free trial.</p>
    </div>

    <div class="mb-6 flex items-center justify-center gap-2 text-xs font-medium">
        @foreach ([1 => 'Clinic', 2 => 'Plan', 3 => 'Account'] as $num => $label)
            <div class="flex items-center gap-2">
                <span class="w-7 h-7 rounded-full flex items-center justify-center {{ $step >= $num ? 'bg-brand-600 text-white' : 'bg-gray-200 text-gray-500' }}">{{ $num }}</span>
                <span class="{{ $step >= $num ? 'text-brand-700' : 'text-gray-400' }}">{{ $label }}</span>
            </div>
            @if ($num < 3)
                <div class="w-10 h-px bg-gray-300"></div>
            @endif
        @endforeach
    </div>

    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
        @if ($step === 1)
            <h2 class="text-lg font-semibold mb-4">Clinic details</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Clinic name</label>
                    <input wire:model="clinic_name" type="text" class="w-full rounded border-gray-300 shadow-sm" placeholder="Shree Ayurveda Clinic">
                    @error('clinic_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Medicine system</label>
                    <select wire:model="system" class="w-full rounded border-gray-300 shadow-sm">
                        <option value="AYURVEDA">Ayurveda</option>
                        <option value="SIDDHA">Siddha</option>
                        <option value="HOMEOPATHY">Homeopathy</option>
                        <option value="GENERAL">General</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Clinic email (optional)</label>
                        <input wire:model="clinic_email" type="email" class="w-full rounded border-gray-300 shadow-sm">
                        @error('clinic_email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Clinic phone (optional)</label>
                        <input wire:model="clinic_phone" type="text" class="w-full rounded border-gray-300 shadow-sm">
                    </div>
                </div>
            </div>
        @elseif ($step === 2)
            <h2 class="text-lg font-semibold mb-4">Choose your plan</h2>
            <div class="space-y-3">
                @foreach ($plans as $plan)
                    <label class="flex items-start gap-3 p-4 border rounded-lg cursor-pointer {{ $plan_code === $plan->code ? 'border-brand-600 bg-brand-50' : 'border-gray-200' }}">
                        <input wire:model="plan_code" type="radio" value="{{ $plan->code }}" class="mt-1">
                        <span>
                            <span class="block font-medium text-gray-900">{{ $plan->name }} — ₹{{ number_format($plan->price_cents / 100, 0) }}/{{ strtolower($plan->billing_cycle) }}</span>
                            <span class="block text-sm text-gray-500">{{ $plan->description ?? 'Up to '.$plan->max_users.' users, '.$plan->max_doctors.' doctors' }}</span>
                        </span>
                    </label>
                @endforeach
                @error('plan_code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
        @else
            <h2 class="text-lg font-semibold mb-4">Your account</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Your name</label>
                    <input wire:model="owner_name" type="text" class="w-full rounded border-gray-300 shadow-sm">
                    @error('owner_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input wire:model="owner_email" type="email" class="w-full rounded border-gray-300 shadow-sm">
                    @error('owner_email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input wire:model="owner_password" type="password" class="w-full rounded border-gray-300 shadow-sm">
                        @error('owner_password') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm password</label>
                        <input wire:model="owner_password_confirmation" type="password" class="w-full rounded border-gray-300 shadow-sm">
                    </div>
                </div>
            </div>
        @endif

        <div class="mt-6 flex justify-between">
            <div>
                @if ($step > 1)
                    <button wire:click="back" type="button" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">← Back</button>
                @endif
            </div>
            @if ($step < 3)
                <button wire:click="next" type="button" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Continue →</button>
            @else
                <button wire:click="submit" type="button" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Create my clinic</button>
            @endif
        </div>
    </div>

    <p class="mt-4 text-center text-sm text-gray-500">
        Already have an account? <a href="{{ route('login') }}" class="text-brand-600 hover:underline">Sign in</a>
    </p>
</div>
