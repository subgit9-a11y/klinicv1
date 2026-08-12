<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionItem extends Model
{
    protected $fillable = [
        'prescription_id', 'medicine', 'form', 'strength', 'dose', 'frequency',
        'duration', 'route', 'quantity', 'instructions', 'timing', 'anupana',
        'external_application',
    ];

    protected function casts(): array
    {
        return ['external_application' => 'boolean'];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}
