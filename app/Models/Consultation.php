<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consultation extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'patient_id', 'appointment_id', 'user_id', 'medicine_system',
        'consultation_type', 'chief_complaint', 'history', 'examination',
        'assessment', 'diagnosis_summary', 'treatment_plan', 'advice',
        'follow_up_instructions', 'follow_up_days', 'status', 'system_specific',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'system_specific' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function vitals(): HasMany
    {
        return $this->hasMany(Vital::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ClinicalNote::class);
    }
}
