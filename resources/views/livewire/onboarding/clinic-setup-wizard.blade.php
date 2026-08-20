<div class="max-w-2xl mx-auto">
    <div class="mb-8 text-center">
        <h1 class="text-2xl font-bold text-gray-900">Welcome — let's set up your clinic</h1>
        <p class="text-sm text-gray-600">You can skip any step and come back later from the dashboard.</p>
    </div>

    <div class="mb-6 flex items-center justify-center gap-2 text-xs font-medium">
        @foreach ([1 => 'Profile', 2 => 'Doctor', 3 => 'Service', 4 => 'Ward'] as $num => $label)
            <div class="flex items-center gap-2">
                <span class="w-7 h-7 rounded-full flex items-center justify-center {{ $step >= $num ? 'bg-brand-600 text-white' : 'bg-gray-200 text-gray-500' }}">{{ $num }}</span>
                <span class="{{ $step >= $num ? 'text-brand-700' : 'text-gray-400' }}">{{ $label }}</span>
            </div>
            @if ($num < 4)
                <div class="w-10 h-px bg-gray-300"></div>
            @endif
        @endforeach
    </div>

    @if (session('message'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">{{ session('message') }}</div>
    @endif

    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
        @if ($step === 1)
            <h2 class="text-lg font-semibold mb-4">Clinic profile</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Clinic name</label>
                    <input wire:model="clinic_name" type="text" class="w-full rounded border-gray-300 shadow-sm">
                    @error('clinic_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input wire:model="clinic_email" type="email" class="w-full rounded border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input wire:model="clinic_phone" type="text" class="w-full rounded border-gray-300 shadow-sm">
                    </div>
                </div>
                <div class="flex justify-between pt-2">
                    <button wire:click="finish" type="button" class="text-sm text-gray-500 hover:text-gray-800">Skip setup entirely</button>
                    <button wire:click="saveProfile" type="button" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Save & continue →</button>
                </div>
            </div>
        @elseif ($step === 2)
            <h2 class="text-lg font-semibold mb-4">Add your first doctor</h2>
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input wire:model="doctor_name" type="text" class="w-full rounded border-gray-300 shadow-sm">
                        @error('doctor_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Specialization</label>
                        <input wire:model="doctor_specialization" type="text" class="w-full rounded border-gray-300 shadow-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input wire:model="doctor_email" type="email" class="w-full rounded border-gray-300 shadow-sm">
                    @error('doctor_email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Temporary password</label>
                    <input wire:model="doctor_password" type="password" class="w-full rounded border-gray-300 shadow-sm">
                    @error('doctor_password') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="flex justify-between pt-2">
                    <button wire:click="skipStep" type="button" class="text-sm text-gray-500 hover:text-gray-800">Skip</button>
                    <button wire:click="addDoctor" type="button" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Add doctor →</button>
                </div>
            </div>
        @elseif ($step === 3)
            <h2 class="text-lg font-semibold mb-4">Add a treatment service</h2>
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Service name</label>
                        <input wire:model="service_name" type="text" class="w-full rounded border-gray-300 shadow-sm" placeholder="Abhyanga">
                        @error('service_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <input wire:model="service_category" type="text" class="w-full rounded border-gray-300 shadow-sm" placeholder="Panchakarma">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Duration (minutes)</label>
                        <input wire:model="service_duration_minutes" type="number" min="1" class="w-full rounded border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Price (₹)</label>
                        <input wire:model="service_price_rupees" type="number" min="0" class="w-full rounded border-gray-300 shadow-sm">
                    </div>
                </div>
                <div class="flex justify-between pt-2">
                    <button wire:click="skipStep" type="button" class="text-sm text-gray-500 hover:text-gray-800">Skip</button>
                    <button wire:click="addService" type="button" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Add service →</button>
                </div>
            </div>
        @else
            <h2 class="text-lg font-semibold mb-4">IPD ward (optional)</h2>
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ward name</label>
                        <input wire:model="ward_name" type="text" class="w-full rounded border-gray-300 shadow-sm" placeholder="General Ward A">
                        @error('ward_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select wire:model="ward_type" class="w-full rounded border-gray-300 shadow-sm">
                            <option value="GENERAL">General</option>
                            <option value="PRIVATE">Private</option>
                            <option value="SEMI_PRIVATE">Semi-private</option>
                            <option value="ICU">ICU</option>
                            <option value="SPECIAL">Special</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-between pt-2">
                    <button wire:click="finish" type="button" class="text-sm text-gray-500 hover:text-gray-800">Skip & finish</button>
                    <button wire:click="addWard" type="button" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Add ward & finish →</button>
                </div>
            </div>
        @endif
    </div>
</div>
