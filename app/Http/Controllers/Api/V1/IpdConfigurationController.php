<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreIpdBedRequest;
use App\Http\Requests\Api\StoreIpdRoomRequest;
use App\Http\Requests\Api\StoreIpdWardRequest;
use App\Http\Resources\Api\IpdBedResource;
use App\Http\Resources\Api\IpdRoomResource;
use App\Http\Resources\Api\IpdWardResource;
use App\Models\IpdBed;
use App\Models\IpdRoom;
use App\Models\IpdWard;
use App\Services\IPD\IpdConfigurationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @group IPD Configuration
 */
class IpdConfigurationController extends Controller
{
    public function __construct(private readonly IpdConfigurationService $service) {}

    public function indexWards(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', IpdWard::class);

        return IpdWardResource::collection($this->service->listWards());
    }

    public function storeWard(StoreIpdWardRequest $request): Response
    {
        $this->authorize('create', IpdWard::class);

        return response(IpdWardResource::make($this->service->createWard($request->validated())), 201);
    }

    public function showWard(IpdWard $ipd_ward): Response
    {
        $this->authorize('view', $ipd_ward);

        return response(IpdWardResource::make($ipd_ward));
    }

    public function updateWard(StoreIpdWardRequest $request, IpdWard $ipd_ward): Response
    {
        $this->authorize('update', $ipd_ward);

        return response(IpdWardResource::make($this->service->updateWard($ipd_ward, $request->validated())));
    }

    public function destroyWard(IpdWard $ipd_ward): Response
    {
        $this->authorize('delete', $ipd_ward);

        $this->service->deleteWard($ipd_ward);

        return response()->noContent();
    }

    public function indexRooms(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', IpdRoom::class);

        return IpdRoomResource::collection(
            $this->service->listRooms($request->query('ward_id') ? (int) $request->query('ward_id') : null)
        );
    }

    public function storeRoom(StoreIpdRoomRequest $request): Response
    {
        $this->authorize('create', IpdRoom::class);

        return response(IpdRoomResource::make($this->service->createRoom($request->validated())), 201);
    }

    public function destroyRoom(IpdRoom $ipd_room): Response
    {
        $this->authorize('delete', $ipd_room);

        $this->service->deleteRoom($ipd_room);

        return response()->noContent();
    }

    public function indexBeds(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', IpdBed::class);

        return IpdBedResource::collection(
            $this->service->listBeds(
                $request->query('room_id') ? (int) $request->query('room_id') : null,
                $request->query('status')
            )
        );
    }

    public function storeBed(StoreIpdBedRequest $request): Response
    {
        $this->authorize('create', IpdBed::class);

        return response(IpdBedResource::make($this->service->createBed($request->validated())), 201);
    }

    public function updateBedStatus(Request $request, IpdBed $ipd_bed): Response
    {
        $this->authorize('update', $ipd_bed);

        $data = $request->validate(['status' => ['required', 'in:AVAILABLE,OCCUPIED,MAINTENANCE,OUT_OF_SERVICE']]);

        return response(IpdBedResource::make($this->service->updateBedStatus($ipd_bed, $data['status'])));
    }

    public function destroyBed(IpdBed $ipd_bed): Response
    {
        $this->authorize('delete', $ipd_bed);

        $this->service->deleteBed($ipd_bed);

        return response()->noContent();
    }
}
