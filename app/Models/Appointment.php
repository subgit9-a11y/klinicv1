<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'patient_id', 'user_id', 'created_by', 'type', 'status',
        'appointment_date', 'start_time', 'end_time', 'duration_minutes',
        'reason', 'notes', 'meeting_id', 'meeting_url', 'cancellation_reason',
        'checked_in_at', 'completed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'checked_in_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * MySQL TIME columns read as 'HH:MM:SS', SQLite as 'HH:MM'. Normalize to
     * HH:MM everywhere so string comparisons (slot overlap checks) and API
     * responses behave identically on both drivers.
     */
    public function getStartTimeAttribute($value): ?string
    {
        return $value !== null ? substr((string) $value, 0, 5) : null;
    }

    public function getEndTimeAttribute($value): ?string
    {
        return $value !== null ? substr((string) $value, 0, 5) : null;
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function token()
    {
        return $this->hasOne(AppointmentToken::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(AppointmentStatusHistory::class);
    }

    public function consultation()
    {
        return $this->hasOne(Consultation::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }
}
