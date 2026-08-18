<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Appointments\AppointmentService;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OnlineBookingController extends Controller
{
    public function __construct(private readonly AppointmentService $appointments) {}

    public function show(Request $request)
    {
        // The default tenant for public booking — resolved from config or the first active tenant.
        $tenant = $this->resolveTenant();
        app(TenantContext::class)->set($tenant->id);

        $doctors = User::where('tenant_id', $tenant->id)
            ->whereIn('role', ['DOCTOR', 'PRACTITIONER', 'CLINIC_OWNER'])
            ->orderBy('name')
            ->get();

        return view('online-booking.show', [
            'tenant' => $tenant,
            'doctors' => $doctors,
        ]);
    }

    public function store(Request $request)
    {
        $tenant = $this->resolveTenant();
        app(TenantContext::class)->set($tenant->id);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['nullable', 'string', 'max:20'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        // Find or create the patient by phone within this tenant.
        $patient = Patient::firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => $validated['phone']],
            [
                'k360_uid' => 'K360-PUB-'.strtoupper(uniqid()),
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'] ?? null,
                'gender' => $validated['gender'] ?? 'UNKNOWN',
                'is_active' => true,
            ]
        );

        // Use a system/owner user of the tenant as the creator for public bookings.
        $creator = User::where('tenant_id', $tenant->id)->firstOrFail();

        $end = $this->computeEndTime($validated['start_time'], 30);

        $appointment = $this->appointments->book([
            'patient_id' => $patient->id,
            'user_id' => $validated['user_id'],
            'type' => 'ONLINE',
            'status' => 'SCHEDULED',
            'appointment_date' => $validated['appointment_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $end,
            'duration_minutes' => 30,
            'reason' => $validated['reason'] ?? 'Online consultation booking',
        ], $creator);

        return redirect()
            ->route('online-booking.show')
            ->with('status', 'Appointment booked! Your reference is #'.$appointment->id.'. A confirmation will be sent to your phone.');
    }

    private function resolveTenant(): Tenant
    {
        $tenantId = (int) config('klinic360.public_booking_tenant_id', 0);

        if ($tenantId > 0) {
            return Tenant::findOrFail($tenantId);
        }

        // An active tenant is one that is not suspended.
        return Tenant::whereNull('suspended_at')->firstOrFail();
    }

    private function computeEndTime(string $startTime, int $minutes): string
    {
        try {
            return Carbon::parse($startTime)->addMinutes($minutes)->format('H:i');
        } catch (\Throwable) {
            return $startTime;
        }
    }
}
