<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

/**
 * Holds the currently resolved tenant id for the request/job lifecycle.
 * Populated by the tenant-resolution middleware (Phase 4).
 */
class TenantContext
{
    private ?int $tenantId = null;

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function isSet(): bool
    {
        return $this->tenantId !== null;
    }

    public function forget(): void
    {
        $this->tenantId = null;
    }
}
