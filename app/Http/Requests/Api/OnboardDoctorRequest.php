<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class OnboardDoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['nullable', 'in:DOCTOR,THERAPIST,NURSE,RECEPTIONIST,IPD_STAFF,ASSISTANT'],
            'specialization' => ['nullable', 'string', 'max:120'],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'medicine_system' => ['nullable', 'in:AYURVEDA,SIDDHA,HOMEOPATHY,GENERAL'],
            'consultation_fee_cents' => ['nullable', 'integer', 'min:0'],
            'followup_fee_cents' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
