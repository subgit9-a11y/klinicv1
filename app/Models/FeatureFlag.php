<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class FeatureFlag extends Model
{
    use HasFactory;

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
        $cacheKey = "klinic:feature:{$key}:".($tenantId ?? 'global');

        return Cache::remember(
            $cacheKey,
            now()->addSeconds((int) config('klinic.cache_ttl.feature_flags', 60)),
            function () use ($key, $tenantId) {
                $flag = static::where('key', $key)
                    ->where(fn ($q) => $tenantId
                        ? $q->where('tenant_id', $tenantId)
                        : $q->whereNull('tenant_id'))
                    ->first();

                return $flag?->enabled ?? $flag?->default_enabled ?? false;
            },
        );
    }

    /**
     * Invalidate cached feature-flag values for a key. Call on create,
     * update, or delete so lookups re-query the database.
     */
    public static function flushCache(string $key): void
    {
        Cache::flush(); // feature flags are infrequent; simple flush is fine for the array/database cache store.
    }
}
