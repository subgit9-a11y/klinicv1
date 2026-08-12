<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentSession extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'treatment_booking_id', 'therapist_id', 'treatment_room_id',
        'session_date', 'start_time', 'end_time', 'status', 'notes', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(TreatmentBooking::class, 'treatment_booking_id');
    }
}
