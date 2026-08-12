<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TenantSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'key', 'value', 'category'];

    public static function get(int $tenantId, string $key, mixed $default = null): mixed
    {
        $setting = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('key', $key)
            ->first();
        return $setting?->value ?? $default;
    }
}
