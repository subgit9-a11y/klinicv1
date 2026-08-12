<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Therapist extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'name', 'qualification', 'specialty',
        'services', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'services' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
