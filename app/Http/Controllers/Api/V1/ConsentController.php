<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PatientConsent;
use App\Services\EMR\ConsentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ConsentController extends Controller
{
    public function __construct(private readonly ConsentService $consentService)
    {
    }

    public function index(Request $request): Response
    {
        $patientId = (int) $request->route('patient');

        return response(['data' => $this->consentService->forPatient($patientId)]);
    }

    public function store(Request $request): Response
    {
        $this->authorize('create', PatientConsent::class);

        $validated = $request->validate([
            'consent_type' => ['required', 'in:TREATMENT,SURGERY,TELECONSULTATION,DATA_SHARING,PROCEDURE,OTHER'],
            'granted' => ['required', 'boolean'],
            'description' => ['nullable', 'string'],
            'document_path' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
            'consented_at' => ['nullable', 'date'],
        ]);

        $consent = $this->consentService->record(
            array_merge($validated, ['patient_id' => (int) $request->route('patient')]),
            $request->user()->id,
        );

        return response(['message' => 'Consent recorded.', 'data' => $consent], 201);
    }

    public function revoke(PatientConsent $consent): Response
    {
        $this->authorize('update', $consent);

        $consent = $this->consentService->revoke($consent);

        return response(['message' => 'Consent revoked.', 'data' => $consent]);
    }
}
