<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TreatmentPackage extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'category', 'description', 'price_cents',
        'currency', 'total_sessions', 'validity_days', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'total_sessions' => 'integer',
            'validity_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
