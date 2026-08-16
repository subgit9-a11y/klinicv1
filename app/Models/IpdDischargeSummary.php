<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpdDischargeSummary extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'ipd_admission_id', 'user_id', 'discharged_at',
        'admission_diagnosis', 'discharge_diagnosis', 'treatment_given',
        'investigations', 'advice_on_discharge', 'follow_up_instructions',
        'follow_up_days', 'status',
    ];

    protected function casts(): array
    {
        return [
            'discharged_at' => 'datetime',
            'follow_up_days' => 'integer',
        ];
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(IpdAdmission::class, 'ipd_admission_id');
    }
}
