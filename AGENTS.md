# Klinic 360 — Repository Memory

## Project Overview
Klinic 360 is an AI-powered multi-tenant B2B SaaS clinic operating system for Ayurveda, Siddha & Homeopathy practices.
Built as a modular Laravel monolith. Two plans: Solo Doctor ₹999/mo, Small Clinic ₹1,999/mo.

## Tech Stack
- PHP 8.4 / Laravel 13 (>=12 required by spec)
- Livewire 3 + Alpine.js + Tailwind CSS 4 + Vite 8
- Database: SQLite for dev/test (MySQL/MariaDB-compatible for prod)
- Auth: Laravel sessions + Sanctum (API)
- Queue: database driver

## Build Commands
- `npm run build` — compile frontend assets (Vite)
- `npm run dev` — Vite dev server with HMR
- `php artisan migrate --graceful` — run migrations (SQLite by default)
- `php artisan serve` — dev server on :8000
- `php artisan test` — run Pest test suite
- `composer test` — same

## Architecture (Document 2 §3-4)
- `app/Services/{AI,Appointments,Billing,Documents,IPD,Notifications,Patients,Payments,Reports,Subscriptions,Telemedicine,Tenancy,Treatments}` — business logic
- `app/Contracts/` — provider interfaces (AI, Payment, Subscription, Video, WhatsApp, SMS, Email, Storage, OCR, Speech)
- `app/Integrations/` — concrete provider implementations
- `app/Policies/` — authorization
- `app/Livewire/` — Livewire components
- Business logic in Services, integrations behind interfaces, validation in Form Requests, authorization in Policies.

## Multi-Tenancy (Document 2 §5)
- `tenants` table; every tenant-owned table has `tenant_id`
- Isolation via middleware + global scopes + scoped queries + route/model binding
- NEVER trust client-supplied `tenant_id`

## RBAC Roles (Document 2 §6)
SUPER_ADMIN, CLINIC_OWNER, DOCTOR, RECEPTIONIST, THERAPIST, NURSE, IPD_STAFF, ASSISTANT

## Key Constraints
- Transactions + locks + idempotency for double-booking/duplicate-payment protection
- Immutable financial/audit records (void/refund/correction/versioning, never delete)
- AI clinical output is draft-only — requires practitioner approval before becoming official
- NEVER fake integrations or expose secrets
- Provider integrations degrade gracefully when not configured (app must boot without keys)

## Plan Location
Full 26-phase plan: `.agents_tmp/PLAN.md`
Specs: `/workspace/vertopal.com_Klinic360_Document_*.json`

## Brand Palette
Brand teal: primary `#1f9482` (brand-500), dark `#167669` (brand-600). See `resources/css/app.css`.

## V1 Boundary
No pharmacy/medicine ordering/delivery. No commission on treatment revenue. Cashfree only for online consultation payments + SaaS subscription. Clinic-side payments: CASH/UPI/CARD/BANK_TRANSFER/CHEQUE/OTHER.

## Phase 3 — Authentication (COMPLETED)
- Livewire components in `app/Livewire/Auth/` (Login, ForgotPassword, ResetPassword, TwoFactorChallenge, VerifyEmail) + `app/Livewire/Profile/Profile.php`.
- `app/Services/Auth/TwoFactorService.php` — Google2FA + Bacon QR. **Must strip the `<?xml ...?>` prolog** from inline SVG (done) so it embeds cleanly in Blade + Livewire morphdom (the prolog blanks the page on re-render).
- Middleware: `app/Http/Middleware/EnsureAccountIsActive.php`, `RequireTwoFactorChallenge.php`. Registered in `bootstrap/app.php`.
- All Livewire component Blade views MUST have a single root `<div>` (MultipleRoot Elements exception otherwise).
- In Livewire components use `session()->regenerate()`, NOT `request()->session()->regenerate()`.
- Sidebar uses `@if(Route::has($item['route']))` guards so not-yet-defined routes don't fatal the layout.
- AdminUserSeeder seeds Super Admin (superadmin@klinic360.test). `DatabaseSeeder` runs PlanSeeder + SystemSettingsSeeder + AdminUserSeeder.
- Tests: `tests/Feature/Auth/AuthenticationTest.php` — 17 tests. Total suite 19 pass / 53 assertions. Run with `php artisan test`.

### Environment gotcha
The PHP runtime is NOT preinstalled in this container. If `php` is missing (command not found), install via apt:
`sudo apt-get install -y php8.4-cli php8.4-mbstring php8.4-xml php8.4-curl php8.4-mysql php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl php8.4-sqlite3 php8.4-readline`. Composer also missing — install from getcomposer.org to `/usr/local/bin/composer`. Dev DB is SQLite (file), APP_KEY already set in `.env`.

