<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTreatmentBookingRequest;
use App\Http\Resources\Api\TreatmentBookingResource;
use App\Models\TreatmentBooking;
use App\Services\Treatments\TreatmentBookingService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @group Treatments
 */
class TreatmentBookingController extends Controller
{
    public function __construct(private readonly TreatmentBookingService $bookingService) {}

    public function index(): AnonymousResourceCollection
    {
        $bookings = TreatmentBooking::query()
            ->with('patient')
            ->when(request()->query('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->when(request()->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when(request()->query('date'), fn ($q, $d) => $q->whereDate('booking_date', $d))
            ->latest()
            ->paginate(20);

        return TreatmentBookingResource::collection($bookings);
    }

    public function store(StoreTreatmentBookingRequest $request): Response
    {
        $this->authorize('create', TreatmentBooking::class);

        $booking = $this->bookingService->book($request->validated());

        return response([
            'message' => 'Treatment booking created.',
            'data' => TreatmentBookingResource::make($booking->load('patient')),
        ], 201);
    }

    public function show(TreatmentBooking $treatmentBooking): Response
    {
        $this->authorize('view', $treatmentBooking);

        return response(TreatmentBookingResource::make($treatmentBooking->load('patient')));
    }

    public function complete(TreatmentBooking $treatmentBooking): Response
    {
        $this->authorize('update', $treatmentBooking);

        $booking = $this->bookingService->complete($treatmentBooking);

        return response([
            'message' => 'Treatment completed.',
            'data' => TreatmentBookingResource::make($booking->load('patient')),
        ]);
    }

    public function cancel(TreatmentBooking $treatmentBooking): Response
    {
        $this->authorize('update', $treatmentBooking);

        $reason = (string) request()->input('reason', '');
        $booking = $this->bookingService->cancel($treatmentBooking, $reason !== '' ? $reason : null);

        return response([
            'message' => 'Treatment cancelled.',
            'data' => TreatmentBookingResource::make($booking->load('patient')),
        ]);
    }
}
