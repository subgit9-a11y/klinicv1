<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiModel extends Model
{
    protected $fillable = [
        'provider', 'model_id', 'display_name', 'context_window',
        'supports_vision', 'supports_structured',
        'input_cost_per_million_cents', 'output_cost_per_million_cents', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'supports_vision' => 'boolean',
            'supports_structured' => 'boolean',
            'input_cost_per_million_cents' => 'integer',
            'output_cost_per_million_cents' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
