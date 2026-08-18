<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreIpdRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ipd_ward_id' => ['required', 'exists:ipd_wards,id'],
            'room_number' => ['required', 'string', 'max:40'],
            'type' => ['nullable', 'in:GENERAL,PRIVATE,ICU,SEMI_PRIVATE,SPECIAL'],
            'status' => ['nullable', 'in:AVAILABLE,OCCUPIED,MAINTENANCE,OUT_OF_SERVICE'],
        ];
    }
}
