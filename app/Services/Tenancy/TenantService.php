<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Models\User;

class TenantService
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * The tenant id active for the current request/job, or null when the actor
     * is a Super Admin operating globally.
     */
    public function id(): ?int
    {
        return $this->tenantContext->id();
    }

    public function isSet(): bool
    {
        return $this->tenantContext->isSet();
    }

    /**
     * Whether the actor is a Super Admin with cross-tenant visibility.
     * True when a user is authenticated and has no tenant_id.
     */
    public function isGlobal(User $user): bool
    {
        return $user->tenant_id === null;
    }

    public function resolveForUser(User $user): ?Tenant
    {
        return $user->tenant_id !== null ? $user->tenant : null;
    }

    public function current(): ?Tenant
    {
        $id = $this->tenantContext->id();

        return $id !== null ? Tenant::find($id) : null;
    }
}
