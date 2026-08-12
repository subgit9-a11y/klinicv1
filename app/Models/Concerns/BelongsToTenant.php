<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Models that are owned by a tenant. Applies a global scope so queries are
 * automatically restricted to the resolved tenant, and stamps tenant_id on
 * creation.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = app(TenantContext::class)->id();
            if ($tenantId !== null) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantId);
            }
        });

        static::creating(function ($model): void {
            if ($model->tenant_id === null) {
                $model->tenant_id = app(TenantContext::class)->id();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
