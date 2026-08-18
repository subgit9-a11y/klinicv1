<?php

declare(strict_types=1);

namespace App\Services\EMR;

use App\Models\Consultation;
use App\Models\Prescription;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Manages prescriptions for a patient — creation with items, status
 * transitions (amend/complete/cancel), and history retrieval.
 *
 * Prescriptions are immutable once COMPLETED. To change a completed
 * prescription, it must be amended (creates a new version) — the original
 * record is never deleted or overwritten.
 */
class PrescriptionService
{
    /**
     * Create a prescription with items for a patient, optionally linked to
     * a consultation.
     *
     * @param  array{notes?: string, consultation_id?: int}  $attributes
     * @param  array<int, array{medicine:string,form?:?string,strength?:?string,dose?:?string,frequency?:?string,duration?:?string,route?:?string,quantity?:?string,instructions?:?string,timing?:?string,anupana?:?string,external_application?:bool}>  $items
     */
    public function create(int $patientId, array $attributes, array $items, ?int $prescriberId = null): Prescription
    {
        return DB::transaction(function () use ($patientId, $attributes, $items, $prescriberId) {
            $prescription = Prescription::create([
                'patient_id' => $patientId,
                'consultation_id' => $attributes['consultation_id'] ?? null,
                'user_id' => $prescriberId ?? auth()->id(),
                'status' => 'ACTIVE',
                'notes' => $attributes['notes'] ?? null,
                'issued_at' => now(),
            ]);

            foreach ($items as $item) {
                $prescription->items()->create([
                    'medicine' => $item['medicine'],
                    'form' => $item['form'] ?? null,
                    'strength' => $item['strength'] ?? null,
                    'dose' => $item['dose'] ?? null,
                    'frequency' => $item['frequency'] ?? null,
                    'duration' => $item['duration'] ?? null,
                    'route' => $item['route'] ?? null,
                    'quantity' => $item['quantity'] ?? null,
                    'instructions' => $item['instructions'] ?? null,
                    'timing' => $item['timing'] ?? null,
                    'anupana' => $item['anupana'] ?? null,
                    'external_application' => $item['external_application'] ?? false,
                ]);
            }

            return $prescription->load('items');
        });
    }

    /**
     * Amend a prescription (only if ACTIVE). Creates a new prescription
     * with the updated items and marks the original as AMENDED.
     */
    public function amend(Prescription $prescription, array $attributes, array $items): Prescription
    {
        if ($prescription->status !== 'ACTIVE') {
            throw new \DomainException('Only ACTIVE prescriptions can be amended.');
        }

        return DB::transaction(function () use ($prescription, $attributes, $items) {
            $prescription->update(['status' => 'AMENDED']);

            return $this->create(
                $prescription->patient_id,
                array_merge($attributes, ['consultation_id' => $prescription->consultation_id]),
                $items,
                $prescription->user_id
            );
        });
    }

    public function complete(Prescription $prescription): Prescription
    {
        $prescription->update(['status' => 'COMPLETED']);

        return $prescription->refresh();
    }

    public function cancel(Prescription $prescription, ?string $reason = null): Prescription
    {
        $prescription->update([
            'status' => 'CANCELLED',
            'notes' => $reason !== null
                ? ($prescription->notes."\n[Cancelled: {$reason}]")
                : $prescription->notes,
        ]);

        return $prescription->refresh();
    }

    /**
     * Get all prescriptions for a patient, newest first, with items.
     *
     * @return Collection<int, Prescription>
     */
    public function forPatient(int $patientId): Collection
    {
        return Prescription::where('patient_id', $patientId)
            ->with('items')
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->get();
    }
}
