<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTreatmentRoomRequest;
use App\Http\Requests\Api\StoreTreatmentServiceRequest;
use App\Http\Resources\Api\TreatmentRoomResource;
use App\Http\Resources\Api\TreatmentServiceResource;
use App\Models\TreatmentRoom;
use App\Models\TreatmentService;
use App\Services\Treatments\TreatmentCatalogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @group Treatment Catalogue
 */
class TreatmentCatalogController extends Controller
{
    public function __construct(private readonly TreatmentCatalogService $service) {}

    public function indexServices(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TreatmentService::class);

        return TreatmentServiceResource::collection(
            $this->service->listServices($request->query('category'))
        );
    }

    public function storeService(StoreTreatmentServiceRequest $request): Response
    {
        $this->authorize('create', TreatmentService::class);

        return response(TreatmentServiceResource::make($this->service->createService($request->validated())), 201);
    }

    public function showService(TreatmentService $treatment_service): Response
    {
        $this->authorize('view', $treatment_service);

        return response(TreatmentServiceResource::make($treatment_service));
    }

    public function updateService(StoreTreatmentServiceRequest $request, TreatmentService $treatment_service): Response
    {
        $this->authorize('update', $treatment_service);

        return response(TreatmentServiceResource::make($this->service->updateService($treatment_service, $request->validated())));
    }

    public function destroyService(TreatmentService $treatment_service): Response
    {
        $this->authorize('delete', $treatment_service);

        $this->service->deleteService($treatment_service);

        return response()->noContent();
    }

    public function indexRooms(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TreatmentRoom::class);

        return TreatmentRoomResource::collection($this->service->listRooms());
    }

    public function storeRoom(StoreTreatmentRoomRequest $request): Response
    {
        $this->authorize('create', TreatmentRoom::class);

        return response(TreatmentRoomResource::make($this->service->createRoom($request->validated())), 201);
    }

    public function destroyRoom(TreatmentRoom $treatment_room): Response
    {
        $this->authorize('delete', $treatment_room);

        $this->service->deleteRoom($treatment_room);

        return response()->noContent();
    }
}
