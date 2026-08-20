<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Plans\PlanService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Super Admin clinic control plane: create, edit, suspend, activate and
 * archive tenants. All mutations are audit-logged. Tenant suspension is
 * enforced at the auth layer (EnsureAccountIsActive / AuthenticateApiToken).
 */
class TenantAdminService
{
    public function __construct(
        private readonly PlanService $plans,
        private readonly AuditService $audit,
    ) {}

    /**
     * Create a new clinic tenant, optionally with a CLINIC_OWNER user and an
     * active subscription on the chosen plan.
     *
     * @param  array{name: string, plan_code?: string, system?: string, email?: ?string, phone?: ?string, country_code?: string, currency?: string, timezone?: string, owner_name?: ?string, owner_email?: ?string, owner_password?: ?string}  $data
     */
    public function createClinic(array $data): Tenant
    {
        return DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'plan_code' => $data['plan_code'] ?? 'SOLO_DOCTOR',
                'system' => $data['system'] ?? 'AYURVEDA',
                'status' => 'TRIAL',
                'country_code' => $data['country_code'] ?? 'IN',
                'currency' => $data['currency'] ?? 'INR',
                'timezone' => $data['timezone'] ?? 'Asia/Kolkata',
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'trial_ends_at' => now()->addDays(14),
            ]);

            if (! empty($data['owner_email']) && ! empty($data['owner_password'])) {
                User::create([
                    'tenant_id' => $tenant->id,
                    'name' => $data['owner_name'] ?? $data['owner_email'],
                    'email' => $data['owner_email'],
                    'password' => bcrypt($data['owner_password']),
                    'role' => 'CLINIC_OWNER',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);
            }

            // Trial subscription on the chosen plan (same lifecycle as the
            // standard onboarding path — PlanService::activate creates the
            // ACTIVE subscription row and cancels any predecessor).
            $this->plans->activate($tenant->id, $tenant->plan_code);

            $this->audit->record('tenant.created', 'TENANCY', ['after' => [
                'tenant_id' => $tenant->id,
                'name' => $tenant->name,
                'plan_code' => $tenant->plan_code,
            ]], $tenant);

            return $tenant;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        $before = $tenant->only(['name', 'plan_code', 'system', 'email', 'phone']);

        $tenant->update(collect($data)->only([
            'name', 'plan_code', 'system', 'email', 'phone', 'country_code', 'currency', 'timezone',
        ])->toArray());

        $this->audit->record('tenant.updated', 'TENANCY', [
            'before' => $before,
            'after' => $tenant->fresh()->only(['name', 'plan_code', 'system', 'email', 'phone']),
        ], $tenant);

        return $tenant->fresh();
    }

    public function suspend(Tenant $tenant, ?string $reason = null): Tenant
    {
        $tenant->update(['status' => 'SUSPENDED', 'suspended_at' => now()]);

        $this->audit->record('tenant.suspended', 'TENANCY', ['after' => [
            'tenant_id' => $tenant->id,
            'reason' => $reason,
        ]], $tenant);

        return $tenant->fresh();
    }

    public function activate(Tenant $tenant): Tenant
    {
        $tenant->update(['status' => 'ACTIVE', 'suspended_at' => null]);

        $this->audit->record('tenant.activated', 'TENANCY', ['after' => [
            'tenant_id' => $tenant->id,
        ]], $tenant);

        return $tenant->fresh();
    }

    /**
     * Archive (soft-delete) a clinic. Its users can no longer authenticate
     * (the tenant no longer resolves) and it disappears from default lists.
     */
    public function archive(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant) {
            $tenant->update(['status' => 'ARCHIVED', 'suspended_at' => now()]);
            $tenant->delete();
        });

        $this->audit->record('tenant.archived', 'TENANCY', ['after' => [
            'tenant_id' => $tenant->id,
        ]], $tenant);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'clinic';
        $slug = $base;
        $i = 1;

        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
