<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StorePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'consultation_id' => ['nullable', 'integer', 'exists:consultations,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medicine' => ['required', 'string', 'max:255'],
            'items.*.form' => ['nullable', 'string', 'max:100'],
            'items.*.strength' => ['nullable', 'string', 'max:100'],
            'items.*.dose' => ['nullable', 'string', 'max:100'],
            'items.*.frequency' => ['nullable', 'string', 'max:100'],
            'items.*.duration' => ['nullable', 'string', 'max:100'],
            'items.*.route' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['nullable', 'string', 'max:100'],
            'items.*.instructions' => ['nullable', 'string', 'max:1000'],
            'items.*.timing' => ['nullable', 'string', 'max:100'],
            'items.*.anupana' => ['nullable', 'string', 'max:100'],
            'items.*.external_application' => ['nullable', 'boolean'],
        ];
    }
}
