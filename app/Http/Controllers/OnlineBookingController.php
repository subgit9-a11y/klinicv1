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
