<?php

declare(strict_types=1);

namespace App\Services\Treatments;

use App\Models\TreatmentPackage;
use App\Models\TreatmentRoom;
use App\Models\TreatmentService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * CRUD for the treatment catalogue: services, packages, and rooms. This is the
 * configuration layer (distinct from TreatmentBookingService which handles
 * operational booking). All operations are tenant-scoped.
 */
class TreatmentCatalogService
{
    /**
     * @return Collection<int, TreatmentService>
     */
    public function listServices(?string $category = null): Collection
    {
        $query = TreatmentService::query()->orderBy('name');
        if ($category) {
            $query->where('category', $category);
        }

        return $query->get();
    }

    public function findService(int $id): ?TreatmentService
    {
        return TreatmentService::find($id);
    }

    /**
     * @param  array{name:string,category?:?string,medicine_system?:?string,duration_minutes?:?int,price_cents?:?int,currency?:?string,description?:?string,requires_therapist?:bool,requires_room?:bool,is_active?:bool}  $attributes
     */
    public function createService(array $attributes): TreatmentService
    {
        $validated = Validator::validate($attributes, [
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:80'],
            'medicine_system' => ['nullable', 'in:AYURVEDA,SIDDHA,HOMEOPATHY,GENERAL'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'price_cents' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3'],
            'description' => ['nullable', 'string'],
            'requires_therapist' => ['nullable', 'boolean'],
            'requires_room' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return TreatmentService::create(array_merge([
            'currency' => 'INR',
            'is_active' => true,
        ], $validated));
    }

    public function updateService(TreatmentService $service, array $attributes): TreatmentService
    {
        $validated = Validator::make($attributes, [
            'name' => ['sometimes', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:80'],
            'medicine_system' => ['nullable', 'in:AYURVEDA,SIDDHA,HOMEOPATHY,GENERAL'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'price_cents' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'requires_therapist' => ['nullable', 'boolean'],
            'requires_room' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ])->validate();

        $service->update($validated);

        return $service->refresh();
    }

    public function deleteService(TreatmentService $service): void
    {
        $service->delete();
    }

    /**
     * @return Collection<int, TreatmentRoom>
     */
    public function listRooms(): Collection
    {
        return TreatmentRoom::orderBy('room_number')->get();
    }

    /**
     * @param  array{room_number:string,type?:?string,capacity?:?int,supported_treatment_types?:?string,status?:?string}  $attributes
     */
    public function createRoom(array $attributes): TreatmentRoom
    {
        $validated = Validator::validate($attributes, [
            'room_number' => ['required', 'string', 'max:40'],
            'type' => ['nullable', 'string', 'max:40'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'supported_treatment_types' => ['nullable', 'string'],
            'status' => ['nullable', 'in:AVAILABLE,OCCUPIED,MAINTENANCE,OUT_OF_SERVICE'],
        ]);

        return TreatmentRoom::create(array_merge([
            'capacity' => 1,
            'status' => 'AVAILABLE',
        ], $validated));
    }

    public function deleteRoom(TreatmentRoom $room): void
    {
        $room->delete();
    }

    /**
     * @return Collection<int, TreatmentPackage>
     */
    public function listPackages(): Collection
    {
        return TreatmentPackage::orderBy('name')->get();
    }
}
