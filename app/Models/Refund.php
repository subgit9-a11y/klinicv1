<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'payment_id', 'invoice_id', 'refund_number',
        'gateway_refund_id', 'amount_cents', 'currency', 'status', 'reason',
        'processed_by', 'refunded_at',
    ];

    protected function casts(): array
    {
        return ['refunded_at' => 'datetime'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
