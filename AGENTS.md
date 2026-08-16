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

## Phase 8 — Queue Board (COMPLETED)

- `app/Services/Queue/QueueService.php` — queue lifecycle engine: `forDoctor()/forDate()/callNext()/startConsultation()/complete()/skip()/recall()/stats()/current()`. Each mutating operation runs inside a DB transaction with `lockForUpdate()` on the token row to prevent two receptionists calling the same patient. Token lifecycle: WAITING → CALLED → IN_PROGRESS → DONE (or SKIPPED via skip, SKIPPED → WAITING via recall). Operations sync the linked appointment status (callNext→CHECKED_IN, start→IN_CONSULTATION, complete→COMPLETED, skip→NO_SHOW, recall→SCHEDULED). Per-doctor per-day token sequences are independent (two doctors each get their own 001/002/...).
- `app/Livewire/Queue/QueueBoard.php` — board grouped by doctor with date filter, stats (total/waiting/done/skipped), and action buttons (Call Next, Start, Done, Skip, Recall). `callNext(int $doctorId)` takes the doctor ID as an argument (each doctor card has its own Call Next button) rather than relying on a separate selector.
- `resources/views/livewire/queue/queue-board.blade.php` — per-doctor cards with token list, status badges, and contextual action buttons (only valid actions shown per status).
- Relaxed appointment status flow: `SCHEDULED → CHECKED_IN` is now valid (a patient can walk in and be checked in directly without explicit confirmation), required for the queue's `callNext` to work on freshly-booked walk-ins.
- Tests: `QueueServiceTest` (16), `QueueBoardLivewireTest` (7). Suite now 128 pass / 423 assertions.
- Browser-verified full queue lifecycle: Call Next (001→CALLED) → Start (→IN_PROGRESS) → Done (→DONE), with 002/003 remaining WAITING.

## Phase 7 — Appointments & Scheduling (COMPLETED)
- `app/Services/Appointments/AppointmentService.php` — unified engine: `book()/reschedule()/changeStatus()/cancel()/availableSlots()/todaysAppointments()/forPatient()`. Double-booking prevented via `lockForUpdate()` existence check on overlapping appointments for the same doctor/date (overlap = `start_time < end && end_time > start`). Status state-machine enforces valid transitions (SCHEDULED→CONFIRMED→CHECKED_IN→IN_CONSULTATION→COMPLETED; CANCELLED/NO_SHOW are terminal). WALK_IN bookings auto-issue a sequential per-doctor per-day queue token (`AppointmentToken`, `001`→`002`…). Status changes sync the token status (CHECKED_IN→CALLED, IN_CONSULTATION→IN_PROGRESS, COMPLETED→DONE, NO_SHOW→SKIPPED). Cancelled/NO_SHOW appointments free the slot for re-booking.
- `app/Policies/AppointmentPolicy.php` — view/viewAny/create/update/cancel/manageQueue; cross-tenant denial on `tenant_id` mismatch; doctors always own their appointments; otherwise gated by `appointments.*` permission.
- `app/Livewire/Appointments/AppointmentBoard.php` — date-filtered board + search + inline booking form (patient autocomplete, doctor select, type, date, time, duration, reason) + cancel action. Patient search returns live matches.
- `resources/views/livewire/appointments/appointment-board.blade.php` — table with status badges, token chips, patient link to Patient 360.
- `User` model gained `availability()`, `appointmentsAsDoctor()`, `appointmentsCreatedBy()` relations.
- `AppointmentFactory` now sets `tenant_id` from context + `end_time` computed; `forToday()`/`walkIn()` states. `DoctorAvailabilityFactory` added.
- Models with **singular uncountable table names** required explicit `protected $table`: `AppointmentStatusHistory`→`appointment_status_history`, `DoctorAvailability`→`doctor_availability`, `RoomAvailability`→`room_availability`, `TherapistAvailability`→`therapist_availability`. Eloquent's pluralizer wrongly produces `*_histories`/`*_availabilities`.
- Tests: `AppointmentServiceTest` (18), `AppointmentPolicyTest` (6), `AppointmentLivewireTest` (9). Suite now 105 pass / 367 assertions.
- **⚠️ Critical SQLite date-storage gotcha**: `date` columns (e.g. `appointment_date`) are stored by SQLite as `'2026-08-13 00:00:00'` (full datetime text), but a query `->where('appointment_date', '2026-08-13')` compares strings and **fails to match**. ALWAYS query date columns with `->whereBetween('col', [$dayStart, $dayEnd])` using `Carbon::startOfDay()/endOfDay()`, or `->whereDate('col', $date)`. The same affects `appointment_date` filters in the board render and the collision check. This will bite every future phase that filters by date (IPD admissions, billing invoices, schedules). On MySQL/MariaDB (prod) the `date` type strips time so the bug is SQLite-only, but `whereBetween` is correct on both.

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

## Phase 17 — API Layer (COMPLETED)
- REST `/api/v1` with Form Requests, API Resources, Sanctum token auth, tenant context, policies, rate limiting, validation (Document 2 §22).
- Auth: `AuthController` login/logout via `TokenService` (custom `api_tokens` table, hashed tokens). `AuthenticateApiToken` middleware (`auth.api` alias) sets `auth()->setUser()` + `TenantContext` from token's `tenant_id`. Login route `throttle:5,1`; authenticated routes `throttle:60,1`.
- Endpoints: auth (login/logout), patients (CRUD), appointments (index/store/show/cancel), consultations (index/store/show), treatments (index/store/show/complete/cancel), ipd-admissions (index/store/show/discharge), invoices (index/store/show/issue/recordPayment), prescriptions (nested patients.prescriptions, shallow show), teleconsultations (index/show), payment webhooks (signature-verified, not token-authed).
- **Policy registration gotcha**: Laravel auto-discovers policies by `<Model>Policy` name. Models whose policies are named differently must be registered explicitly via `Gate::policy(Model::class, Policy::class)` in `AppServiceProvider::boot()`. This bit `TreatmentBooking`→`TreatmentPolicy` and `IpdAdmission`→`IpdPolicy` (caused 403 on create/discharge until registered).
- **Resource response wrapping**: `response(JsonResource::make($model))` returns the resource's `resolve()` output at the JSON top level (NO `data` wrapper). So show-endpoint tests assert `assertJsonPath('id', ...)` not `assertJsonPath('data.id', ...)`. Collection responses (`Resource::collection(...)`) DO wrap in `data`.
- **IpdPolicy** uses non-standard method names `admit()`/`discharge()` (not `create()`/`update()`) matching `IpdService::admit()/discharge()`.
- **IpdService::discharge()** expects summary keys: `discharge_diagnosis`, `treatment_given`, `advice_on_discharge`, `follow_up_instructions`, `follow_up_days` (NOT `discharge_notes`/`final_diagnosis`).
- DOCTOR lacks `ipd.admit`/`ipd.discharge` perms — use `CLINIC_OWNER` for IPD API tests. RECEPTIONIST lacks `prescriptions.create` — good for forbidden tests.
- **Rate-limit test gotcha**: `throttle:60,1` allows 60 attempts; the 61st request returns 200, the 62nd returns 429. Loop must do 61 successful requests before asserting 429. Login with wrong credentials throws `ValidationException` (422), not 401.
- Tests: `ApiAuthAndPatientsTest`, `ApiResourcesTest` (appointments/consultations/invoices), `ApiTreatmentBookingTest`, `ApiIpdTest`, `ApiPrescriptionsTest`, `ApiTeleconsultationsTest`, `ApiRateLimitTest`. Suite now 367 pass / 970 assertions.
