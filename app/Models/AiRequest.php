<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AiRequest extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'ai_feature_id', 'ai_prompt_version_id',
        'provider', 'model', 'input_summary', 'output', 'output_status',
        'status', 'input_tokens', 'output_tokens', 'estimated_cost_cents',
        'duration_ms', 'error', 'approved_at', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'estimated_cost_cents' => 'integer',
            'duration_ms' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(AiFeature::class, 'ai_feature_id');
    }

    public function promptVersion(): BelongsTo
    {
        return $this->belongsTo(AiPromptVersion::class, 'ai_prompt_version_id');
    }

    public function contextable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isApproved(): bool
    {
        return $this->output_status === 'APPROVED';
    }
}
