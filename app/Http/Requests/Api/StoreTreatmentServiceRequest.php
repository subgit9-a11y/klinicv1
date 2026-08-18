<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreTreatmentServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:80'],
            'medicine_system' => ['nullable', 'in:AYURVEDA,SIDDHA,HOMEOPATHY,GENERAL'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'price_cents' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3'],
            'description' => ['nullable', 'string'],
            'requires_therapist' => ['nullable', 'boolean'],
            'requires_room' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
