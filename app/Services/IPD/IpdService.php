<?php

declare(strict_types=1);

namespace App\Services\IPD;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\IpdAdmission;
use App\Models\IpdBed;
use App\Models\IpdDischargeSummary;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Manages IPD admissions: admission with bed allocation, bed transfer,
 * discharge with bill generation.
 *
 * Bed status transitions enforced:
 *  AVAILABLE → OCCUPIED (on admission)
 *  OCCUPIED → CLEANING (on discharge)
 *  CLEANING → AVAILABLE (after housekeeping, manual reset)
 */
class IpdService
{
    /**
     * Admit a patient and allocate a bed.
     *
     * @param  array{patient_id:int, ipd_bed_id?:?int, admitting_doctor_id?:?int, admission_type?:string, admission_reason?:?string, provisional_diagnosis?:?string, admitted_at?:?Carbon}  $attributes
     *
     * @throws BedNotAvailableException
     */
    public function admit(array $attributes): IpdAdmission
    {
        $bedId = $attributes['ipd_bed_id'] ?? null;

        return DB::transaction(function () use ($attributes, $bedId) {
            if ($bedId !== null) {
                $bed = IpdBed::lockForUpdate()->find($bedId);
                if ($bed === null || $bed->status !== 'AVAILABLE') {
                    throw new BedNotAvailableException('Selected bed is not available.');
                }
                $bed->update(['status' => 'OCCUPIED']);
            }

            $admission = IpdAdmission::create([
                'patient_id' => $attributes['patient_id'],
                'ipd_bed_id' => $bedId,
                'admitting_doctor_id' => $attributes['admitting_doctor_id'] ?? null,
                'ipd_number' => $this->generateIpdNumber(),
                'admission_type' => $attributes['admission_type'] ?? 'ROUTINE',
                'admission_reason' => $attributes['admission_reason'] ?? null,
                'provisional_diagnosis' => $attributes['provisional_diagnosis'] ?? null,
                'admitted_at' => $attributes['admitted_at'] ?? now(),
                'status' => 'ADMITTED',
            ]);

            return $admission->refresh();
        });
    }

    /**
     * Transfer a patient to a new bed. The old bed goes to CLEANING, the
     * new bed must be AVAILABLE and becomes OCCUPIED.
     */
    public function transferBed(IpdAdmission $admission, int $newBedId): IpdAdmission
    {
        return DB::transaction(function () use ($admission, $newBedId) {
            if ($admission->ipd_bed_id !== null) {
                $oldBed = IpdBed::lockForUpdate()->find($admission->ipd_bed_id);
                if ($oldBed !== null) {
                    $oldBed->update(['status' => 'CLEANING']);
                }
            }

            $newBed = IpdBed::lockForUpdate()->find($newBedId);
            if ($newBed === null || $newBed->status !== 'AVAILABLE') {
                throw new BedNotAvailableException('Target bed is not available.');
            }
            $newBed->update(['status' => 'OCCUPIED']);

            $admission->update(['ipd_bed_id' => $newBedId]);

            return $admission->refresh();
        });
    }

    /**
     * Discharge a patient. Releases the bed (→ CLEANING), sets discharge
     * timestamp/status, creates a discharge summary, and generates a final
     * IPD invoice with bed charges.
     *
     * @param  array{discharge_diagnosis?:?string, treatment_given?:?string, advice_on_discharge?:?string, follow_up_instructions?:?string, follow_up_days?:?int}  $summary
     */
    public function discharge(IpdAdmission $admission, array $summary = []): IpdAdmission
    {
        if ($admission->status !== 'ADMITTED') {
            throw new \DomainException('Only ADMITTED patients can be discharged.');
        }

        return DB::transaction(function () use ($admission, $summary) {
            $dischargedAt = now();
            $daysAdmitted = max(1, (int) ceil(
                $admission->admitted_at->diffInHours($dischargedAt) / 24
            ));

            if ($admission->ipd_bed_id !== null) {
                $bed = IpdBed::find($admission->ipd_bed_id);
                if ($bed !== null) {
                    $bed->update(['status' => 'CLEANING']);
                }

                $this->generateIpdInvoice($admission, $bed ?? null, $daysAdmitted, $dischargedAt);
            }

            $admission->update([
                'status' => 'DISCHARGED',
                'discharged_at' => $dischargedAt,
            ]);

            IpdDischargeSummary::create([
                'ipd_admission_id' => $admission->id,
                'user_id' => auth()->id(),
                'discharged_at' => $dischargedAt,
                'admission_diagnosis' => $admission->provisional_diagnosis,
                'discharge_diagnosis' => $summary['discharge_diagnosis'] ?? null,
                'treatment_given' => $summary['treatment_given'] ?? null,
                'advice_on_discharge' => $summary['advice_on_discharge'] ?? null,
                'follow_up_instructions' => $summary['follow_up_instructions'] ?? null,
                'follow_up_days' => $summary['follow_up_days'] ?? null,
                'status' => 'FINAL',
            ]);

            return $admission->refresh();
        });
    }

    /**
     * Reset a bed from CLEANING back to AVAILABLE (housekeeping action).
     */
    public function markBedAvailable(IpdBed $bed): IpdBed
    {
        if ($bed->status !== 'CLEANING') {
            throw new \DomainException('Only beds in CLEANING status can be marked available.');
        }

        $bed->update(['status' => 'AVAILABLE']);

        return $bed->refresh();
    }

    /**
     * Get all active admissions for the tenant.
     *
     * @return Collection<int, IpdAdmission>
     */
    public function activeAdmissions(): Collection
    {
        return IpdAdmission::where('status', 'ADMITTED')
            ->with(['patient', 'bed.room.ward'])
            ->orderByDesc('admitted_at')
            ->get();
    }

    /**
     * Find available beds in the tenant.
     *
     * @return Collection<int, IpdBed>
     */
    public function availableBeds(): Collection
    {
        return IpdBed::where('status', 'AVAILABLE')
            ->with('room.ward')
            ->orderBy('bed_number')
            ->get();
    }

    private function generateIpdNumber(): string
    {
        return 'K360-IPD-'.str_pad((string) (IpdAdmission::max('id') + 1), 6, '0', STR_PAD_LEFT);
    }

    private function generateIpdInvoice(IpdAdmission $admission, ?IpdBed $bed, int $days, Carbon $dischargedAt): void
    {
        $invoice = Invoice::create([
            'tenant_id' => $admission->tenant_id,
            'patient_id' => $admission->patient_id,
            'ipd_admission_id' => $admission->id,
            'invoice_number' => 'K360-INV-IPD-'.$admission->id,
            'status' => 'DRAFT',
            'source' => 'IPD',
            'currency' => 'INR',
            'issued_at' => $dischargedAt,
        ]);

        if ($bed !== null && $bed->daily_rate_cents > 0) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => "Room charges ({$days} day(s)) — Bed {$bed->bed_number}",
                'type' => 'IPD_ROOM',
                'quantity' => $days,
                'unit_price_cents' => $bed->daily_rate_cents,
                'total_cents' => $days * $bed->daily_rate_cents,
                'reference_type' => $bed->getMorphClass(),
                'reference_id' => $bed->id,
            ]);
        }
    }
}
