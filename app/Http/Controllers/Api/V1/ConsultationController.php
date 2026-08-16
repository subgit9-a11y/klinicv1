<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreConsultationRequest;
use App\Http\Resources\Api\ConsultationResource;
use App\Models\Consultation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ConsultationController extends Controller
{
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

        $data = array_merge($request->validated(), [
            'user_id' => $request->user()->id,
        ]);

        $consultation = Consultation::create($data);

        return response([
            'message' => 'Consultation created.',
            'data' => ConsultationResource::make($consultation->load(['patient', 'doctor'])),
        ], 201);
    }

    public function show(Consultation $consultation): Response
    {
        $this->authorize('view', $consultation);

        return response(ConsultationResource::make($consultation->load(['patient', 'doctor'])));
    }
}
