<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ClinicalNote extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'patient_id', 'consultation_id', 'user_id',
        'note_type', 'content', 'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
