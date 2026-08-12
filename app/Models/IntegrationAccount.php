<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class IntegrationAccount extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'provider', 'name', 'credentials_encrypted', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credentials_encrypted' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected $hidden = ['credentials_encrypted'];
}
