<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    protected $fillable = [
        'key', 'description', 'is_global', 'default_enabled',
        'tenant_id', 'enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_global' => 'boolean',
            'default_enabled' => 'boolean',
            'enabled' => 'boolean',
        ];
    }

    public static function enabled(string $key, ?int $tenantId = null): bool
    {
        $flag = static::where('key', $key)
            ->where(fn ($q) => $tenantId
                ? $q->where('tenant_id', $tenantId)
                : $q->whereNull('tenant_id'))
            ->first();
        return $flag?->enabled ?? $flag?->default_enabled ?? false;
    }
}
