<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestigationResult extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'investigation_id', 'document_id', 'parameter', 'value',
        'unit', 'reference_range', 'flag', 'notes', 'resulted_at',
    ];

    protected function casts(): array
    {
        return ['resulted_at' => 'datetime'];
    }

    public function investigation(): BelongsTo
    {
        return $this->belongsTo(Investigation::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
