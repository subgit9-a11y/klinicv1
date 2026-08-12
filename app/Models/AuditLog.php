<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'action', 'category',
        'auditable_type', 'auditable_id', 'before', 'after',
        'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
