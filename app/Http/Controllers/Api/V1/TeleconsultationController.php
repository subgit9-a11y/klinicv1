<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\TeleconsultationResource;
use App\Models\Teleconsultation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @group Teleconsultations
 */
class TeleconsultationController extends Controller
{
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
}
