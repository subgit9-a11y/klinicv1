<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TreatmentBooking;
use App\Services\Treatments\TreatmentBookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TreatmentController extends Controller
{
    public function __construct(private readonly TreatmentBookingService $treatments) {}

    public function index(Request $request)
    {
        $date = $request->input('date') ? Carbon::parse($request->input('date')) : now();
        $bookings = TreatmentBooking::with(['patient', 'service', 'therapist', 'room'])
            ->whereDate('booking_date', $date)
            ->orderBy('start_time')
            ->paginate(25)
            ->appends(['date' => $date->toDateString()]);

        return view('treatments.index', ['bookings' => $bookings, 'date' => $date]);
    }

    public function book(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'treatment_service_id' => ['required', 'integer', 'exists:treatment_services,id'],
            'therapist_id' => ['nullable', 'integer', 'exists:users,id'],
            'treatment_room_id' => ['nullable', 'integer', 'exists:treatment_rooms,id'],
            'booking_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'payment_mode' => ['nullable', 'string', 'in:PREPAID,POSTPAID,CASH_ON_ARRIVAL'],
        ]);

        $booking = $this->treatments->book($validated);

        return redirect()->route('treatments.index')->with('status', "Treatment booked for {$booking->booking_date->format('d M Y')}.");
    }

    public function complete(TreatmentBooking $treatment)
    {
        $this->treatments->complete($treatment);

        return redirect()->route('treatments.index')->with('status', "Treatment #{$treatment->id} completed.");
    }

    public function cancel(Request $request, TreatmentBooking $treatment)
    {
        $this->treatments->cancel($treatment, $request->input('reason'));

        return redirect()->route('treatments.index')->with('status', "Treatment #{$treatment->id} cancelled.");
    }
}
