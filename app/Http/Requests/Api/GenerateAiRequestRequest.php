<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a request to generate a new AI draft.
 *
 * The contextable (patient/consultation/etc.) is resolved server-side from
 * the morph type+id and must exist; the AI feature key must match an active
 * AiFeature. Variables are free-form key/value pairs the prompt template
 * substitutes.
 */
class GenerateAiRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'feature_key' => ['required', 'string'],
            'contextable_type' => ['required', 'string'],
            'contextable_id' => ['required', 'integer'],
            'system' => ['nullable', 'string', Rule::in(['AYURVEDA', 'SIDDHAT', 'UNANI', 'HOMEOPATHY', 'YOGA', 'NATUROPATHY'])],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['nullable', 'string'],
        ];
    }
}
