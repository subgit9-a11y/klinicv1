<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePatientRequest;
use App\Http\Resources\Api\PatientResource;
use App\Models\Patient;
use App\Services\Patients\PatientService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @group Patients
 */
class PatientController extends Controller
{
    public function __construct(private readonly PatientService $patientService) {}

    public function index(): AnonymousResourceCollection
    {
        $patients = Patient::query()
            ->when($this->search(), fn ($q, $term) => $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('k360_uid', 'like', "%{$term}%"))
            ->latest()
            ->paginate(20);

        return PatientResource::collection($patients);
    }

    public function store(StorePatientRequest $request): Response
    {
        $patient = $this->patientService->register($request->validated());

        return response([
            'message' => 'Patient registered.',
            'data' => PatientResource::make($patient),
        ], 201);
    }

    public function show(Patient $patient): Response
    {
        $this->authorize('view', $patient);

        return response(PatientResource::make($patient));
    }

    public function update(StorePatientRequest $request, Patient $patient): Response
    {
        $this->authorize('update', $patient);
        $patient = $this->patientService->update($patient, $request->validated());

        return response(PatientResource::make($patient));
    }

    public function destroy(Patient $patient): Response
    {
        $this->authorize('delete', $patient);
        $patient->delete();

        return response(null, 204);
    }

    private function search(): ?string
    {
        $term = request()->query('search');

        return is_string($term) && $term !== '' ? $term : null;
    }
}
