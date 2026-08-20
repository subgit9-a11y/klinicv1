<?php

declare(strict_types=1);

namespace App\Livewire\IPD;

use App\Services\Auth\Permissions;
use App\Services\IPD\IpdConfigurationService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Clinic-side IPD configuration: wards → rooms → beds hierarchy.
 * Backed by IpdConfigurationService (delete guards stay intact).
 */
class IpdConfiguration extends Component
{
    public ?int $expandedWardId = null;

    // Ward form
    public string $ward_name = '';

    public string $ward_type = 'GENERAL';

    // Room form
    public ?int $room_ward_id = null;

    public string $room_number = '';

    public string $room_type = 'GENERAL';

    // Bed form
    public ?int $bed_room_id = null;

    public string $bed_number = '';

    public ?int $bed_daily_rate_rupees = null;

    public function addWard(IpdConfigurationService $ipd): void
    {
        $this->guard();
        $this->validate(['ward_name' => 'required|string|max:120', 'ward_type' => 'required|in:GENERAL,PRIVATE,ICU,SEMI_PRIVATE,SPECIAL']);

        $ipd->createWard(['name' => $this->ward_name, 'type' => $this->ward_type]);
        session()->flash('message', "Ward {$this->ward_name} created.");
        $this->reset(['ward_name']);
    }

    public function deleteWard(int $wardId, IpdConfigurationService $ipd): void
    {
        $this->guard();

        $ward = $ipd->findWard($wardId);
        abort_if($ward === null, 404);
        try {
            $ipd->deleteWard($ward);
            session()->flash('message', 'Ward removed.');
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first());
        }
    }

    public function addRoom(IpdConfigurationService $ipd): void
    {
        $this->guard();
        $this->validate([
            'room_ward_id' => 'required|exists:ipd_wards,id',
            'room_number' => 'required|string|max:40',
            'room_type' => 'required|in:GENERAL,PRIVATE,ICU,SEMI_PRIVATE,SPECIAL',
        ]);

        $ipd->createRoom(['ipd_ward_id' => $this->room_ward_id, 'room_number' => $this->room_number, 'type' => $this->room_type]);
        session()->flash('message', "Room {$this->room_number} created.");
        $this->reset(['room_number']);
    }

    public function deleteRoom(int $roomId, IpdConfigurationService $ipd): void
    {
        $this->guard();

        $room = $ipd->listRooms()->firstWhere('id', $roomId);
        abort_if($room === null, 404);
        try {
            $ipd->deleteRoom($room);
            session()->flash('message', 'Room removed.');
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first());
        }
    }

    public function addBed(IpdConfigurationService $ipd): void
    {
        $this->guard();
        $this->validate([
            'bed_room_id' => 'required|exists:ipd_rooms,id',
            'bed_number' => 'required|string|max:40',
            'bed_daily_rate_rupees' => 'nullable|integer|min:0',
        ]);

        $ipd->createBed([
            'ipd_room_id' => $this->bed_room_id,
            'bed_number' => $this->bed_number,
            'daily_rate_cents' => $this->bed_daily_rate_rupees !== null ? $this->bed_daily_rate_rupees * 100 : null,
        ]);
        session()->flash('message', "Bed {$this->bed_number} created.");
        $this->reset(['bed_number', 'bed_daily_rate_rupees']);
    }

    public function deleteBed(int $bedId, IpdConfigurationService $ipd): void
    {
        $this->guard();

        $bed = $ipd->listBeds()->firstWhere('id', $bedId);
        abort_if($bed === null, 404);
        try {
            $ipd->deleteBed($bed);
            session()->flash('message', 'Bed removed.');
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first());
        }
    }

    public function toggleWard(int $wardId): void
    {
        $this->expandedWardId = $this->expandedWardId === $wardId ? null : $wardId;
    }

    private function guard(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasPermission(Permissions::IPD_CONFIGURE), 403);
    }

    public function render()
    {
        $this->guard();

        $ipd = app(IpdConfigurationService::class);

        return view('livewire.ipd.ipd-configuration', [
            'wards' => $ipd->listWards(),
            'rooms' => $ipd->listRooms(),
            'beds' => $ipd->listBeds(),
        ])->layout('components.layouts.app');
    }
}
