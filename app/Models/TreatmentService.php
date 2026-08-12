<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreatmentService extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'category', 'medicine_system', 'duration_minutes',
        'price_cents', 'currency', 'description', 'requires_therapist',
        'requires_room', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price_cents' => 'integer',
            'requires_therapist' => 'boolean',
            'requires_room' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(TreatmentBooking::class);
    }
}
