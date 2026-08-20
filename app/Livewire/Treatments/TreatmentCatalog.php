<?php

declare(strict_types=1);

namespace App\Livewire\Treatments;

use App\Services\Auth\Permissions;
use App\Services\Treatments\TreatmentCatalogService;
use Livewire\Component;

/**
 * Clinic-side treatment catalogue administration: services and rooms.
 * Backed by TreatmentCatalogService (same service the API uses).
 */
class TreatmentCatalog extends Component
{
    public string $activeTab = 'services';

    public bool $showServiceForm = false;

    public bool $showRoomForm = false;

    // Service form
    public string $service_name = '';

    public ?string $service_category = null;

    public ?int $service_duration_minutes = null;

    public ?int $service_price_rupees = null;

    public ?string $service_description = null;

    public bool $service_requires_therapist = false;

    public bool $service_requires_room = false;

    // Room form
    public string $room_number = '';

    public ?string $room_type = null;

    public int $room_capacity = 1;

    public ?string $room_supported = null;

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function addService(TreatmentCatalogService $catalog): void
    {
        $this->guard();

        $this->validate([
            'service_name' => 'required|string|max:150',
            'service_category' => 'nullable|string|max:80',
            'service_duration_minutes' => 'nullable|integer|min:1',
            'service_price_rupees' => 'nullable|integer|min:0',
            'service_description' => 'nullable|string',
        ]);

        $catalog->createService([
            'name' => $this->service_name,
            'category' => $this->service_category,
            'duration_minutes' => $this->service_duration_minutes,
            'price_cents' => $this->service_price_rupees !== null ? $this->service_price_rupees * 100 : null,
            'description' => $this->service_description,
            'requires_therapist' => $this->service_requires_therapist,
            'requires_room' => $this->service_requires_room,
            'medicine_system' => auth()->user()->tenant?->system,
        ]);

        session()->flash('message', "Service {$this->service_name} created.");
        $this->reset(['service_name', 'service_category', 'service_duration_minutes', 'service_price_rupees', 'service_description', 'service_requires_therapist', 'service_requires_room']);
        $this->showServiceForm = false;
    }

    public function toggleService(int $serviceId, TreatmentCatalogService $catalog): void
    {
        $this->guard();

        $service = $catalog->findService($serviceId);
        abort_if($service === null, 404);
        $catalog->updateService($service, ['is_active' => ! $service->is_active]);
    }

    public function deleteService(int $serviceId, TreatmentCatalogService $catalog): void
    {
        $this->guard();

        $service = $catalog->findService($serviceId);
        abort_if($service === null, 404);
        $catalog->deleteService($service);

        session()->flash('message', 'Service removed.');
    }

    public function addRoom(TreatmentCatalogService $catalog): void
    {
        $this->guard();

        $this->validate([
            'room_number' => 'required|string|max:40',
            'room_type' => 'nullable|string|max:40',
            'room_capacity' => 'required|integer|min:1',
            'room_supported' => 'nullable|string',
        ]);

        $catalog->createRoom([
            'room_number' => $this->room_number,
            'type' => $this->room_type,
            'capacity' => $this->room_capacity,
            'supported_treatment_types' => $this->room_supported,
        ]);

        session()->flash('message', "Room {$this->room_number} created.");
        $this->reset(['room_number', 'room_type', 'room_supported']);
        $this->room_capacity = 1;
        $this->showRoomForm = false;
    }

    public function deleteRoom(int $roomId, TreatmentCatalogService $catalog): void
    {
        $this->guard();

        $room = $catalog->listRooms()->firstWhere('id', $roomId);
        abort_if($room === null, 404);
        $catalog->deleteRoom($room);

        session()->flash('message', 'Room removed.');
    }

    private function guard(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasPermission(Permissions::TREATMENTS_MANAGE), 403);
    }

    public function render()
    {
        $this->guard();

        $catalog = app(TreatmentCatalogService::class);

        return view('livewire.treatments.treatment-catalog', [
            'services' => $catalog->listServices(),
            'rooms' => $catalog->listRooms(),
        ])->layout('components.layouts.app');
    }
}
