<?php

declare(strict_types=1);

namespace App\Services\Bookings;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\PaymentOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Appointments\AppointmentService;
use App\Services\Billing\BillingService;
use App\Services\Patients\PatientService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Public online-consultation booking with server-side payment verification.
 *
 * Flow:
 *  1. Resolve/validate the doctor against the booking tenant (practitioner role,
 *     same tenant, active) — never trust a raw user_id from the public form.
 *  2. Register/find the patient via the canonical PatientService (K360-P-* UID,
 *     phone de-dup) — never the throwaway K360-PUB-* scheme.
 *  3. Book the appointment as SCHEDULED — NOT confirmed. Confirmation only
 *     happens after payment is verified (webhook / order verification).
 *  4. Create + issue an invoice for the online consultation fee, linked to the
 *     appointment (invoice.appointment_id).
 *  5. Create a Cashfree order (PaymentGatewayInterface::createOrder) and persist
 *     a PaymentOrder (payable = Invoice) keyed by gateway_order_id so the
 *     webhook can resolve it.
 *  6. Return the exact checkout URL the gateway supplied (hosted checkout) —
 *     never a fabricated URL. If the gateway is not configured (dev/test),
 *     the flow degrades gracefully: the appointment remains SCHEDULED with
 *     no order and no payment required; in production the booking fails
 *     closed (paymentUnavailable → 503).
 *
 * The webhook (WebhookController → WebhookProcessor) records the payment against
 * the invoice; OnlineBookingService::confirmOnPayment promotes the appointment
 * to CONFIRMED once the invoice is PAID — invoked from the webhook path.
 */
class OnlineBookingService
{
    public function __construct(
        private readonly AppointmentService $appointments,
        private readonly PatientService $patients,
        private readonly BillingService $billing,
        private readonly PaymentGatewayInterface $gateway,
    ) {}

