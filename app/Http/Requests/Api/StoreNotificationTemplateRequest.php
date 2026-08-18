<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_key' => ['sometimes', 'required', 'string', 'max:80'],
            'channel' => ['sometimes', 'required', 'in:in_app,whatsapp,sms,email'],
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['sometimes', 'required', 'string'],
            'whatsapp_template_name' => ['nullable', 'string', 'max:120'],
            'sms_template_id' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'is_global' => ['nullable', 'boolean'],
        ];
    }
}
