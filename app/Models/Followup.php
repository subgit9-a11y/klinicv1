<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Followup extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'patient_id', 'consultation_id', 'appointment_id',
        'due_date', 'status', 'instructions',
    ];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
