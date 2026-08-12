<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomAvailability extends Model
{
    use BelongsToTenant;

    protected $table = 'room_availability';

    protected $fillable = [
        'tenant_id', 'treatment_room_id', 'date', 'start_time', 'end_time', 'is_available',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_available' => 'boolean',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(TreatmentRoom::class, 'treatment_room_id');
    }
}
