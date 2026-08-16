<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TreatmentPlan extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'patient_id', 'consultation_id', 'name', 'description',
        'total_sessions', 'completed_sessions', 'status', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'total_sessions' => 'integer',
            'completed_sessions' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
