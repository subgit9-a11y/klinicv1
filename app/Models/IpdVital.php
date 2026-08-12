<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpdVital extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'ipd_admission_id', 'recorded_by',
        'systolic_bp', 'diastolic_bp', 'pulse', 'temperature',
        'respiratory_rate', 'spo2', 'custom_vitals', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'custom_vitals' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(IpdAdmission::class, 'ipd_admission_id');
    }
}
