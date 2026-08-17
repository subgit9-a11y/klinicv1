<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreTreatmentBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'treatment_service_id' => ['required', 'integer', 'exists:treatment_services,id'],
            'therapist_id' => ['nullable', 'integer', 'exists:therapists,id'],
            'treatment_room_id' => ['nullable', 'integer', 'exists:treatment_rooms,id'],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'payment_mode' => ['nullable', 'in:PAY_AT_CLINIC,PREPAID,INSURANCE'],
        ];
    }
}
