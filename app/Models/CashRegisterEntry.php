<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CashRegisterEntry extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'cash_register_id', 'reference_type', 'reference_id',
        'type', 'method', 'amount_cents', 'currency', 'description', 'user_id',
    ];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
