<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'exists:patients,id'],
            'appointment_id' => ['nullable', 'exists:appointments,id'],
            'medicine_system' => ['required', 'in:GENERAL,AYURVEDA,SIDDHA,HOMEOPATHY'],
            'consultation_type' => ['nullable', 'in:OPD,ONLINE,FOLLOW_UP,IPD'],
            'chief_complaint' => ['nullable', 'string'],
            'history' => ['nullable', 'string'],
            'examination' => ['nullable', 'string'],
            'assessment' => ['nullable', 'string'],
            'diagnosis_summary' => ['nullable', 'string'],
            'treatment_plan' => ['nullable', 'string'],
            'advice' => ['nullable', 'string'],
            'follow_up_instructions' => ['nullable', 'string'],
            'follow_up_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'system_specific' => ['nullable', 'array'],
        ];
    }
}
