<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTeleconsultationRequest;
use App\Http\Resources\Api\TeleconsultationResource;
use App\Models\Teleconsultation;
use App\Services\Telemedicine\TeleconsultationService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @group Teleconsultations
 */
class TeleconsultationController extends Controller
{
    public function __construct(private readonly TeleconsultationService $service) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Teleconsultation::class);

        $teleconsultations = Teleconsultation::query()
            ->with(['patient', 'doctor'])
            ->when(request()->query('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->when(request()->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20);

        return TeleconsultationResource::collection($teleconsultations);
    }

    public function show(Teleconsultation $teleconsultation): Response
    {
        $this->authorize('view', $teleconsultation);

        return response(TeleconsultationResource::make($teleconsultation->load(['patient', 'doctor'])));
    }

    public function store(StoreTeleconsultationRequest $request): Response
    {
        $this->authorize('create', Teleconsultation::class);

        $teleconsultation = $this->service->schedule($request->validated());

        return response(TeleconsultationResource::make($teleconsultation->load(['patient', 'doctor'])), 201);
    }

    public function start(Teleconsultation $teleconsultation): Response
    {
        $this->authorize('start', $teleconsultation);

        $teleconsultation = $this->service->start($teleconsultation);

        return response(TeleconsultationResource::make($teleconsultation->load(['patient', 'doctor'])));
    }

    public function end(Teleconsultation $teleconsultation): Response
    {
        $this->authorize('end', $teleconsultation);

        $teleconsultation = $this->service->end($teleconsultation);

        return response(TeleconsultationResource::make($teleconsultation->load(['patient', 'doctor'])));
    }

    public function cancel(Teleconsultation $teleconsultation): Response
    {
        $this->authorize('cancel', $teleconsultation);

        $teleconsultation = $this->service->cancel($teleconsultation, (string) request()->input('reason'));

        return response(TeleconsultationResource::make($teleconsultation->load(['patient', 'doctor'])));
    }
}
