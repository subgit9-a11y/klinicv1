<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reporting service — aggregates operational and clinical metrics.
 *
 * All reports are tenant-scoped. Supports date-range filtering.
 *
 * Categories:
 *  - Operational: appointments, queue, IPD occupancy
 *  - Financial: revenue, collections, outstanding
 *  - Clinical: consultations, prescriptions, treatments
 *  - Patient: demographics, new vs returning
 */
class ReportService
{
    private function tenantId(): ?int
    {
        return app(TenantContext::class)->id();
    }

    /**
     * Database-agnostic expression for the difference in minutes
     * between two datetime columns (end - start). SQLite lacks
     * TIMESTAMPDIFF; MySQL lacks julianday, so the expression is chosen
     * per driver.
     */
    private function minutesDiff(string $end, string $start): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(julianday({$end}) - julianday({$start}) AS FLOAT) * 24 * 60"
            : "TIMESTAMPDIFF(MINUTE, {$start}, {$end})";
    }

    /**
     * Database-agnostic expression for the difference in days between
     * two datetime columns (end - start), as a float so fractional days
     * are preserved for the length-of-stay average.
     */
    private function daysDiff(string $end, string $start): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(julianday({$end}) - julianday({$start}) AS FLOAT)"
            : "TIMESTAMPDIFF(SECOND, {$start}, {$end}) / 86400.0";
    }

    /**
     * Operational: appointment counts by status for a date range.
     *
     * @return array<string, int>
     */
    public function appointmentSummary(Carbon $from, Carbon $to): array
    {
        $tenantId = $this->tenantId();

        $rows = DB::table('appointments')
            ->select('status', DB::raw('count(*) as count'))
            ->whereBetween('appointment_date', [$from->toDateString(), $to->toDateString()])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'scheduled' => (int) ($rows['SCHEDULED'] ?? 0),
            'confirmed' => (int) ($rows['CONFIRMED'] ?? 0),
            'checked_in' => (int) ($rows['CHECKED_IN'] ?? 0),
            'in_consultation' => (int) ($rows['IN_CONSULTATION'] ?? 0),
            'completed' => (int) ($rows['COMPLETED'] ?? 0),
            'cancelled' => (int) ($rows['CANCELLED'] ?? 0),
            'no_show' => (int) ($rows['NO_SHOW'] ?? 0),
            'total' => (int) $rows->sum(),
        ];
    }

    /**
     * Operational: average wait time per appointment (minutes).
     * Wait = from checked_in_at to completed_at.
     *
     * Uses a database-agnostic minute difference so the query works on
     * both SQLite (dev) and MySQL/MariaDB (prod). julianday() is
     * SQLite-only and would fail on MySQL.
     */
    public function averageWaitTime(Carbon $from, Carbon $to): float
    {
        $tenantId = $this->tenantId();

        $avg = DB::table('appointments')
            ->whereNotNull('checked_in_at')
            ->whereNotNull('completed_at')
            ->whereBetween('appointment_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('AVG('.$this->minutesDiff('completed_at', 'checked_in_at').') as avg_minutes')
            ->value('avg_minutes');

        return round((float) ($avg ?? 0), 1);
    }

    /**
     * Financial: revenue summary (paid invoices) for a date range.
     * Amounts are in cents; we convert to rupees for display.
     *
     * @return array{total_revenue: float, total_collected: float, total_outstanding: float, invoice_count: int}
     */
    public function revenueSummary(Carbon $from, Carbon $to): array
    {
        $tenantId = $this->tenantId();

        $base = DB::table('invoices')
            ->whereBetween('created_at', [$from, $to])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));

        $totalRevenue = (clone $base)->whereIn('status', ['PAID', 'PARTIALLY_PAID'])->sum('total_cents');
        $totalCollected = (clone $base)->sum('amount_paid_cents');
        $totalOutstanding = (clone $base)->whereIn('status', ['ISSUED', 'PARTIALLY_PAID'])->sum('amount_due_cents');
        $invoiceCount = (clone $base)->count();

        return [
            'total_revenue' => (float) ($totalRevenue / 100),
            'total_collected' => (float) ($totalCollected / 100),
            'total_outstanding' => (float) ($totalOutstanding / 100),
            'invoice_count' => (int) $invoiceCount,
        ];
    }

    /**
     * Financial: collections by payment method (in rupees).
     *
     * Successful payments are recorded with status SUCCESS (see
     * BillingService::recordPayment), not COMPLETED, so the filter must
     * match that vocabulary or collections silently return zero.
     *
     * @return array<string, float>
     */
    public function collectionsByMethod(Carbon $from, Carbon $to): array
    {
        $tenantId = $this->tenantId();

        return DB::table('payments')
            ->select('method', DB::raw('sum(amount_cents) as total'))
            ->where('status', 'SUCCESS')
            ->whereBetween('created_at', [$from, $to])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->groupBy('method')
            ->pluck('total', 'method')
            ->map(fn ($v) => (float) ($v / 100))
            ->toArray();
    }

    /**
     * Clinical: consultation counts by medicine_system.
     *
     * @return array<string, int>
     */
    public function consultationsBySystem(Carbon $from, Carbon $to): array
    {
        $tenantId = $this->tenantId();

        return DB::table('consultations')
            ->select('medicine_system', DB::raw('count(*) as count'))
            ->whereBetween('created_at', [$from, $to])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->groupBy('medicine_system')
            ->pluck('count', 'medicine_system')
            ->map(fn ($v) => (int) $v)
            ->toArray();
    }

    /**
     * Clinical: prescription counts for a date range.
     *
     * @return array{total: int, completed: int}
     */
    public function prescriptionSummary(Carbon $from, Carbon $to): array
    {
        $tenantId = $this->tenantId();

        $base = DB::table('prescriptions')
            ->whereBetween('created_at', [$from, $to])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));

        return [
            'total' => (clone $base)->count(),
            'completed' => (clone $base)->where('status', 'COMPLETED')->count(),
        ];
    }

    /**
     * Operational: IPD occupancy metrics.
     *
     * @return array{current_admissions: int, discharged_in_period: int, avg_los_days: float}
     */
    public function ipdSummary(Carbon $from, Carbon $to): array
    {
        $tenantId = $this->tenantId();

        $base = DB::table('ipd_admissions')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));

        $currentAdmissions = (clone $base)->where('status', 'ADMITTED')->count();
        $dischargedInPeriod = (clone $base)
            ->where('status', 'DISCHARGED')
            ->whereBetween('discharged_at', [$from, $to])
            ->count();

        $avgLos = (clone $base)
            ->where('status', 'DISCHARGED')
            ->whereBetween('discharged_at', [$from, $to])
            ->selectRaw('AVG('.$this->daysDiff('discharged_at', 'admitted_at').') as avg_days')
            ->value('avg_days');

        return [
            'current_admissions' => (int) $currentAdmissions,
            'discharged_in_period' => (int) $dischargedInPeriod,
            'avg_los_days' => round((float) ($avgLos ?? 0), 1),
        ];
    }

    /**
     * Patient: new vs returning patients for a date range.
     *
     * @return array{new_patients: int, returning_patients: int, total_visits: int}
     */
    public function patientAcquisition(Carbon $from, Carbon $to): array
    {
        $tenantId = $this->tenantId();

        $newPatients = DB::table('patients')
            ->whereBetween('created_at', [$from, $to])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->count();

        $returningPatients = DB::table('appointments')
            ->join('patients', 'appointments.patient_id', '=', 'patients.id')
            ->where('patients.created_at', '<', $from->toDateString())
            ->whereBetween('appointments.appointment_date', [$from->toDateString(), $to->toDateString()])
            ->when($tenantId, fn ($q) => $q->where('appointments.tenant_id', $tenantId))
            ->distinct()
            ->count('patients.id');

        $totalVisits = DB::table('appointments')
            ->whereBetween('appointment_date', [$from->toDateString(), $to->toDateString()])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->count();

        return [
            'new_patients' => (int) $newPatients,
            'returning_patients' => (int) $returningPatients,
            'total_visits' => (int) $totalVisits,
        ];
    }

    /**
     * Patient: demographic breakdown by gender.
     *
     * @return array<string, int>
     */
    public function patientDemographics(): array
    {
        $tenantId = $this->tenantId();

        return DB::table('patients')
            ->select('gender', DB::raw('count(*) as count'))
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->groupBy('gender')
            ->pluck('count', 'gender')
            ->map(fn ($v) => (int) $v)
            ->toArray();
    }

    /**
     * Treatment: booking summary for a date range.
     *
     * @return array<string, int>
     */
    public function treatmentSummary(Carbon $from, Carbon $to): array
    {
        $tenantId = $this->tenantId();

        $rows = DB::table('treatment_bookings')
            ->select('status', DB::raw('count(*) as count'))
            ->whereBetween('created_at', [$from, $to])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'booked' => (int) ($rows['BOOKED'] ?? 0),
            'in_progress' => (int) ($rows['IN_PROGRESS'] ?? 0),
            'completed' => (int) ($rows['COMPLETED'] ?? 0),
            'cancelled' => (int) ($rows['CANCELLED'] ?? 0),
            'total' => (int) $rows->sum(),
        ];
    }

    /**
     * Comprehensive dashboard report combining all categories.
     *
     * @return array<string, mixed>
     */
    /**
     * Operational: appointments split by booking channel.
     *
     * @return array<string, int>
     */
    public function appointmentsByType(Carbon $from, Carbon $to): array
    {
        $tenantId = $this->tenantId();

        $rows = DB::table('appointments')
            ->select('type', DB::raw('count(*) as count'))
            ->whereBetween('appointment_date', [$from->toDateString(), $to->toDateString()])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->groupBy('type')
            ->pluck('count', 'type');

        return [
            'walk_in' => (int) ($rows['WALK_IN'] ?? 0),
            'online' => (int) ($rows['ONLINE'] ?? 0),
            'in_person' => (int) ($rows['IN_PERSON'] ?? 0),
            'follow_up' => (int) ($rows['FOLLOW_UP'] ?? 0),
        ];
    }

    /**
     * Clinical: follow-up pipeline for a date range (by due date).
     *
     * @return array<string, int>
     */
    public function followupSummary(Carbon $from, Carbon $to): array
    {
        $tenantId = $this->tenantId();

        $rows = DB::table('followups')
            ->select('status', DB::raw('count(*) as count'))
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'pending' => (int) ($rows['PENDING'] ?? 0),
            'completed' => (int) ($rows['COMPLETED'] ?? 0),
            'missed' => (int) ($rows['MISSED'] ?? 0),
            'rescheduled' => (int) ($rows['RESCHEDULED'] ?? 0),
            'cancelled' => (int) ($rows['CANCELLED'] ?? 0),
            'total' => (int) $rows->sum(),
        ];
    }

    /**
     * Financial: refunds issued, expenses recorded, and cash-register
     * variance for a date range. Amounts in rupees.
     *
     * @return array{refunds: float, refund_count: int, expenses: float, expense_count: int, cash_variance: float}
     */
    public function financeExtras(Carbon $from, Carbon $to): array
    {
        $tenantId = $this->tenantId();

        $refunds = DB::table('refunds')
            ->where('status', 'COMPLETED')
            ->whereBetween('refunded_at', [$from, $to])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(amount_cents), 0) as total')
            ->first();

        $expenses = DB::table('expenses')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(amount_cents), 0) as total')
            ->first();

        $variance = DB::table('cash_registers')
            ->where('status', 'CLOSED')
            ->whereNotNull('variance_cents')
            ->whereBetween('closed_at', [$from, $to])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->sum('variance_cents');

        return [
            'refunds' => ((int) $refunds->total) / 100.0,
            'refund_count' => (int) $refunds->cnt,
            'expenses' => ((int) $expenses->total) / 100.0,
            'expense_count' => (int) $expenses->cnt,
            'cash_variance' => ((int) $variance) / 100.0,
        ];
    }

    public function dashboard(Carbon $from, Carbon $to): array
    {
        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'operational' => [
                'appointments' => $this->appointmentSummary($from, $to),
                'appointments_by_type' => $this->appointmentsByType($from, $to),
                'avg_wait_time_minutes' => $this->averageWaitTime($from, $to),
                'ipd' => $this->ipdSummary($from, $to),
            ],
            'financial' => [
                'revenue' => $this->revenueSummary($from, $to),
                'collections_by_method' => $this->collectionsByMethod($from, $to),
                'extras' => $this->financeExtras($from, $to),
            ],
            'clinical' => [
                'followups' => $this->followupSummary($from, $to),
                'consultations_by_system' => $this->consultationsBySystem($from, $to),
                'prescriptions' => $this->prescriptionSummary($from, $to),
                'treatments' => $this->treatmentSummary($from, $to),
            ],
            'patients' => [
                'acquisition' => $this->patientAcquisition($from, $to),
                'demographics' => $this->patientDemographics(),
            ],
        ];
    }
}
