<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreConsultationRequest;
use App\Http\Resources\Api\ConsultationResource;
use App\Models\Consultation;
use App\Services\EMR\ConsultationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @group Consultations
 */
class ConsultationController extends Controller
{
    public function __construct(private readonly ConsultationService $consultationService) {}

    public function index(): AnonymousResourceCollection
    {
        $consultations = Consultation::query()
            ->with(['patient', 'doctor'])
            ->when(request()->query('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->when(request()->query('system'), fn ($q, $sys) => $q->where('medicine_system', $sys))
            ->latest()
            ->paginate(20);

        return ConsultationResource::collection($consultations);
    }

    public function store(StoreConsultationRequest $request): Response
    {
        $this->authorize('create', Consultation::class);

        $consultation = $this->consultationService->start(
            $request->validated(),
            $request->user(),
        );

        return response([
            'message' => 'Consultation created.',
            'data' => ConsultationResource::make($consultation->load(['patient', 'doctor'])),
        ], 201);
    }

    public function show(Consultation $consultation): Response
    {
        $this->authorize('view', $consultation);

        return response(['data' => ConsultationResource::make(
            $consultation->load(['patient', 'doctor', 'vitals', 'diagnoses', 'notes']),
        )]);
    }

    public function update(StoreConsultationRequest $request, Consultation $consultation): Response
    {
        $this->authorize('update', $consultation);

        $consultation = $this->consultationService->update(
            $consultation,
            $request->validated(),
            $request->user(),
        );

        return response(['data' => ConsultationResource::make($consultation->load(['patient', 'doctor']))]);
    }

    public function complete(Request $request, Consultation $consultation): Response
    {
        $this->authorize('update', $consultation);

        $consultation = $this->consultationService->complete($consultation, $request->user());

        return response(['message' => 'Consultation completed.', 'data' => ConsultationResource::make($consultation->load(['patient', 'doctor']))]);
    }

    public function amend(Request $request, Consultation $consultation): Response
    {
        $this->authorize('update', $consultation);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $consultation = $this->consultationService->amend(
            $consultation,
            $request->user(),
            $validated['reason'],
        );

        return response(['message' => 'Consultation amended.', 'data' => ConsultationResource::make($consultation->load(['patient', 'doctor']))]);
    }

    public function recordVitals(Request $request, Consultation $consultation): Response
    {
        $this->authorize('update', $consultation);

        $data = array_merge($request->all(), [
            'patient_id' => $consultation->patient_id,
            'consultation_id' => $consultation->id,
        ]);

        $vital = $this->consultationService->recordVitals($data, $request->user());

        return response(['message' => 'Vitals recorded.', 'data' => $vital], 201);
    }

    public function addDiagnosis(Request $request, Consultation $consultation): Response
    {
        $this->authorize('update', $consultation);

        $diagnosis = $this->consultationService->addDiagnosis(
            $consultation,
            $request->all(),
            $request->user(),
        );

        return response(['message' => 'Diagnosis added.', 'data' => $diagnosis], 201);
    }

    public function addNote(Request $request, Consultation $consultation): Response
    {
        $this->authorize('update', $consultation);

        $validated = $request->validate([
            'content' => ['required', 'string'],
            'note_type' => ['nullable', 'in:PROGRESS,NURSING,OBSERVATION,OTHER'],
        ]);

        $note = $this->consultationService->addNote(
            $consultation->patient_id,
            $validated['content'],
            $request->user(),
            $validated['note_type'] ?? 'PROGRESS',
            $consultation->id,
        );

        return response(['message' => 'Note added.', 'data' => $note], 201);
    }
}
