<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book an Online Consultation · {{ config('app.name', 'Klinic 360') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12">
        <div class="w-full max-w-lg">
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-lg bg-brand-600 text-white font-bold mb-3">K360</div>
                <h1 class="text-2xl font-bold text-gray-900">Book an Online Consultation</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $tenant->name ?? 'Klinic 360' }} · Secure & instant</p>
            </div>

            @if(session('status'))
                <div class="mb-6 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <form method="POST" action="{{ route('online-booking.store') }}">
                    @csrf
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">First name</label>
                            <input type="text" name="first_name" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Last name</label>
                            <input type="text" name="last_name" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" />
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Phone</label>
                        <input type="tel" name="phone" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Email (optional)</label>
                        <input type="email" name="email" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Choose doctor</label>
                        <select name="user_id" id="booking-doctor" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                            <option value="">Select a practitioner</option>
                            @foreach($doctors as $doctor)
                                <option value="{{ $doctor->id }}">{{ $doctor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Date</label>
                            <input type="date" name="appointment_date" id="booking-date" required min="{{ now()->toDateString() }}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Selected time</label>
                            <input type="text" name="start_time" id="booking-time" required readonly placeholder="Pick a slot below" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm bg-gray-50" />
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Available slots</label>
                        <div id="slots-container" class="flex flex-wrap gap-2 min-h-[2.5rem]" data-slots-url="{{ route('online-booking.slots') }}">
                            <span class="text-xs text-gray-400">Select a doctor and date to see real available slots.</span>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Reason (optional)</label>
                        <textarea name="reason" rows="2" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"></textarea>
                    </div>
                    <button type="submit" class="w-full bg-brand-600 text-white text-sm font-medium px-4 py-2.5 rounded-md hover:bg-brand-700">Confirm Booking</button>
                </form>
            </div>
        </div>
    </div>
    <script>
        (function () {
            var doctor = document.getElementById('booking-doctor');
            var date = document.getElementById('booking-date');
            var time = document.getElementById('booking-time');
            var container = document.getElementById('slots-container');
            var url = container.dataset.slotsUrl;
            var placeholder = '<span class="text-xs text-gray-400">Select a doctor and date to see real available slots.</span>';

            function loadSlots() {
                time.value = '';
                if (!doctor.value || !date.value) {
                    container.innerHTML = placeholder;
                    return;
                }
                container.innerHTML = '<span class="text-xs text-gray-400">Loading slots…</span>';
                fetch(url + '?user_id=' + encodeURIComponent(doctor.value) + '&date=' + encodeURIComponent(date.value), {
                    headers: { 'Accept': 'application/json' }
                }).then(function (r) { return r.json(); }).then(function (res) {
                    var slots = res.data || [];
                    if (slots.length === 0) {
                        container.innerHTML = '<span class="text-xs text-gray-400">No working hours configured for this day.</span>';
                        return;
                    }
                    container.innerHTML = '';
                    slots.forEach(function (slot) {
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        var base = 'text-xs px-2.5 py-1.5 rounded-md border ';
                        if (slot.available) {
                            btn.className = base + 'border-brand-600 text-brand-700 hover:bg-brand-50';
                        } else {
                            btn.className = base + 'border-gray-200 text-gray-300 line-through cursor-not-allowed';
                            btn.disabled = true;
                        }
                        btn.textContent = slot.start;
                        btn.addEventListener('click', function () {
                            if (!slot.available) return;
                            time.value = slot.start;
                            container.querySelectorAll('button').forEach(function (b) { b.classList.remove('bg-brand-600', 'text-white'); });
                            btn.classList.add('bg-brand-600', 'text-white');
                        });
                        container.appendChild(btn);
                    });
                }).catch(function () {
                    container.innerHTML = '<span class="text-xs text-red-500">Could not load slots.</span>';
                });
            }

            doctor.addEventListener('change', loadSlots);
            date.addEventListener('change', loadSlots);
        })();
    </script>
</body>
</html>
