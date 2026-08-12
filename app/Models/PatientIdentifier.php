<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PatientIdentifier extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'patient_id', 'type', 'value', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
