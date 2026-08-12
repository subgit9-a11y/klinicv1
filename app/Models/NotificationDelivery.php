<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationDelivery extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'notification_template_id', 'notification_id',
        'notifiable_type', 'notifiable_id', 'channel', 'event_id', 'recipient',
        'status', 'provider_reference', 'error', 'attempts',
        'sent_at', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
