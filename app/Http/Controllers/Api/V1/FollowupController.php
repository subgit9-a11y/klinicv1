<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Followup;
use App\Services\EMR\FollowupService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * @group Follow-ups
 */
class FollowupController extends Controller
{
    public function __construct(private readonly FollowupService $followupService) {}

    public function index(Request $request): Response
    {
        $patientId = (int) $request->route('patient');

        return response(['data' => $this->followupService->forPatient($patientId)]);
    }

    public function store(Request $request): Response
    {
        $this->authorize('create', Followup::class);

        $validated = $request->validate([
            'consultation_id' => ['nullable', 'integer'],
            'appointment_id' => ['nullable', 'integer'],
            'due_date' => ['required', 'date'],
            'instructions' => ['nullable', 'string'],
            'status' => ['nullable', 'in:PENDING,COMPLETED,CANCELLED'],
        ]);

        $followup = $this->followupService->schedule(array_merge($validated, [
            'patient_id' => (int) $request->route('patient'),
        ]));

        return response(['message' => 'Follow-up scheduled.', 'data' => $followup], 201);
    }

    public function updateStatus(Request $request, Followup $followup): Response
    {
        $this->authorize('update', $followup);

        $validated = $request->validate([
            'status' => ['required', 'in:PENDING,COMPLETED,CANCELLED'],
        ]);

        $followup = $this->followupService->updateStatus($followup, $validated['status']);

        return response(['message' => 'Follow-up updated.', 'data' => $followup]);
    }
}
