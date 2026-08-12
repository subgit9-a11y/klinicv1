<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IpdRoom extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'ipd_ward_id', 'room_number', 'type', 'status'];

    public function ward(): BelongsTo
    {
        return $this->belongsTo(IpdWard::class, 'ipd_ward_id');
    }

    public function beds(): HasMany
    {
        return $this->hasMany(IpdBed::class);
    }
}