    /**
     * @param  array{first_name:string, last_name:string, phone:string, email?:?string, gender?:?string, user_id:int, appointment_date:string, start_time:string, reason?:?string}  $validated
     * @return array{appointment: Appointment, invoice: ?Invoice, payment_url: ?string, gateway_configured: bool}
     */
    public function book(Tenant $tenant, array $validated): array
    {
        app(TenantContext::class)->set($tenant->id);

        // Fail closed BEFORE creating anything: production + unconfigured
        // gateway must make booking unavailable, never a free appointment.
        if ($this->paymentUnavailable()) {
            throw new \RuntimeException('Online booking is temporarily unavailable (payments not configured).');
        }

        // Security: the doctor MUST belong to the booking tenant and be a
        // practitioner role. Otherwise a public caller could book an
        // arbitrary user_id (cross-tenant escalation).
        $doctor = User::where('id', $validated['user_id'])
            ->where('tenant_id', $tenant->id)
            ->whereIn('role', ['DOCTOR', 'PRACTITIONER', 'CLINIC_OWNER'])
            ->where('is_active', true)
            ->firstOrFail();

        // Canonical patient registration path — permanent K360-P-* UID, phone
        // de-dup. Never the throwaway K360-PUB-* scheme.
        $patient = $this->patients->findDuplicateByPhone($validated['phone'])
            ?? $this->patients->register([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? null,
                'gender' => $validated['gender'] ?? 'UNKNOWN',
                'is_active' => true,
            ]);

        $creator = User::where('tenant_id', $tenant->id)->firstOrFail();
        $duration = (int) config('klinic.public_booking.consultation_duration_minutes', 30);
        $end = $this->computeEndTime($validated['start_time'], $duration);

        // Book as SCHEDULED — NOT confirmed. Confirmation requires verified payment.
        $appointment = $this->appointments->book([
            'patient_id' => $patient->id,
            'user_id' => $doctor->id,
            'type' => 'ONLINE',
            'status' => 'SCHEDULED',
            'appointment_date' => $validated['appointment_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $end,
            'duration_minutes' => $duration,
            'reason' => $validated['reason'] ?? 'Online consultation booking',
        ], $creator);

        $gatewayConfigured = $this->gateway->isConfigured();
        $invoice = null;
        $paymentUrl = null;

        if ($gatewayConfigured) {
            [$invoice, $paymentUrl] = $this->createPaymentFor($appointment, $patient, $tenant);
        } else {
            // Graceful degrade (dev/test only — fail-closed runs at the top of
            // book()): no gateway → no payment required, stays SCHEDULED.
            Log::info('Online booking created without payment (gateway unconfigured)', [
                'appointment_id' => $appointment->id,
            ]);
        }

        return [
            'appointment' => $appointment,
            'invoice' => $invoice,
            'payment_url' => $paymentUrl,
            'gateway_configured' => $gatewayConfigured,
        ];
    }

    /**
     * True when the payment gateway is unconfigured AND the fail-closed policy
     * is active. bookingUnavailableReason() renders this as a notice on the
     * public booking page; book() throws before creating any appointment.
     */
    public function paymentUnavailable(): bool
    {
        return ! $this->gateway->isConfigured()
            && (bool) config('klinic.public_booking.fail_closed', false);
    }

    /**
     * Promote an appointment to CONFIRMED once its linked invoice is paid.
     * Called from the webhook path after a successful payment record. This is
     * the ONLY place an online booking is confirmed — never on browser redirect.
     */
    public function confirmOnPayment(Invoice $invoice): ?Appointment
    {
        if ($invoice->appointment_id === null) {
            return null;
        }

        $appointment = Appointment::find($invoice->appointment_id);
        if ($appointment === null) {
            return null;
        }

        // Only confirm once the invoice is fully paid.
        if (! $invoice->fresh()->isPaid()) {
            return null;
        }

        if ($appointment->status === 'SCHEDULED') {
            $by = User::where('tenant_id', $appointment->tenant_id)->first();
            if ($by !== null) {
                $this->appointments->changeStatus($appointment, 'CONFIRMED', $by, 'Payment verified');
            } else {
                // No tenant user to attribute the transition — update directly.
                $appointment->update(['status' => 'CONFIRMED']);
            }

            return $appointment->fresh();
        }

        return $appointment;
    }

    /**
     * @return array{0: Invoice, 1: ?string}
     */
    private function createPaymentFor(Appointment $appointment, $patient, Tenant $tenant): array
    {
        $fee = (int) config('klinic.public_booking.consultation_fee_cents', 49900);

        // Create + issue an invoice for the consultation fee.
        $invoice = $this->billing->createInvoice([
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'source' => 'OPD',
            'currency' => 'INR',
            'notes' => 'Online consultation booking #'.$appointment->id,
        ]);
        $this->billing->addInvoiceItem($invoice, [
            'description' => 'Online consultation — Dr. '.$appointment->doctor?->name,
            'type' => 'CONSULTATION',
            'quantity' => 1,
            'unit_price_cents' => $fee,
            'currency' => 'INR',
        ]);
        $invoice = $this->billing->issue($invoice);

        $internalOrderId = 'K360-ORD-'.strtoupper(Str::random(12));
        $returnUrl = rtrim((string) config('app.url'), '/').config('klinic.public_booking.return_url', '/book/done');

        $order = $this->gateway->createOrder(
            $internalOrderId,
            $invoice->total_cents,
            $invoice->currency,
            (string) ($patient->email ?? 'patient@klinic360.test'),
            (string) $patient->phone,
            [
                'customer_id' => 'K360-C-'.$patient->id,
                'return_url' => $returnUrl,
                'notify_url' => rtrim((string) config('app.url'), '/').'/api/v1/webhooks/payments',
            ],
        );

        $paymentUrl = null;
        if ($order['success'] ?? false) {
            DB::transaction(function () use ($order, $invoice, $internalOrderId, $patient, $tenant, &$paymentUrl) {
                PaymentOrder::create([
                    'tenant_id' => $tenant->id,
                    'internal_order_id' => $internalOrderId,
                    'gateway' => $this->gateway->name(),
                    'gateway_order_id' => $order['gateway_order_id'],
                    'payable_type' => Invoice::class,
                    'payable_id' => $invoice->id,
                    'amount_cents' => $invoice->total_cents,
                    'currency' => $invoice->currency,
                    'customer_email' => (string) ($patient->email ?? ''),
                    'customer_phone' => (string) $patient->phone,
                    'status' => 'CREATED',
                    'metadata' => ['return_url' => config('klinic.public_booking.return_url', '/book/done')],
                ]);
                // Only the checkout URL the gateway actually returned — never
                // fabricated. When Cashfree omits it, payment_url stays null
                // and the booking remains "payment pending" instead of
                // redirecting the patient to a constructed (possibly invalid)
                // third-party URL.
                $paymentUrl = $order['checkout_url'] ?? null;
            });
        } else {
            Log::warning('Online booking: Cashfree order creation failed', [
                'invoice_id' => $invoice->id,
                'message' => $order['message'] ?? '',
            ]);
        }

        return [$invoice, $paymentUrl];
    }

    private function computeEndTime(string $startTime, int $minutes): string
    {
        try {
            return \Carbon\Carbon::parse($startTime)->addMinutes($minutes)->format('H:i');
        } catch (\Throwable) {
            return $startTime;
        }
    }
}
