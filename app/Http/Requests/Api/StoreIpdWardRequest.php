<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreIpdWardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['nullable', 'in:GENERAL,PRIVATE,ICU,SEMI_PRIVATE,SPECIAL'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