## Phase 6 — Patient UID & Patient 360 (COMPLETED)
- `app/Services/Patients/PatientUidService.php` — collision-safe 10-digit UID `K360-P-0000012487` (select-for-update increment + retry loop, zero-padded, `isValid()` guard).
- `app/Services/Patients/PatientService.php` — `register()/findDuplicateByPhone()/search()/findByUid()/update()`. Tenant-scoped (resolves from `TenantContext`), force-stamps `tenant_id`, generates UID via service, rejects manual UID/tenant tamper on update, supports related `identifiers` + `consents` on register. Throws if no tenant context.
- `app/Policies/PatientPolicy.php` — view/viewAny/create/update/delete/export; cross-tenant access denied by `tenant_id` mismatch.
- `app/Livewire/Patients/` — `PatientList` (search + inline register form, pagination), `Patient360` (15-tab patient chart), `PatientEdit`. Auto-resolved by Livewire FQN convention.
- `resources/views/livewire/patients/` — `patient-list`, `patient-360`, `patient-edit` Blade views. `resources/views/components/patient/header.blade.php` — Patient 360 header (avatar, UID, Edit button, tab strip).
- Routes in `routes/web.php` (order matters: `/patients/{patient}/edit` before `/patients/{patient}` to avoid "edit" matching `{patient}`).
- `PatientFactory` now uses `PatientUidService::generate()` (no more faker `unique()` collision risk) + `configure()` afterMaking for unique phone.
- `x-ui.page-header` component extended with an `actions` slot for header action buttons.
- Tests: `tests/Feature/Patients/` — `PatientServiceTest` (14), `PatientPolicyTest` (8), `PatientLivewireTest` (8). Suite now 72 pass / 289 assertions.
- **Livewire gotcha**: nullable DB columns assigned to `string`-typed public properties throw "Cannot assign null". Use `?string` for optional fields in Livewire component mount/fill.
- **Browser-tool gotcha**: after a Livewire morph (e.g. toggling a form via wire:click), `browser_get_state` may return empty `interactive_elements`. Prefer Livewire component tests (`Livewire::test(...)`) over browser clicks for verifying AJAX form flows.

## Phase 5 — RBAC (COMPLETED)
- `app/Services/Auth/Permissions.php` — granular permission catalog grouped by module (patients, appointments, consultations, prescriptions, treatments, ipd, billing, ai, documents). Dotted keys like `patients.view`.
- `app/Services/Auth/RolePermissions.php` — default grant per role (SUPER_ADMIN bypassed; CLINIC_OWNER=all; DOCTOR/RECEPTIONIST/THERAPIST/NURSE/IPD_STAFF/ASSISTANT scoped).
- `app/Services/Auth/PermissionService.php` — `can()/canAny()/canAll()/forUser()`. Resolution: SUPER_ADMIN→all; else role defaults ∪ user `permissions` grants − revokes. User.permissions stored as `{grants:[], revokes:[]}`.
- Gate::before in AppServiceProvider routes dotted abilities to PermissionService (returns explicit bool = grant/deny; non-dotted → null → defer to Policies).
- `app/Http/Middleware/RequireRole.php` — `role:SUPER_ADMIN` / `role:A,B` route guard.
- User gains `hasPermission()/hasAnyPermission()/hasAllPermissions()` helpers (use in Blade `@if(auth()->user()->hasPermission('patients.create'))` or `@can('patients.create')`).
- Tests: `tests/Feature/Rbac/RbacTest.php` — 12 tests. Suite now 42 pass / 218 assertions.

## Phase 4 — Multi-Tenancy (COMPLETED)
- `app/Http/Middleware/SetTenantContext.php` — resolves tenant from `Auth::user()->tenant_id` into `TenantContext`. Registered as `tenant` alias in `bootstrap/app.php`, applied to the authenticated route group.
- `app/Services/Tenancy/TenantService.php` — `id()/isSet()/current()/isGlobal()/resolveForUser()`.
- Super Admin (`tenant_id = null`) → null context → no global scope → cross-tenant visibility. Tenant users → forced scope + force-stamped `tenant_id`.
- `BelongsToTenant` trait **force-stamps** `tenant_id` from context on create (overrides client input) so mass-assignment can never inject another tenant.
- `PatientFactory` reads `tenant_id` from `TenantContext`; `UserFactory` gains `forTenant()`/`superAdmin()` helpers.
- Tests: `tests/Feature/Tenancy/TenantIsolationTest.php` — 11 tests. Suite now 30 pass / 76 assertions.

## Phase 2 — Database Design (COMPLETED)
- 11 migration files creating ~75 tables, all migrate cleanly on SQLite.
- Migration naming: `2025_01_02_0000XX_create_<domain>_tables.php` grouped by domain.
- 68 Eloquent models in `app/Models/` with fillable, casts, relationships, tenant scopes.
- `app/Models/Concerns/BelongsToTenant` trait: global scope + auto-stamps `tenant_id` on create.
- `app/Services/Tenancy/TenantContext` singleton (registered in `AppServiceProvider`).
- Models WITHOUT the auto-scope (intentional): `Tenant`, `User`, `Plan`, `AuditLog`, `FeatureFlag`, `SystemSetting`, AI governance models, `IntegrationAccount`, `CustomField` — these are cross-tenant/global or Super Admin scoped.
- Factories: `UserFactory`, `TenantFactory`, `PatientFactory`, `PlanFactory`, `AppointmentFactory`.
- Seeders: `PlanSeeder` (Solo Doctor ₹999 / Small Clinic ₹1999, each with 12 features), `SystemSettingsSeeder` (10 base settings).

### ⚠️ Critical: Laravel belongsTo foreign key inference
Laravel derives the `belongsTo` foreign key from the **method name** (snake_cased + `_id`), NOT the related model name. So a method `bed()` → looks for column `bed_id`, even if the related model is `IpdBed` (which has column `ipd_bed_id`). **Always pass the foreign key explicitly** when the method name doesn't match the column:
```php
// CORRECT — column is ipd_bed_id
public function bed(): BelongsTo { return $this->belongsTo(IpdBed::class, 'ipd_bed_id'); }
// WRONG — would query where ipd_beds.id = {bed_id} (null)
public function bed(): BelongsTo { return $this->belongsTo(IpdBed::class); }
```
The same applies to `doctor()` → `user_id`, `service()` → `treatment_service_id`, `admission()` → `ipd_admission_id`, etc. All such cases are fixed in the codebase.
