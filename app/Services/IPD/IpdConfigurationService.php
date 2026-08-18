<?php

declare(strict_types=1);

namespace App\Services\IPD;

use App\Models\IpdBed;
use App\Models\IpdRoom;
use App\Models\IpdWard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Configuration CRUD for IPD infrastructure: wards → rooms → beds. Distinct
 * from IpdService which handles admission/discharge. All operations are
 * tenant-scoped via the models' BelongsToTenant scope.
 */
class IpdConfigurationService
{
    /**
     * @return Collection<int, IpdWard>
     */
    public function listWards(): Collection
    {
        return IpdWard::withCount('rooms')->orderBy('name')->get();
    }

    public function findWard(int $id): ?IpdWard
    {
        return IpdWard::find($id);
    }

    /**
     * @param  array{name:string,type?:?string,is_active?:bool}  $attributes
     */
    public function createWard(array $attributes): IpdWard
    {
        $validated = Validator::validate($attributes, [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['nullable', 'in:GENERAL,PRIVATE,ICU,SEMI_PRIVATE,SPECIAL'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return IpdWard::create(array_merge(['is_active' => true], $validated));
    }

    public function updateWard(IpdWard $ward, array $attributes): IpdWard
    {
        $validated = Validator::make($attributes, [
            'name' => ['sometimes', 'string', 'max:120'],
            'type' => ['nullable', 'in:GENERAL,PRIVATE,ICU,SEMI_PRIVATE,SPECIAL'],
            'is_active' => ['sometimes', 'boolean'],
        ])->validate();

        $ward->update($validated);

        return $ward->refresh();
    }

    public function deleteWard(IpdWard $ward): void
    {
        if ($ward->rooms()->exists()) {
            throw ValidationException::withMessages(['ward' => 'Cannot delete a ward that still has rooms.']);
        }
        $ward->delete();
    }

    /**
     * @return Collection<int, IpdRoom>
     */
    public function listRooms(?int $wardId = null): Collection
    {
        $query = IpdRoom::with('ward')->orderBy('room_number');
        if ($wardId) {
            $query->where('ipd_ward_id', $wardId);
        }

        return $query->get();
    }

    /**
     * @param  array{ipd_ward_id:int,room_number:string,type?:?string,status?:?string}  $attributes
     */
    public function createRoom(array $attributes): IpdRoom
    {
        $validated = Validator::validate($attributes, [
            'ipd_ward_id' => ['required', 'exists:ipd_wards,id'],
            'room_number' => ['required', 'string', 'max:40'],
            'type' => ['nullable', 'in:GENERAL,PRIVATE,ICU,SEMI_PRIVATE,SPECIAL'],
            'status' => ['nullable', 'in:AVAILABLE,OCCUPIED,MAINTENANCE,OUT_OF_SERVICE'],
        ]);

        return IpdRoom::create(array_merge(['status' => 'AVAILABLE'], $validated));
    }

    public function deleteRoom(IpdRoom $room): void
    {
        if ($room->beds()->exists()) {
            throw ValidationException::withMessages(['room' => 'Cannot delete a room that still has beds.']);
        }
        $room->delete();
    }

    /**
     * @return Collection<int, IpdBed>
     */
    public function listBeds(?int $roomId = null, ?string $status = null): Collection
    {
        $query = IpdBed::with('room.ward')->orderBy('bed_number');
        if ($roomId) {
            $query->where('ipd_room_id', $roomId);
        }
        if ($status) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    /**
     * @param  array{ipd_room_id:int,bed_number:string,daily_rate_cents?:?int,status?:?string}  $attributes
     */
    public function createBed(array $attributes): IpdBed
    {
        $validated = Validator::validate($attributes, [
            'ipd_room_id' => ['required', 'exists:ipd_rooms,id'],
            'bed_number' => ['required', 'string', 'max:40'],
            'daily_rate_cents' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'in:AVAILABLE,OCCUPIED,MAINTENANCE,OUT_OF_SERVICE'],
        ]);

        return IpdBed::create(array_merge([
            'status' => 'AVAILABLE',
            'daily_rate_cents' => 0,
        ], $validated));
    }

    public function updateBedStatus(IpdBed $bed, string $status): IpdBed
    {
        Validator::validate(['status' => $status], [
            'status' => ['required', 'in:AVAILABLE,OCCUPIED,MAINTENANCE,OUT_OF_SERVICE'],
        ]);

        $bed->update(['status' => $status]);

        return $bed->refresh();
    }

    public function deleteBed(IpdBed $bed): void
    {
        if ($bed->status === 'OCCUPIED') {
            throw ValidationException::withMessages(['bed' => 'Cannot delete an occupied bed.']);
        }
        $bed->delete();
    }
}
