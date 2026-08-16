<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreIpdAdmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'ipd_bed_id' => ['nullable', 'integer', 'exists:ipd_beds,id'],
            'admitting_doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'admission_type' => ['nullable', 'in:ROUTINE,EMERGENCY,DAY_CARE'],
            'admission_reason' => ['nullable', 'string', 'max:255'],
            'provisional_diagnosis' => ['nullable', 'string', 'max:255'],
            'admitted_at' => ['nullable', 'date'],
        ];
    }
}
