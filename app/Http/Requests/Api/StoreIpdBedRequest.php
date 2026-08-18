<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreIpdBedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ipd_room_id' => ['required', 'exists:ipd_rooms,id'],
            'bed_number' => ['required', 'string', 'max:40'],
            'daily_rate_cents' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'in:AVAILABLE,OCCUPIED,MAINTENANCE,OUT_OF_SERVICE'],
        ];
    }
}
