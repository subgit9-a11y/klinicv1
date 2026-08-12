<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IpdAdmission extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'patient_id', 'ipd_bed_id', 'admitting_doctor_id',
        'ipd_number', 'admission_type', 'admission_reason',
        'provisional_diagnosis', 'admitted_at', 'discharged_at', 'status', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'admitted_at' => 'datetime',
            'discharged_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(IpdBed::class, 'ipd_bed_id');
    }

    public function dailyNotes(): HasMany
    {
        return $this->hasMany(IpdDailyNote::class);
    }

    public function dischargeSummary()
    {
        return $this->hasOne(IpdDischargeSummary::class);
    }
}
