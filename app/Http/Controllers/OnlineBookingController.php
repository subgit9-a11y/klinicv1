<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\Bookings\OnlineBookingService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;

class OnlineBookingController extends Controller
{
    public function __construct(
        private readonly OnlineBookingService $bookings,
        private readonly \App\Services\Appointments\AppointmentService $appointments,
    ) {}

    public function show(Request $request)
    {
        // The default tenant for public booking — resolved from config or the first active tenant.
        $tenant = $this->resolveTenant();
        app(TenantContext::class)->set($tenant->id);

        $doctors = \App\Models\User::where('tenant_id', $tenant->id)
            ->whereIn('role', ['DOCTOR', 'PRACTITIONER', 'CLINIC_OWNER'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('online-booking.show', [
            'tenant' => $tenant,
            'doctors' => $doctors,
            'paymentUnavailable' => $this->bookings->paymentUnavailable(),
        ]);
    }

    /**
     * Public (unauthenticated) endpoint returning real bookable slots for a
     * doctor on a date. The doctor must belong to the resolved public-booking
     * tenant and be an active practitioner — otherwise 404 (no cross-tenant
     * slot enumeration).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function slots(Request $request)
    {
        // Fail closed: no slot enumeration when booking itself is unavailable.
        if ($this->bookings->paymentUnavailable()) {
            return response()->json(['message' => 'Online booking is temporarily unavailable.'], 503);
        }

        $tenant = $this->resolveTenant();
        app(TenantContext::class)->set($tenant->id);

        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $doctor = \App\Models\User::where('id', $validated['user_id'])
            ->where('tenant_id', $tenant->id)
            ->whereIn('role', ['DOCTOR', 'PRACTITIONER', 'CLINIC_OWNER'])
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json([
            'data' => $this->appointments->availableSlots($doctor, $validated['date']),
        ]);
    }

    public function store(Request $request)
    {
        // Fail closed: production + unconfigured gateway = booking unavailable,
        // never an unpaid appointment.
        if ($this->bookings->paymentUnavailable()) {
            abort(503, 'Online booking is temporarily unavailable. Please call the clinic to book.');
        }

        $tenant = $this->resolveTenant();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['nullable', 'string', 'max:20'],
            'user_id' => ['required', 'integer'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->bookings->book($tenant, $validated);
        $appointment = $result['appointment'];

        // Payment is taken via Cashfree and verified server-side (webhook +
        // order verification) BEFORE the appointment is confirmed. The
        // appointment is SCHEDULED here; it becomes CONFIRMED only after the
        // webhook records a successful payment against its invoice.
        if ($result['gateway_configured'] && $result['payment_url'] !== null) {
            return redirect()->away($result['payment_url']);
        }

        $message = $result['gateway_configured']
            ? 'Appointment requested. Complete payment to confirm your slot. Reference #'.$appointment->id.'.'
            : 'Appointment booked! Your reference is #'.$appointment->id.'. A confirmation will be sent to your phone.';

        return redirect()
            ->route('online-booking.show')
            ->with('status', $message);
    }

    public function done()
    {
        return view('online-booking.done');
    }

    public function status(Request $request)
    {
        $tenant = $this->resolveTenantAndSet();

        $lookup = null;
        if ($request->filled('reference')) {
            $lookup = $this->lookupBooking($request->input('reference'), (string) $request->input('phone', ''), $tenant->id);
        }

        return view('online-booking.status', [
            'lookup' => $lookup,
            'reference' => $request->input('reference', ''),
            'phone' => $request->input('phone', ''),
        ]);
    }

    /**
     * @return array{status: string, date: string, start_time: string, doctor: string, patient: string}|null
     */
    private function lookupBooking(string $reference, string $phone, int $tenantId): ?array
    {
        $id = (int) ltrim($reference, '#Kk ');
        if ($id <= 0 || strlen(preg_replace('/\D/', '', $phone)) < 6) {
            return null;
        }

        $appointment = \App\Models\Appointment::withoutGlobalScopes()
            ->with(['patient', 'doctor'])
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($appointment === null || $appointment->patient === null) {
            return null;
        }

        $digits = fn (string $value) => substr(preg_replace('/\D/', '', $value), -10);
        if ($digits((string) $appointment->patient->phone) !== $digits($phone)) {
            return null;
        }

        return [
            'status' => $appointment->status,
            'date' => (string) $appointment->appointment_date,
            'start_time' => (string) $appointment->start_time,
            'doctor' => $appointment->doctor?->name ?? '—',
            'patient' => $appointment->patient->name,
        ];
    }

    private function resolveTenantAndSet(): Tenant
    {
        $tenant = $this->resolveTenant();
        app(TenantContext::class)->set($tenant->id);

        return $tenant;
    }

    private function resolveTenant(): Tenant
    {
        $tenantId = (int) config('klinic.public_booking.tenant_id', 0);

        if ($tenantId > 0) {
            return Tenant::findOrFail($tenantId);
        }

        // An active tenant is one that is not suspended.
        return Tenant::whereNull('suspended_at')->firstOrFail();
    }
}
