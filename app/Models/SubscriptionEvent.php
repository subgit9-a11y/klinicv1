<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionEvent extends Model
{
    protected $fillable = [
        'subscription_id', 'event_type', 'amount_cents', 'currency',
        'gateway_event_id', 'payload',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array', 'amount_cents' => 'integer'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
