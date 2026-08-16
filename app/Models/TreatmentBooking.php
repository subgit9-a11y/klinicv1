<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreatmentBooking extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'patient_id', 'treatment_service_id', 'therapist_id',
        'treatment_room_id', 'treatment_plan_id', 'treatment_package_id',
        'invoice_id', 'booking_date', 'start_time', 'end_time', 'status',
        'payment_mode', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(TreatmentService::class, 'treatment_service_id');
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(Therapist::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(TreatmentRoom::class, 'treatment_room_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class, 'treatment_plan_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TreatmentSession::class, 'treatment_booking_id');
    }
}
