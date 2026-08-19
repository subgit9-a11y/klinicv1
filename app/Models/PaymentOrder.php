<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentOrder extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_CREATED = 'CREATED';
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PAID = 'PAID';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_CANCELLED = 'CANCELLED';

    /**
     * Allowed status transitions. PAID is terminal (refunds are tracked on
     * payments/refunds, not on the order). FAILED is not terminal — a
     * customer retry at the gateway can still succeed. EXPIRED/CANCELLED
     * are terminal.
     */
    private const TRANSITIONS = [
        self::STATUS_CREATED => [self::STATUS_PENDING, self::STATUS_PAID, self::STATUS_FAILED, self::STATUS_EXPIRED, self::STATUS_CANCELLED],
        self::STATUS_PENDING => [self::STATUS_PAID, self::STATUS_FAILED, self::STATUS_EXPIRED, self::STATUS_CANCELLED],
        self::STATUS_FAILED => [self::STATUS_PENDING, self::STATUS_PAID, self::STATUS_CANCELLED],
        self::STATUS_PAID => [],
        self::STATUS_EXPIRED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'tenant_id', 'internal_order_id', 'gateway', 'gateway_order_id',
        'payable_type', 'payable_id', 'amount_cents', 'currency',
        'customer_email', 'customer_phone', 'status', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function transitionTo(string $status): static
    {
        $allowed = self::TRANSITIONS[$this->status] ?? [];

        if ($this->status !== $status && ! in_array($status, $allowed, true)) {
            throw new \LogicException("Invalid PaymentOrder transition {$this->status} → {$status}");
        }

        $this->update(['status' => $status]);

        return $this;
    }

    public function markPaid(): static
    {
        return $this->transitionTo(self::STATUS_PAID);
    }

    public function markFailed(): static
    {
        return $this->transitionTo(self::STATUS_FAILED);
    }

    public function markPending(): static
    {
        return $this->transitionTo(self::STATUS_PENDING);
    }

    public function expire(): static
    {
        return $this->transitionTo(self::STATUS_EXPIRED);
    }

    public function cancel(): static
    {
        return $this->transitionTo(self::STATUS_CANCELLED);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_CREATED, self::STATUS_PENDING], true);
    }
}
