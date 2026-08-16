<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vital extends Model
{
    use BelongsToTenant, HasFactory;

    public $timestamps = true;

    protected $fillable = [
        'tenant_id', 'patient_id', 'consultation_id', 'recorded_by',
        'systolic_bp', 'diastolic_bp', 'pulse', 'temperature',
        'respiratory_rate', 'spo2', 'height', 'weight', 'bmi',
        'pain_score', 'custom_vitals', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'custom_vitals' => 'array',
            'recorded_at' => 'datetime',
        ];
    }
}
