<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDoctorProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'specialization' => ['nullable', 'string', 'max:120'],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'medicine_system' => ['nullable', 'in:AYURVEDA,SIDDHA,HOMEOPATHY,GENERAL'],
            'consultation_fee_cents' => ['nullable', 'integer', 'min:0'],
            'followup_fee_cents' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'string', 'min:8'],
        ];
    }
}
