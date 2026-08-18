<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreIpdAdmissionRequest;
use App\Http\Resources\Api\IpdAdmissionResource;
use App\Models\IpdAdmission;
use App\Services\IPD\IpdService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @group IPD Admissions
 */
class IpdAdmissionController extends Controller
{
    public function __construct(private readonly IpdService $ipdService) {}

    public function index(): AnonymousResourceCollection
    {
        $admissions = IpdAdmission::query()
            ->with('patient')
            ->when(request()->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when(request()->query('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->latest()
            ->paginate(20);

        return IpdAdmissionResource::collection($admissions);
    }

    public function store(StoreIpdAdmissionRequest $request): Response
    {
        $this->authorize('admit', IpdAdmission::class);

        $admission = $this->ipdService->admit($request->validated());

        return response([
            'message' => 'Patient admitted.',
            'data' => IpdAdmissionResource::make($admission->load('patient')),
        ], 201);
    }

    public function show(IpdAdmission $ipdAdmission): Response
    {
        $this->authorize('view', $ipdAdmission);

        return response(IpdAdmissionResource::make($ipdAdmission->load('patient')));
    }

    public function discharge(IpdAdmission $ipdAdmission): Response
    {
        $this->authorize('discharge', $ipdAdmission);

        $summary = request()->validate([
            'discharge_diagnosis' => ['nullable', 'string', 'max:255'],
            'treatment_given' => ['nullable', 'string'],
            'advice_on_discharge' => ['nullable', 'string'],
            'follow_up_instructions' => ['nullable', 'string'],
            'follow_up_days' => ['nullable', 'integer', 'min:0'],
        ]);

        $admission = $this->ipdService->discharge($ipdAdmission, $summary);

        return response([
            'message' => 'Patient discharged.',
            'data' => IpdAdmissionResource::make($admission->load('patient')),
        ]);
    }
}
