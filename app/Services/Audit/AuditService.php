<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * Record an audit entry.
     *
     * @param  string  $action  Short action key (e.g. 'patient.viewed', 'invoice.created').
     * @param  string  $category  Logical group (auth, patient, clinical, billing, etc.).
     * @param  array{before?: array, after?: array}  $changes
     */
    public function record(
        string $action,
        string $category,
        array $changes = [],
        ?Model $auditable = null,
        ?User $user = null,
        ?Request $request = null,
    ): AuditLog {
        return AuditLog::create([
            'tenant_id' => $this->tenantContext->id(),
            'user_id' => $user?->id ?? auth()->id(),
            'action' => $action,
            'category' => $category,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->id,
            'before' => $changes['before'] ?? null,
            'after' => $changes['after'] ?? null,
            'ip_address' => $request?->ip() ?? request()->ip(),
            'user_agent' => $request?->userAgent() ?? request()->userAgent(),
        ]);
    }
}
