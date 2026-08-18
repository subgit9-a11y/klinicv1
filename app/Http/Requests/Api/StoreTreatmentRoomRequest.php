<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreTreatmentRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_number' => ['required', 'string', 'max:40'],
            'type' => ['nullable', 'string', 'max:40'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'supported_treatment_types' => ['nullable', 'string'],
            'status' => ['nullable', 'in:AVAILABLE,OCCUPIED,MAINTENANCE,OUT_OF_SERVICE'],
        ];
    }
}
