<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_key' => $this->event_key,
            'channel' => $this->channel,
            'name' => $this->name,
            'subject' => $this->subject,
            'body' => $this->body,
            'whatsapp_template_name' => $this->whatsapp_template_name,
            'sms_template_id' => $this->sms_template_id,
            'is_active' => (bool) $this->is_active,
            'is_global' => $this->tenant_id === null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
