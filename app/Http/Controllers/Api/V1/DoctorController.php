<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OnboardDoctorRequest;
use App\Http\Requests\Api\SetDoctorAvailabilityRequest;
use App\Http\Requests\Api\UpdateDoctorProfileRequest;
use App\Http\Resources\Api\DoctorAvailabilityResource;
use App\Http\Resources\Api\DoctorResource;
use App\Models\User;
use App\Services\Staff\DoctorService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @group Doctor Onboarding & Availability
 */
class DoctorController extends Controller
{
    public function __construct(private readonly DoctorService $service) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        return DoctorResource::collection($this->service->listDoctors()->loadMissing('availability'));
    }

    public function store(OnboardDoctorRequest $request): Response
    {
        $this->authorize('create', User::class);

        $doctor = $this->service->onboard($request->validated());

        return response(DoctorResource::make($doctor), 201);
    }

    public function show(User $doctor): Response
    {
        $this->authorize('view', $doctor);

        return response(DoctorResource::make($doctor->loadMissing('availability')));
    }

    public function update(UpdateDoctorProfileRequest $request, User $doctor): Response
    {
        $this->authorize('update', $doctor);

        return response(DoctorResource::make($this->service->updateProfile($doctor, $request->validated())));
    }

    public function availability(User $doctor): Response
    {
        $this->authorize('view', $doctor);

        return response(DoctorAvailabilityResource::collection($this->service->getAvailability($doctor)));
    }

    public function setAvailability(SetDoctorAvailabilityRequest $request, User $doctor): Response
    {
        $this->authorize('update', $doctor);

        $slots = $this->service->setAvailability($doctor, $request->input('slots'));

        return response(DoctorAvailabilityResource::collection($slots));
    }
}
