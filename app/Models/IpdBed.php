<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpdBed extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'ipd_room_id', 'bed_number', 'status', 'daily_rate_cents',
    ];

    protected function casts(): array
    {
        return ['daily_rate_cents' => 'integer'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(IpdRoom::class, 'ipd_room_id');
    }
}
