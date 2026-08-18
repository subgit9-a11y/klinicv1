<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePrescriptionRequest;
use App\Http\Resources\Api\PrescriptionResource;
use App\Models\Prescription;
use App\Services\EMR\PrescriptionService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @group Prescriptions
 */
class PrescriptionController extends Controller
{
    public function __construct(private readonly PrescriptionService $prescriptionService) {}

    public function index(): AnonymousResourceCollection
    {
        $prescriptions = Prescription::query()
            ->with(['patient', 'items'])
            ->when(request()->query('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->when(request()->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20);

        return PrescriptionResource::collection($prescriptions);
    }

    public function store(StorePrescriptionRequest $request): Response
    {
        $this->authorize('create', Prescription::class);

        $validated = $request->validated();

        $prescription = $this->prescriptionService->create(
            patientId: (int) request()->route('patient'),
            attributes: ['consultation_id' => $validated['consultation_id'] ?? null, 'notes' => $validated['notes'] ?? null],
            items: $validated['items'],
            prescriberId: $request->user()->id,
        );

        return response([
            'message' => 'Prescription created.',
            'data' => PrescriptionResource::make($prescription->load(['patient', 'items'])),
        ], 201);
    }

    public function show(Prescription $prescription): Response
    {
        $this->authorize('view', $prescription);

        return response(PrescriptionResource::make($prescription->load(['patient', 'items'])));
    }
}
