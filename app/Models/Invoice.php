<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'patient_id', 'appointment_id', 'consultation_id',
        'ipd_admission_id', 'invoice_number', 'status', 'source',
        'subtotal_cents', 'discount_cents', 'tax_cents', 'total_cents',
        'amount_paid_cents', 'amount_due_cents', 'currency', 'payment_mode',
        'notes', 'issued_at', 'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'PAID';
    }

    public function isVoided(): bool
    {
        return $this->status === 'VOID';
    }
}
