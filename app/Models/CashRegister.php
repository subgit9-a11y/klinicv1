<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegister extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'user_id', 'status',
        'opening_balance_cents', 'closing_balance_cents',
        'opened_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance_cents' => 'integer',
            'closing_balance_cents' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CashRegisterEntry::class);
    }
}
