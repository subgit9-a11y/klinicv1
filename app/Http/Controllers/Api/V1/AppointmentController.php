<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAppointmentRequest;
use App\Http\Resources\Api\AppointmentResource;
use App\Models\Appointment;
use App\Services\Appointments\AppointmentService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @group Appointments
 */
class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentService $appointmentService) {}

    public function index(): AnonymousResourceCollection
    {
        $query = Appointment::query()->with(['patient', 'doctor']);

        if ($date = request()->query('date')) {
            $query->whereDate('appointment_date', $date);
        }

        if ($status = request()->query('status')) {
            $query->where('status', $status);
        }

        $appointments = $query->latest('appointment_date')->paginate(20);

        return AppointmentResource::collection($appointments);
    }

    public function store(StoreAppointmentRequest $request): Response
    {
        $appointment = $this->appointmentService->book(
            $request->validated(),
            $request->user(),
        );

        return response([
            'message' => 'Appointment booked.',
            'data' => AppointmentResource::make($appointment->load(['patient', 'doctor'])),
        ], 201);
    }

    public function show(Appointment $appointment): Response
    {
        $this->authorize('view', $appointment);

        return response(AppointmentResource::make($appointment->load(['patient', 'doctor'])));
    }

    public function cancel(Appointment $appointment): Response
    {
        $this->authorize('update', $appointment);

        $reason = (string) request()->input('reason', '');
        $appointment = $this->appointmentService->cancel($appointment, request()->user(), $reason !== '' ? $reason : null);

        return response([
            'message' => 'Appointment cancelled.',
            'data' => AppointmentResource::make($appointment),
        ]);
    }
}
