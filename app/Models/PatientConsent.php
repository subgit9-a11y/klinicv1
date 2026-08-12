<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PatientConsent extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'patient_id', 'consent_type', 'granted', 'description',
        'document_path', 'captured_by', 'consented_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'consented_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
