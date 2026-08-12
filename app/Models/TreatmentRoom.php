<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TreatmentRoom extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'room_number', 'type', 'capacity',
        'supported_treatment_types', 'status',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'supported_treatment_types' => 'array',
        ];
    }
}
