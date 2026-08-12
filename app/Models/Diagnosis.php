<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Diagnosis extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'patient_id', 'consultation_id', 'code', 'name',
        'system', 'type', 'notes',
    ];
}
