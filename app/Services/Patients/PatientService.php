<?php

declare(strict_types=1);

namespace App\Services\Patients;

use App\Models\Patient;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PatientService
{
    public function __construct(
        private readonly PatientUidService $uidService,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * Register a new patient. Auto-generates the permanent K360 UID, scopes to
     * the active tenant, and detects duplicate phone numbers within the
     * tenant (the (tenant_id, phone) unique constraint enforces this).
     *
     * @param  array<string, mixed>  $attributes
     * @param  array{identifiers?: array<int, array{type:string,value:string,is_primary?:bool}>, consents?: array<int, array{consent_type:string,granted?:bool,description?:string,captured_by?:int}>}  $related
     */
    public function register(array $attributes, array $related = []): Patient
    {
        $this->guardTenantContext();

        return DB::transaction(function () use ($attributes, $related) {
            $attributes['k360_uid'] = $attributes['k360_uid'] ?? $this->uidService->generate();

            $patient = new Patient($attributes);
            $patient->save();

            foreach ($related['identifiers'] ?? [] as $identifier) {
                $patient->identifiers()->create([
                    'type' => $identifier['type'],
                    'value' => $identifier['value'],
                    'is_primary' => $identifier['is_primary'] ?? false,
                ]);
            }

            foreach ($related['consents'] ?? [] as $consent) {
                $patient->consents()->create([
                    'consent_type' => $consent['consent_type'],
                    'granted' => $consent['granted'] ?? false,
                    'description' => $consent['description'] ?? null,
                    'captured_by' => $consent['captured_by'] ?? null,
                    'consented_at' => ($consent['granted'] ?? false) ? now() : null,
                ]);
            }

            return $patient->fresh();
        });
    }

    /**
     * Find a duplicate patient within the tenant by phone (the primary
     * de-duplication key). Returns null when no match exists.
     */
    public function findDuplicateByPhone(string $phone): ?Patient
    {
        $this->guardTenantContext();

        return Patient::where('phone', $phone)->first();
    }

    /**
     * Lightweight patient search by UID, phone, name, or ABHA — scoped to
     * the active tenant via the BelongsToTenant global scope.
     *
     * @return Collection<int, Patient>
     */
    public function search(string $term, int $limit = 25): Collection
    {
        $this->guardTenantContext();

        $query = Patient::query()->limit($limit);

        $query->where(function ($q) use ($term) {
            $q->where('k360_uid', 'like', $term.'%')
                ->orWhere('phone', 'like', '%'.$term.'%')
                ->orWhereRaw('lower('.(DB::connection()->getDriverName() === 'sqlite' ? 'first_name || " " || last_name' : 'CONCAT(first_name, " ", COALESCE(last_name, ""))').') like ?', ['%'.strtolower($term).'%'])
                ->orWhere('abha_id', 'like', $term.'%');
        });

        return $query->orderBy('first_name')->get();
    }

    public function findByUid(string $uid): ?Patient
    {
        $this->guardTenantContext();

        return Patient::where('k360_uid', $uid)->first();
    }

    /**
     * Update a patient's demographic/clinical attributes. The K360 UID and
     * tenant_id are never mutated.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Patient $patient, array $attributes): Patient
    {
        unset($attributes['k360_uid'], $attributes['tenant_id']);

        return DB::transaction(function () use ($patient, $attributes) {
            $patient->fill($attributes)->save();

            return $patient->fresh();
        });
    }

    private function guardTenantContext(): void
    {
        if (! $this->tenantContext->isSet()) {
            throw new \RuntimeException('Patient operations require an active tenant context.');
        }
    }
}
