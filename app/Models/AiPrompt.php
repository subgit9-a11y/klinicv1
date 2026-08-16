<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiPrompt extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'name', 'system', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AiPromptVersion::class);
    }

    public function activeVersion()
    {
        return $this->versions()->orderByDesc('version')->first();
    }
}
