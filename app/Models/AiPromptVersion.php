<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPromptVersion extends Model
{
    protected $fillable = [
        'ai_prompt_id', 'version', 'system_prompt', 'user_prompt_template',
        'expected_output_schema', 'default_model', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'expected_output_schema' => 'array',
        ];
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(AiPrompt::class, 'ai_prompt_id');
    }
}
