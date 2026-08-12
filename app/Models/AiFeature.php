<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiFeature extends Model
{
    protected $fillable = [
        'key', 'name', 'description', 'category',
        'default_prompt_key', 'default_model', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function requests()
    {
        return $this->hasMany(AiRequest::class);
    }
}
