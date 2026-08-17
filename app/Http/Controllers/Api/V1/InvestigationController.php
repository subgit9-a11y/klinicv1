<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Investigation;
use App\Services\EMR\InvestigationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvestigationController extends Controller
{
    public function __construct(private readonly InvestigationService $investigationService)
    {
    }

    public function index(Request $request): Response
    {
        $patientId = (int) $request->route('patient');

        return response(['data' => $this->investigationService->forPatient($patientId)]);
    }

    public function store(Request $request): Response
    {
        $this->authorize('create', Investigation::class);

        $validated = $request->validate([
            'consultation_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'in:LAB,RADIOLOGY,CARDIAC,PATHOLOGY,OTHER'],
            'status' => ['nullable', 'in:REQUESTED,IN_PROGRESS,COMPLETED,CANCELLED'],
            'requested_at' => ['nullable', 'date'],
        ]);

        $investigation = $this->investigationService->order(array_merge($validated, [
            'patient_id' => (int) $request->route('patient'),
        ]));

        return response(['message' => 'Investigation ordered.', 'data' => $investigation], 201);
    }

    public function update(Request $request, Investigation $investigation): Response
    {
        $this->authorize('update', $investigation);

        $validated = $request->validate([
            'status' => ['nullable', 'in:REQUESTED,IN_PROGRESS,COMPLETED,CANCELLED'],
            'completed_at' => ['nullable', 'date'],
            'name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'in:LAB,RADIOLOGY,CARDIAC,PATHOLOGY,OTHER'],
        ]);

        $investigation = $this->investigationService->update($investigation, $validated);

        return response(['message' => 'Investigation updated.', 'data' => $investigation]);
    }
}
