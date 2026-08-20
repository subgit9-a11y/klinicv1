<?php

namespace App\Models;

use App\Services\Auth\PermissionService;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'tenant_id', 'name', 'email', 'phone', 'password', 'role',
    'permissions', 'two_factor_secret', 'two_factor_confirmed_at',
            'two_factor_recovery_codes', 'is_active', 'avatar', 'designation',
        'specialization', 'registration_number', 'medicine_system',
        'consultation_fee_cents', 'followup_fee_cents',
        'consultation_duration_minutes', 'max_daily_appointments',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'permissions'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        // Flush the cached permission set whenever a user is saved so that
        // role changes and per-user override updates take effect immediately.
        static::saved(function (User $user) {
            app(PermissionService::class)->flush($user);
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'encrypted:array',
            'is_active' => 'boolean',
            'consultation_duration_minutes' => 'integer',
            'max_daily_appointments' => 'integer',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function availability()
    {
        return $this->hasMany(DoctorAvailability::class, 'user_id');
    }

    public function leaves()
    {
        return $this->hasMany(DoctorLeave::class, 'user_id');
    }

    public function appointmentsAsDoctor()
    {
        return $this->hasMany(Appointment::class, 'user_id');
    }

    public function appointmentsCreatedBy()
    {
        return $this->hasMany(Appointment::class, 'created_by');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'SUPER_ADMIN';
    }

    public function isClinicOwner(): bool
    {
        return $this->role === 'CLINIC_OWNER';
    }

    public function hasRole(string|array $roles): bool
    {
        return in_array($this->role, (array) $roles, true);
    }

    /**
     * Additional DB roles (user_roles) stacked on top of users.role —
     * resolved by RbacService when the DB is synced.
     */
    public function rbacRoles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Rbac\Role::class, 'user_roles');
    }

    public function hasPermission(string $permission): bool
    {
        return app(PermissionService::class)->can($this, $permission);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        return app(PermissionService::class)->canAny($this, $permissions);
    }

    public function hasAllPermissions(array $permissions): bool
    {
        return app(PermissionService::class)->canAll($this, $permissions);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_secret)
            && ! is_null($this->two_factor_confirmed_at);
    }

    /** Scope to users with a given role. */
    public function scopeRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    public function consultationFee(): int
    {
        return $this->consultation_fee_cents ?? 0;
    }

    /**
     * This doctor's preferred slot length, falling back to the system
     * default from config when unset.
     */
    public function consultationDurationMinutes(): int
    {
        return $this->consultation_duration_minutes
            ?? (int) config('klinic.public_booking.consultation_duration_minutes', 30);
    }

    public function fullName(): string
    {
        return $this->name;
    }
}
