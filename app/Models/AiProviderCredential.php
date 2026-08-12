<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProviderCredential extends Model
{
    protected $fillable = [
        'provider', 'name', 'api_key_encrypted', 'default_model', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected $hidden = ['api_key_encrypted'];
}
