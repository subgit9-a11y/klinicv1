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
- Sidebar uses `@if(Route::has($item['route']))` guards so not-yet-defined routes don't fatal the layout. All 16 nav routes now resolve to web URLs.

## Route name collisions (API vs web)
- `routes/api.php` authenticated group is prefixed with `->name('api.')` so `Route::apiResource('patients', ...)` generates `api.patients.index` (not `patients.index`), avoiding collisions with web routes (`patients.index` → `/patients` web, `api.patients.index` → `/api/v1/patients`). The webhook route keeps its explicit `api.webhooks.payments` name.
- API tests use literal `/api/v1/...` URLs via `getJson()`/`postJson()`, never `route()` names, so renaming API route names is safe.
- Patient model has NO `full_name` column — use `$patient->name` (accessor) or `$patient->fullName()`, not `$patient->full_name`.
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

## Scheduler (Document 2 §25) — COMPLETED
- `bootstrap/app.php` → `withSchedule()` wires 7 commands.
- Commands in `app/Console/Commands/`: `SendAppointmentReminders`, `SendTreatmentReminders`, `SendFollowupReminders`, `RetryNotifications`, `CheckSubscriptions`, `ReconcilePayments`, `CleanupRecords`.
- **Carbon parse gotcha**: `appointment_date` is a `date` cast (Carbon). Concatenating `"$date $time"` then `Carbon::parse()` can throw `InvalidFormatException`. Use `$date->copy()->setTimeFromTimeString($time)` instead.
- `RetryNotifications` re-dispatches PENDING/FAILED deliveries via `NotificationService::sendOnChannel()` up to `MAX_ATTEMPTS=3`.
- `ReconcilePayments` skips gracefully when gateway unconfigured (`isConfigured()` check) — important for test envs without Cashfree creds.
- `CleanupRecords` uses `config('klinic.audit_retention_days', 365)`.
- **HasFactory trait**: `Followup` model was missing `HasFactory` — `factory()` calls failed until added.
- Tests: `tests/Feature/Scheduler/SchedulerCommandsTest` — 9 tests. Suite now 376 pass / 986 assertions.

## Audit Logging (Document 2 §26) — COMPLETED
- `App\Services\Audit\AuditService::record()` writes `audit_logs` entries (action, category, before/after JSON, auditable morph, user, IP, user agent).
- Wired into: `AuthController` (login/logout), `BillingService` (payment.recorded, refund.issued), `WebhookProcessor` (payment.webhook), `ConfigurationPanel` (super_admin feature flag save/delete).
- **Migration gotcha**: `audit_logs.auditable` was `morphs()` (NOT NULL). Audit events like `auth.login` have no auditable model, so insert failed with NOT NULL constraint. Changed to `nullableMorphs('auditable')`. Note: `morphs()->nullable()` chaining breaks (morphs returns void) — use `nullableMorphs()`.
- Livewire: inject `AuditService` via `boot()` method (not constructor) for Livewire components.
- `auth()->user()` in Super Admin context may return null (guard mismatch); pass explicitly where needed.
- Tests: `tests/Feature/Audit/AuditLogTest` — 3 tests. Suite now 379 pass / 993 assertions.

## Phase 18 — Clinical Sub-Resource APIs (COMPLETED)
- Exposed previously-orphaned ConsultationService methods via API: PUT /consultations/{id} (update), POST .../complete, .../amend, .../vitals, .../diagnoses, .../notes.
- Refactored ConsultationController::store() to delegate to ConsultationService::start() (was bypassing tenant-scoped validation). Constructor-injects ConsultationService.
- Added Consultation::notes() HasMany relation (was missing — ClinicalNote had consultation_id FK but no inverse relation).
- Extended ConsultationResource with vitals/diagnoses/notes via whenLoaded() so show can return sub-resources.
- Created 3 new EMR services: FollowupService (schedule/updateStatus/forPatient), InvestigationService (order/update/forPatient), ConsentService (record/revoke/forPatient) — all tenant-scoped with requireTenant() + assertSameTenant() pattern matching ConsultationService.
- Created 3 controllers: FollowupController, InvestigationController, ConsentController (nested under patients.{resource}).
- Exposed BillingService::refund() via POST /invoices/{invoice}/payments/{payment}/refund in InvoiceController (uses refund policy method + BILLING_REFUND permission, not update).
- Policies created: FollowupPolicy, InvestigationPolicy, PatientConsentPolicy — reuse CONSULTATIONS_* permissions (no dedicated followup/investigation/consent permission constants exist).
- **Policy naming gotcha**: Laravel auto-discovers PatientConsent -> PatientConsentPolicy, NOT ConsentPolicy. Name the policy after the Model class.
- **Response wrapper consistency**: response(JsonResource::make(...)) returns at root (no data key); response(["data" => JsonResource::make(...)]) wraps explicitly. New endpoints use the explicit ["data" => ...] wrapper to match the store pattern. index() collection endpoints keep Resource::collection() (auto-wraps in data).
- Tests: tests/Feature/Api/ApiClinicalFlowsTest — 11 tests. Suite now 414 pass / 1160 assertions.

## Phase 19 — P1 Core Product APIs (COMPLETED)

### Appointment status management API
- `AppointmentController` extended with `reschedule()` (PUT .../reschedule), `changeStatus()` (POST .../status), `cancel()` (POST .../cancel). All delegate to `AppointmentService::reschedule()/changeStatus()/cancel()` which enforce the STATUS_FLOW state-machine and double-booking `lockForUpdate()` checks.
- `RescheduleAppointmentRequest` validates `appointment_date` (after today), `start_time`, `duration_minutes`.
- **Route ordering gotcha**: `GET /appointments/slots` MUST be registered BEFORE `Route::apiResource('appointments', ...)->only([..., 'show'])`, otherwise `/appointments/slots` matches the `{appointment}` show binding and 404s. Static paths before dynamic.
- **Time format gotcha**: `start_time` stored as `HH:MM` (SQLite time cast). Resource returns `14:00` not `14:00:00` — assert accordingly.
- Tests: `tests/Feature/Api/ApiAppointmentStatusTest` — 7 tests (confirm/check-in→in-consultation→complete, invalid transition 422, no-show, reschedule, past-date validation, available slots).

### Receipt workflow (PDF generation + download)
- `app/Services/Billing/ReceiptService.php` — `generate(Payment)` renders `pdf.receipt` via dompdf, stores on `local` disk under `pdfs/Y/m/`, persists a `Document` (type=RECEIPT) linking `metadata.payment_id`. `content(Payment)` returns stored bytes or generates on demand. `latestDocument()` finds prior receipt.
- `app/Http/Controllers/Api/V1/ReceiptController.php` — `generate()` (POST, 201) + `download()` (GET, application/pdf). Nested under invoices: `/invoices/{invoice}/payments/{payment}/receipt`. Authorize via `view` on the invoice; 404s if payment's invoice_id mismatches.
- **Migration**: `documents.type` enum CHECK didn't include `RECEIPT`/`INVOICE` → migration `2026_08_18_000020_add_receipt_type_to_documents` extends the enum (SQLite stores enum as VARCHAR + CHECK; `->change()` recreates the column).
- Tests: `tests/Feature/Billing/ReceiptServiceTest` — 6 tests (generate creates Document + file, content returns PDF bytes, latestDocument lookup, API generate 201, API download Content-Type, cross-invoice payment 404).

### Subscription lifecycle API (SaaS plans)
- `app/Http/Controllers/Api/V1/SubscriptionController.php` — `indexPlans` (GET /plans), `showPlan` (GET /plans/{plan}), `currentSubscription` (GET /subscription), `activate` (POST /subscription/activate), `cancel` (POST /subscriptions/{subscription}/cancel). Delegates to existing `PlanService::activate()/cancel()`.
- `PlanResource`, `SubscriptionResource`, `ActivateSubscriptionRequest` (`plan_code` exists validation).
- `app/Policies/SubscriptionPolicy.php` — **non-standard method names** (`viewAnyPlan`, `viewPlan`, `viewAnySubscription`, `viewSubscription`, `activate`, `cancel`) since it authorizes both `Plan` and `Subscription` models. Registered via `Gate::policy()` for BOTH `Plan::class` and `Subscription::class` (Laravel auto-discovery would look for `PlanPolicy`/`SubscriptionPolicy` by class name; the shared policy with custom method names needs explicit registration).
- **Auth model**: Plans are a global catalogue (any authenticated user may list/view). Subscriptions are tenant-scoped — only CLINIC_OWNER/Super Admin of the owning tenant may activate/cancel. Cross-tenant cancel → 404 (BelongsToTenant global scope hides the row, same secure pattern as treatment catalog).
- **Response wrapping**: `response(JsonResource::make(...))` returns fields at JSON root (NO `data` wrapper); `response(['data' => JsonResource::make(...)])` wraps explicitly. The subscription show/current endpoints use the explicit wrapper so `assertJsonPath('data.status')` works.
- Tests: `tests/Feature/Api/ApiSubscriptionTest` — 8 tests (list plans, activate 201, receptionist 403, current subscription, cancel, cross-tenant 404, unknown plan 422, service records ACTIVATED event).

### Suite status
- 505 tests pass / 1383 assertions (up from 484).

## P1 — Core Product Features (Code Review Fixes, COMPLETED)

Resolved the ChatGPT code review's P0 + P1 issues. Suite now 484 pass / 1331 assertions.

### P0 fixes
- **T1 Webhook route wired**: `POST /api/v1/webhooks/payments` now invokes `WebhookProcessor` (was a stub returning "Webhook received.").
- **T2 ReportService DB-agnostic**: replaced SQLite-only `julianday()` with `TIMESTAMPDIFF()`/Carbon-based calculations compatible with both SQLite and MySQL.
- **T3 ReportService patient search**: fixed `lower(first_name ||" "|| last_name)` (SQLite concat) → `LOWER(CONCAT(...))` MySQL-compatible.

### P1 features (service + API + policy + tests pattern)
- **Cash Register** (`app/Services/Billing/CashRegisterService.php`): open/close/entry/stats lifecycle; `CashRegisterController` (index/open/show/close/entries); 8 svc + 7 API tests. Float comparison gotcha: JSON decodes `50` vs `50.0` — use loose `==` in assertions.
- **Expenses** (`ExpenseController`): index/store/show; 7 API tests. Uses `BILLING_REFUND`/expense perms.
- **Teleconsultation lifecycle** (`app/Services/Telemedicine/TeleconsultationService.php`): create/start/end/cancel; 8 svc + 5 API tests. Store returns resource at JSON root (no `data.` wrapper); RECEPTIONIST can create (has `APPOINTMENTS_CREATE`).
- **Notification templates** (`app/Services/Notifications/NotificationTemplateService.php`): CRUD with global vs tenant scope guard (`assertSameScope`); `NotificationTemplateSeeder` (10 default global templates); 8 svc tests. **TenantContext gotcha**: to create a global (tenant_id=null) template in tests, call `app(TenantContext::class)->forget()` first — the `BelongsToTenant` creating hook otherwise stamps the current tenant_id over null.
- **Doctor onboarding + availability** (`app/Services/Staff/DoctorService.php`): onboard/updateProfile/setAvailability (replace schedule); migration `2026_08_18_000010_add_doctor_profile_columns_to_users` adds `specialization, registration_number, medicine_system, consultation_fee_cents, followup_fee_cents`; `UserPolicy` (STAFF_MANAGE perm); `DoctorController` + nested `/doctors/{doctor}/availability`; 9 tests.
- **Treatment catalogue** (`app/Services/Treatments/TreatmentCatalogService.php`): CRUD for TreatmentService + TreatmentRoom; `TreatmentCatalogPolicy` (TREATMENTS_MANAGE perm, registered explicitly for both models); `TreatmentCatalogController`; 7 tests.
- **IPD configuration** (`app/Services/IPD/IpdConfigurationService.php`): CRUD wards→rooms→beds with delete guards (ward-with-rooms, room-with-beds, occupied-bed protected); `IpdConfigurationPolicy` (IPD_CONFIGURE perm, registered for IpdWard/IpdRoom/IpdBed); `IpdConfigurationController`; 8 tests.

### New permissions
- `staff.manage` (CLINIC_OWNER) — `UserPolicy` for staff management.
- `treatments.manage` (CLINIC_OWNER) — treatment catalogue config.
- `ipd.configure` (CLINIC_OWNER) — IPD ward/room/bed config.
All added to `Permissions.php` constants + `groups()` + `RolePermissions` CLINIC_OWNER grant.

### Cross-tenant test pattern
Cross-tenant model access via route-model binding returns **404** (global scope filters it out before policy), not 403. The policy `view` would only fire if the model resolves. To create a tenantB-owned model in a test while context is tenantA, switch context (`$ctx->set($tenantB->id)`) before factory create, then switch back.

## Phase 19 — Production-Readiness / Bug-Sweep Fixes (COMPLETED)
Addressed the P0 critical issues from the ChatGPT production-readiness review (webhook wiring, MySQL-incompat reports, billing financial safety, public-booking security, concurrency-safe numbering).

- **Webhook wired (CRITICAL)**: `POST /api/v1/webhooks/payments` was a stub closure returning `{message: 'Webhook received.'}`. Created `app/Http/Controllers/Api/V1/WebhookController.php` that reads the `X-Cf-Signature`/`X-Cashfree-Signature` header and delegates to `WebhookProcessor::process()` (signature verify → server-side `CashfreePaymentProvider::verify()` → idempotent `BillingService::recordPayment()` → audit). Always returns 200 to stop Cashfree retries. Tests: `tests/Feature/Payments/CashfreeIntegrationTest::test_webhook_endpoint_*`.
- **ReportService MySQL compatibility**: `averageWaitTime()` and `ipdSummary()` used SQLite-only `julianday()` which fails on MySQL/MariaDB (prod). Added driver-agnostic `minutesDiff()`/`daysDiff()` helpers that pick `julianday` (SQLite) vs `TIMESTAMPDIFF` (MySQL). Also widened `averageWaitTime` date filter to `whereBetween(startOfDay, endOfDay)` (SQLite stores `date` columns as full datetime — same gotcha as appointments).
- **Payment status vocabulary mismatch (BUG)**: `ReportService::collectionsByMethod()` filtered `payments.status = 'COMPLETED'` but `BillingService::recordPayment()` writes `SUCCESS`, so collections silently returned zero. Fixed to `SUCCESS`. Updated the test fixture accordingly.
- **Invoice status vocabulary mismatch (BUG)**: `revenueSummary()` outstanding filter used `['DUE','PARTIALLY_PAID','OVERDUE']` which don't exist in the billing state machine (`DRAFT, ISSUED, PARTIALLY_PAID, PAID, REFUNDED, VOID`). Fixed to `['ISSUED','PARTIALLY_PAID']`.
- **PatientService::search() MySQL concat**: `lower(first_name || " " || last_name)` is SQLite-only. Now driver-aware: SQLite keeps `||`, MySQL uses `CONCAT(first_name, " ", COALESCE(last_name, ""))`.
- **Public booking security + UID**: `OnlineBookingController` accepted any `exists:users,id` user_id (cross-tenant escalation) and issued throwaway `K360-PUB-uniqid()` UIDs. Now: validates the doctor belongs to the booking tenant + practitioner role via `firstOrFail()`; reuses `PatientService::register()` (canonical `K360-P-##########` UID via `PatientUidService`) with phone de-dup via `findDuplicateByPhone()`.
- **Appointment concurrency hardening**: `assertNoCollision()` only locked existing overlapping appointment rows — two simultaneous bookings where neither exists yet could both pass. Added `User::whereKey($doctorId)->lockForUpdate()->first()` to deterministically serialize concurrent bookings for the same doctor (portable SQLite+MySQL; `GET_LOCK` is MySQL-only).
- **Billing financial safety**: `recordPayment()` now `lockForUpdate()`s the invoice row inside the txn and rejects overpayment (`amount > amount_due_cents`) so two concurrent payments can't over-collect. `refund()` now `lockForUpdate()`s the payment + invoice rows so concurrent refunds can't over-refund.
- **Concurrency-safe numbering**: `MAX(id)+1` patterns in `BillingService::generateInvoiceNumber/PaymentNumber/RefundNumber` and `IpdService::generateIpdNumber` (and the IPD invoice `K360-INV-IPD-<id>`) race under concurrent inserts. New `app/Support/SequentialNumber::next($table, $prefix, $numberColumn)` computes `MAX(numeric-tail)+1` under a `lockForUpdate()` on the prefix range and retries on collision (backed by the unique constraints on `invoice_number`/`payment_number`/`refund_number`/`ipd_number`). Tests: `tests/Unit/SequentialNumberTest`.
- Tests: +10 new (webhook routing ×2, overpayment ×1, sequential-number ×3, plus updated report fixtures). Suite now **424 pass / 1182 assertions**.
- **Env note**: PHP is NOT preinstalled — install via apt (php8.4-cli + mbstring/xml/curl/zip/gd/bcmath/intl/sqlite3/readline). Composer from getcomposer.org. `php artisan migrate` hangs on the `--graceful` interactive prompt in CI — use `--force`. `php artisan test` (no `--no-interaction`). Frontend must be built (`npm run build`) or Livewire/Blade tests fail with "Vite manifest not found at public/build/manifest.json".



## Task A — OCR & Speech Provider Integrations (COMPLETED)

Config-driven, gracefully-degrading OCR and speech-to-text integrations behind the existing provider contracts.

- **Providers** (all implement the existing `OCRProviderInterface` / `SpeechProviderInterface`):
  - `app/Integrations/OCR/GoogleVisionOcrProvider.php` — `name()=GOOGLE_VISION`, `isConfigured()` <= `services.google_vision.api_key`, `extract()` POSTs to the Vision API (HTTP facade, no fake when unconfigured -> returns `{success:false, message:'Google Vision OCR provider not configured'}`).
  - `app/Integrations/OCR/TesseractOcrProvider.php` — `name()=TESSERACT`, shells out to the `tesseract` binary via `Process`; `isConfigured()` <= binary resolvable (`services.tesseract.binary`, default `tesseract`).
  - `app/Integrations/Speech/OpenAiWhisperProvider.php` — `name()=OPENAI_WHISPER`, `isConfigured()` <= `services.openai.api_key`, `transcribe()` POSTs multipart to the Whisper endpoint.
  - `app/Integrations/Speech/WhisperCppProvider.php` — `name()=WHISPER_CPP`, shells out to the `whisper.cpp` binary + model path; `isConfigured()` <= binary + model both set (`services.whisper_cpp.*`).
- **Config** (`config/services.php`): added `gemini`, `ocr_provider`, `speech_provider` keys plus per-provider blocks under `services.ocr.*` / `services.speech.*` / `services.tesseract.*` / `services.whisper_cpp.*`. `OCR_PROVIDER` / `SPEECH_PROVIDER` env selects the active provider (`google_vision|tesseract`, `openai|whisper_cpp`).
- **AppServiceProvider**: binds `OCRProviderInterface` / `SpeechProviderInterface` to the config-selected concrete provider (mirrors the GeminiProvider pattern). Both `OcrService` and `SpeechService` registered as singletons.
- **Orchestration services** (tenant-scoped + audit-logged wrappers):
  - `app/Services/Documents/OcrService.php` — `extractFromDocument(Document)`: calls the provider, on success stamps `metadata.ocr_text` / `ocr_provider` / `ocr_extracted_at` onto the document; always records `document.ocr_extracted` / `document.ocr_failed` audit logs.
  - `app/Services/Documents/SpeechService.php` — `transcribeFromDocument(Document)`: same shape, stamps `metadata.transcription` / `transcription_provider` / `transcribed_at`; audits `document.speech_transcribed` / `document.speech_transcribe_failed`.
- **AuditService signature**: `record(string $action, string $category, array{before?:array, after?:array} $changes, ?Model $auditable = null)`.
- Tests: `tests/Feature/Documents/OcrAndSpeechProviderTest` — 17 tests (interface resolution, config-driven switching, not-configured degrade, orchestration success/failure + audit + metadata stamping; happy path uses in-test `FakeOcrProvider`/`FakeSpeechProvider` to avoid real HTTP). Suite: **539 pass / 1453 assertions**.
- **Graceful-degrade invariant preserved**: providers report `isConfigured()=false` and return `{success:false}` when creds/binary absent — app boots without any OCR/Speech keys, no faked success.

## Task B — AI Governance API & Approval Board (COMPLETED)

Exposed the existing AI draft->approve governance flow as a REST API + a Livewire approval board, enforcing the "AI output is draft-only until a practitioner signs off" invariant.

- **Policy**: `app/Policies/AiRequestPolicy.php` — `viewAny`/`view`/`create`/`approve`/`reject` gated by `Permissions::AI_USE` (CLINIC_OWNER + DOCTOR); Super Admin bypasses; `view`/`approve`/`reject` enforce tenant isolation (`$aiRequest->tenant_id === $user->tenant_id`). Auto-discovered by `<Model>Policy` naming — no `Gate::policy()` registration needed.
- **API** (`/api/v1/ai-requests`):
  - `GET` list (filters: `?status=DRAFT|APPROVED|REJECTED|ERROR|PENDING`, `?feature_key=`), `GET` show, `POST` generate, `POST /{id}/approve`, `POST /{id}/reject` (optional `reason`).
  - Controller `app/Http/Controllers/Api/V1/AiRequestController.php` — `generate()` resolves the morph `contextable_type` (accepts short alias `patient`/`consultation`/`ipd_admission` OR the FQN) + `contextable_id`, delegates to `AIManager::generate()` which always returns `output_status` in {PENDING, DRAFT, ERROR} — **never APPROVED**. `approve()`/`reject()` delegate to `AIManager::approve()/reject()` (audit-logged).
  - Form request `app/Http/Requests/Api/GenerateAiRequestRequest.php`, resource `app/Http/Resources/Api/AiRequestResource.php` (exposes `output_status`, `is_draft`, `is_approved`, `approved_at`, `approved_by`).
- **Routes** (`routes/api.php`): added under the authenticated `throttle:api` group; controller imported. Names auto-prefixed `api.` by the group `->name('api.')`.
- **Livewire board**: `app/Livewire/AI/AiApprovalBoard.php` + `resources/views/livewire/ai/approval-board.blade.php` — status-filtered list of AI drafts with inline Approve / Reject (reason prompt) actions; `authorize('approve'/'reject', $aiRequest)` per action. Web route `GET /ai/board` (`ai.board`), sidebar link added.
- **Cross-tenant**: `AiRequest` uses `BelongsToTenant`, so route-model binding on `/ai-requests/{aiRequest}` returns **404** for other tenants (global scope hides the row before the policy runs). In Livewire, `findOrFail()` throws `ModelNotFoundException` for cross-tenant ids — caught in tests as the blocked path.
- Tests: `tests/Feature/Api/ApiAiGovernanceTest` (10) + `tests/Feature/AI/AiApprovalBoardLivewireTest` (7). Invariants asserted: generate never returns APPROVED; receptionist (no `ai.use`) -> 403; cross-tenant view/approve -> 404; approve stamps `approved_at`/`approved_by`; reject stores `reason` in `error`. Audit `AllPagesAndFlowsAuditTest` extended with `/ai/board` page check. Suite: **539 pass / 1453 assertions**.


## P0 #2 — Public Online Booking Payment Flow (COMPLETED)

The public `/book` flow now takes and verifies payment BEFORE the appointment is confirmed (review item #2 — the last genuine P0 gap; the other Phase-1 items were already fixed in prior phases).

- **`app/Services/Bookings/OnlineBookingService.php`** — orchestrates the full flow:
  1. **Doctor validation** — `User::where('id', $user_id)->where('tenant_id', $tenant->id)->whereIn('role', [DOCTOR, PRACTITIONER, CLINIC_OWNER])->where('is_active', true)->firstOrFail()`. Rejects cross-tenant, inactive, and non-practitioner ids (404). **Note**: `users` has no `suspended_at` column — the active gate is `is_active` (the demo Tenant, not User, has `suspended_at`).
  2. **Patient** — canonical `PatientService::register()` + `findDuplicateByPhone()` (permanent `K360-P-*` UID via PatientUidService; never `K360-PUB-*`).
  3. **Appointment booked as SCHEDULED** — NOT confirmed. Confirmation requires verified payment.
  4. **Invoice** — `BillingService::createInvoice(['appointment_id' => ...])` → `addInvoiceItem(CONSULTATION, fee)` → `issue()`. Fee/duration from `config('klinic.public_booking.*')`.
  5. **Cashfree order** — `PaymentGatewayInterface::createOrder()` → persist `PaymentOrder` (payable = Invoice, keyed by `gateway_order_id` so the webhook resolves it) → return the hosted checkout URL.
  6. **Graceful degrade** — when `gateway->isConfigured() === false` (dev/test), no invoice/order/payment is created; the appointment stays SCHEDULED with no payment required.
- **`OnlineBookingController`** slimmed to delegate to `OnlineBookingService`; if `gateway_configured && payment_url`, `redirect()->away($payment_url)`; else show a status message. Removed the raw `exists:users,id` rule (the `firstOrFail()` tenant+role+active check is the real gate).
- **Post-payment confirmation** — `WebhookProcessor` now calls `OnlineBookingService::confirmOnPayment($invoice->fresh())` after `BillingService::recordPayment()`. That method promotes the appointment to CONFIRMED **only when** `invoice->isPaid()`; no-op for invoices without an appointment link. This is the ONLY place an online booking is confirmed — never on browser redirect (browser redirects are never trusted; server-side `verify()` is the source of truth).
- **Config** (`config/klinic.php` `public_booking` block): `tenant_id`, `consultation_fee_cents` (default 49900), `consultation_duration_minutes` (30), `return_url`. The `KLINIC_PUBLIC_BOOKING_TENANT_ID` env replaces the old `klinic360.public_booking_tenant_id` (kept the `klinic.public_booking.tenant_id` key).
- **Tests** (`tests/Feature/Bookings/OnlineBookingPaymentTest` — 7): gateway-unconfigured → appointment SCHEDULED, no invoice; gateway-configured → invoice ISSUED + PaymentOrder CREATED + payment_url + appointment still SCHEDULED; webhook verified → appointment CONFIRMED + invoice PAID + payment SUCCESS; webhook not-verified → appointment stays SCHEDULED; cross-tenant / inactive / non-practitioner doctor → ModelNotFoundException (404). The webhook test swaps BOTH the `PaymentGatewayInterface` binding (for `createOrder` during booking) AND the concrete `CashfreePaymentProvider` binding (for `verifyWebhookSignature` + `verify()` during webhook processing — `WebhookProcessor` constructor-injects the concrete class, not the interface).
- Suite: **546 pass / 1475 assertions** (was 539; +7).

### Review-item status summary (Phase 1 of the review's recommended order)
- #1 Webhook wiring — already done (WebhookController → WebhookProcessor).
- #2 Public booking payment flow — **this phase** (book SCHEDULED → Cashfree order → webhook/server-verify → CONFIRMED).
- #3 Patient UID — already done (PatientService::register + PatientUidService).
- #4 Doctor validation — already done (tenant + role + is_active firstOrFail); refined to `is_active` (not the non-existent `suspended_at`).
- #5 Appointment concurrency — already done (User::whereKey lockForUpdate + overlap lockForUpdate).
- #6 Invoice locking — already done (Invoice::lockForUpdate()->find).
- #7 Overpayment — already done (amount > currentDue throws).
- #8 Payment status vocab — already done (SUCCESS; ReportService uses SUCCESS).
- #9 Invoice status vocab — already done (DRAFT, ISSUED, PARTIALLY_PAID, PAID, REFUNDED, VOID).
- #10 Cash register — already done (CashRegisterService).
- #11 Expense — already done (ExpenseService).
- P2 MySQL #1/#2, number generation — already done (driver-aware CONCAT/TIMESTAMPDIFF, SequentialNumber).

## PB2 — Events / Listeners / Jobs for async notifications (COMPLETED)

Before this phase the app had **no domain events** — `NotificationService::sendOnChannel()` ran synchronously in the request, blocking on WhatsApp/SMS/email/AI provider calls. Now provider calls happen off the request path.

- **Events** (`app/Events/`): `AppointmentBooked`, `AppointmentConfirmed`, `PaymentRecorded` — each carries the model + a `variables()` map (`patient_name`, `doctor_name`, `appointment_date`, `start_time`, `amount`, `currency`, `invoice_number`, `payment_number`, …).
- **Dispatch sites** (all post-transaction, so listeners only fire on commit):
  - `AppointmentService::book()` → `AppointmentBooked::dispatch(...)` after the DB transaction commits.
  - `AppointmentService::changeStatus()` → `AppointmentConfirmed::dispatch(...)` ONLY on a real `SCHEDULED→CONFIRMED` transition (guarded by `$status === 'CONFIRMED' && $current !== 'CONFIRMED'` so a no-op re-confirm doesn't spam a notification).
  - `BillingService::recordPayment()` → `PaymentRecorded::dispatch(...)` after the payment is recorded as `SUCCESS` (failed/partial-that-throws paths never dispatch).
- **Listeners** (`app/Listeners/`): `SendAppointmentBookedNotification`, `SendAppointmentConfirmedNotification`, `SendPaymentReceiptNotification`. Each resolves the patient via the appointment/invoice, calls `NotificationService::sendOnChannel($patient, $eventKey, 'in_app', $vars)` to create a `NotificationDelivery` row, and — when the delivery is still `PENDING`/`FAILED` (i.e. an external channel needs a provider call) — dispatches `SendNotificationJob`.
- **Job** (`app/Jobs/SendNotificationJob.php`): `ShouldQueue`, constructs with `deliveryId, eventKey, channel, variables`; `handle()` re-resolves `TenantContext` from the delivery's notifiable, then calls `NotificationService::sendOnChannel()`. Public props so `Queue::assertPushed(fn ($job) => $job->deliveryId === ...)` works in tests.
- **Wiring**: `AppServiceProvider::boot()` registers three `Event::listen(...)` mappings (explicit, not `#[AsEventListener]`, so the registration is grep-able). Added `use Illuminate\Support\Facades\Event`.
- **Templates** (`NotificationTemplateSeeder`): the existing `appointment.confirmation` (sms/whatsapp/email) and `payment.received` (sms) keys are reused — added `in_app` rows for both (`appointment.confirmation` + `payment.received`) so the in-app channel has a template to render. `DatabaseSeeder` already calls `NotificationTemplateSeeder`.
- **TenantContext gotcha**: `TenantContext` is a singleton bound to the container — its methods (`isSet()`, `id()`) are INSTANCE methods, NOT static. In listeners use `app(TenantContext::class)->isSet()`, never `TenantContext::isSet()`.
- **Test queue driver**: `phpunit.xml` sets `QUEUE_CONNECTION=sync`, so dispatched jobs run inline during tests. `Event::fake([OnlySpecificEvents])` still lets the real event's listener run (only the faked ones are suppressed). To assert the in-app path: don't fake queues — assert the `NotificationDelivery` reaches `SENT`. To assert job dispatch for a FAILED external delivery, `Queue::fake()` + create a `FAILED` delivery + `SendNotificationJob::dispatch()` + `Queue::assertPushed(...)`.
- **ShouldRenderJsonWhen**: see PB3 below — the public slots endpoint is a web route but must return JSON. `bootstrap/app.php` `shouldRenderJsonWhen` now returns true when `$request->is('api/*') || $request->expectsJson()`.
- Tests: `tests/Feature/Notifications/AsyncNotificationEventsTest` — 8 tests. Suite now 554 pass / 1489 assertions.

## PB3 — Public booking real-slots API endpoint (COMPLETED)

The public `/book` form previously used a free-text `<input type="time">` with no link to actual availability. Now it fetches real bookable slots.

- **Endpoint**: `GET /book/slots` (`OnlineBookingController::slots`, route `online-booking.slots`) — unauthenticated. Validates `user_id` (required int) + `date` (required date, `after_or_equal:today`). Resolves the public-booking tenant via `resolveTenant()`, sets `TenantContext`, then `User::where('id', ...)->where('tenant_id', $tenant->id)->whereIn('role', [...])->where('is_active', true)->firstOrFail()`. Returns `{data: [{start, end, available}]}` from `AppointmentService::availableSlots()`.
- **Security**: the doctor MUST belong to the resolved public tenant and be an active practitioner — otherwise `firstOrFail()` → 404. No cross-tenant slot enumeration. Inactive/non-practitioner roles → 404 (not a leaky 200 with empty data).
- **Reuses the real engine**: `AppointmentService::availableSlots()` already handles `DoctorAvailability` (day-of-week windows) + booked-appointment overlap (15-min slot grid; a slot is `available=false` if any non-cancelled appointment overlaps `[start, end)`). The SQLite date-storage gotcha (Phase 7) is handled by `whereBetween('appointment_date', [dayStart, dayEnd])` inside the service.
- **Slots vs appointments overlap**: a booking `09:15–09:45` marks BOTH `09:15–09:30` and `09:30–09:45` unavailable (the overlap check is `b.start_time < slotEnd && b.end_time > slotStart`). `09:00–09:15` stays available (back-to-back, no overlap).
- **shouldRenderJsonWhen fix**: `bootstrap/app.php` exception handler now renders JSON when `$request->is('api/*') || $request->expectsJson()`. Without this, a `ValidationException` on the web `/book/slots` route returned a 302 HTML redirect even for `getJson()` calls (the old rule only matched `api/*` paths). This is the correct general behaviour — any client sending `Accept: application/json` gets JSON errors.
- **Blade** (`resources/views/online-booking/show.blade.php`): the time input is now read-only; doctor (`#booking-doctor`) + date (`#booking-date`) changes trigger a `fetch('/book/slots?...')` that renders clickable slot chips (available = teal border, booked = greyed/strikethrough + disabled). Selecting a chip fills `#booking-time`. Vanilla JS (no Alpine) — the public form is a plain Blade page outside the app shell.
- `show()` also filters `doctors` to `is_active = true` (inactive practitioners are hidden from public booking).
- Tests: `tests/Feature/Bookings/OnlineBookingSlotsTest` — 9 tests (bookable slots, overlap marks slots unavailable, no working hours → empty, cross-tenant doctor 404, inactive doctor 404, non-practitioner 404, required-field validation 422, past-date 422, show view renders slot picker). Suite now 563 pass / 1518 assertions.

## Payment idempotency & status vocab hardening (review #2/#10/#11/#30, COMPLETED)

A second-pass review against a refreshed ZIP flagged four financial-correctness items; on verification against the live `feature/database-design` branch, most of the reviewer's 30 items were already addressed by prior phases (webhook wiring, paid booking flow, UID via PatientService, doctor validation, slot locking, payment locking, MySQL compat, Events/Listeners/Jobs, OCR providers, appointment lifecycle, Google Meet/teleconsultation, SequentialNumber). The genuinely-still-open items were fixed here:

- **#2 Webhook idempotency (real bug)** — `WebhookProcessor` keyed idempotency on `event_id = order_id` alone, so a single Cashfree order carrying multiple payment events (a failed attempt, then a retried success) would have the FIRST event mark the order "done" and the success silently skipped. Fixed: the idempotency key is now a composite `(gateway_order_id :: gateway_payment_id :: event_type)`. A failed event (`ORD::PAY1::PAYMENT_FAILED`) and a success event (`ORD::PAY2::PAYMENT_SUCCESS_WEBHOOK`) on the same order get distinct keys → both processed. Duplicate delivery of the SAME event keeps the same key → skipped. Regression test: `test_webhook_processor_processes_failed_then_success_on_same_order`.
- **recordPayment gateway idempotency** — `BillingService::recordPayment()` now dedupes on `(gateway, gateway_payment_id)` BEFORE the insert (and again inside the `lockForUpdate()` transaction to cover the lost race), returning the existing SUCCESS payment instead of creating a duplicate. The hard backstop is a new `payments(gateway, gateway_payment_id)` UNIQUE constraint (migration `2026_08_18_000030_harden_payment_idempotency_and_status_vocab`); NULL `gateway_payment_id` (manual payments) is exempt on both SQLite and MySQL. Tests: `test_record_payment_is_idempotent_on_gateway_payment_id`, `test_record_payment_allows_distinct_gateway_payments`.
- **#11 Payment status vocab** — the `payments.status` column was declared `default 'COMPLETED'` with a comment listing `PENDING/COMPLETED/FAILED/REFUNDED`, but `BillingService` writes `SUCCESS` and `ReportService` sums on `SUCCESS`. Standardized the column default + comment on `SUCCESS` and the canonical set `PENDING/SUCCESS/FAILED/REFUNDED/PARTIALLY_REFUNDED` (both in the base migration and an ALTER migration for existing installs). No code path relied on the old default (recordPayment always sets status explicitly).
- **#30 Patient UID race** — `PatientUidService::generate()` (random + existence check) is an optimization, not an arbiter: two concurrent registrations can both observe the same free UID and the second insert hits the `patients.k360_uid` unique constraint. `PatientService::register()` now wraps `Patient::save()` in a try/catch that, on a unique-constraint violation (SQLSTATE 23000 / "UNIQUE constraint" / "k360_uid"), regenerates the UID and retries (up to 5 attempts). The unique constraint is the arbiter; the existence check is only the fast path. Regression test: `test_register_retries_on_uid_collision` (stubs the uid service to collide first, then succeed).

### Remaining review items verified as ALREADY DONE (stale in the reviewer's ZIP)
The reviewer's ZIP predates PB1/PB2/PB3/A1-A4/B1. On live verification: #1 webhook wired (WebhookController→WebhookProcessor, no closure stub); #3 online booking paid (OnlineBookingService: createOrder/PaymentOrder/Invoice/confirmOnPayment); #4 UID via PatientService (no `K360-PUB-uniqid`); #5 doctor validation (tenant+role+is_active firstOrFail); #6 PatientService not bypassed; #7 slot locking (lockForUpdate + DB::transaction + User::whereKey lockForUpdate); #8/#9 CashRegisterService/ExpenseService/ReceiptService exist; #10 Invoice::lockForUpdate()+overpay check; #12 invoice status DRAFT/ISSUED/PARTIALLY_PAID/PAID/REFUNDED/VOID; #13 driver-aware CONCAT/TIMESTAMPDIFF; #14 Events/Listeners/Jobs/Integrations (PB2); #15 GoogleVisionOcrProvider + TesseractOcrProvider; #19 appointment STATUS_FLOW + reschedule/cancel/changeStatus; #20/#21 GoogleMeetProvider in TeleconsultationService (schedule/start/end/cancel/markNoShow + tryCreateMeeting); #30 SequentialNumber for invoices/payments/refunds/ipd.

### Larger product gaps NOT in this pass (properly-scoped future phases, not "fixes")
These are substantial feature builds, not targeted fixes, so they are tracked here as future phases rather than crammed into a fix pass:
- #16 Super Admin completeness — ConfigurationPanel has Plans/Features/AI Models tabs; missing: Tenants CRUD UI, Users management UI, Integrations config UI, Templates management UI, Monitoring/audit/webhook/notification dashboards.
- #17 Clinic + Super Admin onboarding wizard (create clinic → owner → plan → subscription → settings → staff → services → rooms → beds).
- #18 Doctor availability breaks/leave/holiday/max-appointments/consultation-duration — **slot engine + schema DONE** (see #18 section above); only the admin UI remains.
- #22 Treatment catalog admin UI (backend `TreatmentCatalogService` exists, no Livewire UI).
- #23 IPD configuration admin UI (wards/rooms/beds management).
- #25 Follow-up workflow UI (upcoming/today/completed/missed/rescheduled views; `FollowupService` exists).
- #26 Investigation/lab end-to-end UI (order → upload result → OCR → doctor review → timeline → AI summary; `InvestigationService` + `OcrService` exist).
- #27 Documents lifecycle UI (upload/categorize/version/OCR/AI-extract/share/access-audit/archive/retention-delete; `DocumentService` exists).
- #28 Discrete AI products (AI Scribe, Patient/Lab/Document/Treatment/IPD Summary, Follow-up/Clinical Assistant) — `AIContextBuilder` has summarizePatient/Consultation/IpdAdmission but no dedicated feature endpoints.
- #29 Database-backed RBAC (roles/permissions/role_permissions/user_roles tables for Super Admin to modify permissions without a deploy).

Tests: +4 (1 webhook, 2 recordPayment idempotency, 1 PatientService UID collision). Suite now 567 pass / 1531 assertions.
Docs: added `docs/INSTALLATION.md` and `docs/ADMIN_GUIDE.md`.

## #18 — Doctor availability: breaks / leave / duration / capacity (slot engine, COMPLETED)

Review item #18 flagged that doctor availability was day-of-week + start/end + is_active only — no breaks, leave, holidays, per-doctor consultation duration, or daily capacity cap. The slot engine (`AppointmentService::availableSlots`, consumed by the public `/book/slots` endpoint) would therefore offer slots during a doctor's lunch and during approved leave. Fixed at the engine/schema level (no UI yet — that remains a future phase):

- **Migration `2026_08_18_000040`** — adds nullable `break_start_time`/`break_end_time` to `doctor_availability`, a new `doctor_leaves` table (dated inclusive `start_date`/`end_date`, `type` LEAVE/HOLIDAY/EMERGENCY/OTHER, `is_approved`, tenant-scoped), and nullable `consultation_duration_minutes`/`max_daily_appointments` on `users`. All nullable → existing rows/flows unaffected.
- **`DoctorAvailability`** model: fillable + the two break columns. **`DoctorLeave`** model: `onDate()` scope using `whereDate()` (SQLite stores `date` columns as `'Y-m-d 00:00:00'` text — a plain string `<=` comparison fails, same gotcha as `appointment_date`; `whereDate` is correct on both SQLite and MySQL).
- **`User`**: `leaves()` relation, `consultationDurationMinutes()` (falls back to `klinic.public_booking.consultation_duration_minutes` config, default 30), integer casts for the two new columns.
- **`DoctorService::setAvailability`** now accepts optional `break_start_time`/`break_end_time` (validated `after:break_start_time`); new `setLeave($doctor, [start_date,end_date,reason?,type?,is_approved?])` method.
- **`AppointmentService::availableSlots`** now: (1) returns `[]` when an approved `DoctorLeave` covers the date; (2) skips any slot overlapping `[break_start, break_end)` and jumps the cursor past the break (not one-slot-at-a-time); (3) uses the doctor's `consultation_duration_minutes` as the slot interval when set (callers passing an explicit `slotMinutes` for the board still override); (4) marks every slot unavailable once `max_daily_appointments` is met (cap = non-null and booked count ≥ cap).
- Tests: +9 (6 in `OnlineBookingSlotsTest`: break-skip, approved-leave empties, unapproved-leave keeps slots, per-doctor duration, daily-capacity; 4 in `DoctorServiceTest`: break persistence, break-end-before-start rejection, setLeave create, setLeave end-before-start). Suite now 576 pass / 1559 assertions.
- **Still a future phase (UI only)**: a Livewire/Blade admin screen to set breaks/leave/duration/capacity — the backend service + API + slot engine are all in place.

## Review #3 hardening pass (COMPLETED)

The review's "top 10" targeted fixes. Suite now **612 pass / 1666 assertions**.

- **MySQL concat in Livewire (review #10)** — `app/Support/Sql.php::personNameConcat($first, $last)` is driver-aware (`||` on SQLite, `CONCAT` on MySQL). Used in `Livewire/Patients/PatientList`, `Livewire/Appointments/AppointmentBoard`, `PatientService::search`. Tests: `tests/Unit/SqlHelperTest`.
- **Webhook status codes (#3)** — `WebhookProcessor::process()` returns `['status' => 200|400|401|404|422, 'processed', 'event_id', 'message']`; `WebhookController` returns that status. Bad signature → 401, malformed → 400, unknown order → 404 (NOT marked processed, so a Cashfree retry after our booking commits can settle), amount/verify failure → 422, processed/idempotent → 200.
- **Amount consistency (#5)** — `app/Services/Payments/PaymentSettlementService::settle($order, $verifyResult, $eventType)` is the shared settle path (webhook + reconciliation): rejects when `verify.amount_cents !== order.amount_cents` (audits `payment.amount_mismatch`, no payment recorded), settles via `BillingService::recordPayment`, then transitions the `PaymentOrder`.
- **PaymentOrder lifecycle (#6)** — constants `STATUS_CREATED/PENDING/PAID/FAILED/EXPIRED/CANCELLED`; transitions `markPending/markPaid/markFailed/expire/cancel` with an explicit allowed-transition map (PAID terminal; `LogicException` otherwise). `ReconcilePayments` settles verified open orders via PaymentSettlementService and `expire()`s stale (>1h, `klinic.payments.order_ttl_hours`) unverified ones.
- **Fail-closed online booking (#4)** — `OnlineBookingService::paymentUnavailable()` = gateway unconfigured && `klinic.public_booking.fail_closed` (default `app()->environment('production')`). When unavailable: `book()` throws RuntimeException BEFORE creating anything; controller `store()`/`slots()` → 503, `show()` renders an amber notice. Dev/test stays open (fail-open).
- **Token sequence counter (#9)** — new `token_sequences` table + `app/Models/TokenSequence::lockFor($tenantId,$userId,$date)->consume()`: dedicated per-doctor per-day counter row, created on first use (unique-violation retry on the create race), row-locked before consuming; initializes from `MAX(CAST(token_number AS SIGNED))` of existing tokens (deployment continuity). **⚠️ whereDate gotcha applies to the new table too**: `token_date` must be queried with `whereDate` (SQLite stores `Y-m-d H:i:s` text). `AppointmentService::issueToken` uses it. Tests: `tests/Unit/TokenSequenceTest`.
- **Sequential-number DB constraints (#11)** — verified UNIQUE on invoices.invoice_number, payments.payment_number, refunds.refund_number, ipd_admissions.ipd_number already exist; regression test in `SequentialNumberTest`.
- **Cash register rules (#8)** — `CashRegisterService::close($register, ?int $actualBalanceCents)` computes expected from ledger and records `actual_balance_cents` + `variance_cents = actual − expected` (new nullable columns, migration `2026_08_19_000020`); rejects negative counts; double-close guarded under row lock. New `postAdjustment($register, CREDIT|DEBIT, $amount, $reason, $by)` for drawer corrections (OPEN-only; checks `$register->fresh()` against stale instances). API close accepts `actual_balance_cents`; `CashRegisterResource` exposes `actual_balance`/`variance`.
- **Gateway refunds (#7)** — `BillingService::refund()` calls `PaymentGatewayInterface::refund()` for payments with `gateway`+`gateway_payment_id` INSIDE the payment row lock (never double-refund at the gateway). Fail closed: unconfigured gateway or refund failure → DomainException, NO local refund. Manual payments stay local-only. Passing `gateway_refund_id` explicitly skips the gateway call (webhook/ops escape hatch). Tests: 3 new in CashRegisterServiceTest.
- **Adversarial tenant matrix (#15)** — `tests/Feature/Tenancy/TenantAdversarialTest`: tenant-A CLINIC_OWNER vs tenant-B patients/appointments/invoices/treatments/ipd-admissions/ai-requests — reads, mutations, listings, nested sub-resources all blocked/empty. **Learnings**: cross-tenant PUT patient → 403 (policy denies; binding resolves cross-tenant because SubstituteBindings runs before `auth.api` sets TenantContext — don't assume 404 from binding); nested index endpoints (prescriptions/followups/investigations) ignore the `{patient}` route param and return the attacker's own (empty) tenant-scoped list — 200 is safe, assert EMPTY `data`, not 404. API tests use `TokenService::create()` headers, NOT `Sanctum::actingAs`. Asserting tenant-B rows after a request needs `withoutGlobalScopes()` (TenantContext persists on the singleton).
- **Async OCR (#12)** — `App\Events\DocumentUploaded` (dispatched from `DocumentService::upload()` after commit) → `App\Listeners\DispatchDocumentOcr` (dispatches job ONLY when `OcrService::isConfigured()`) → `App\Jobs\ProcessDocumentOcr` (ShouldQueue, tries=3): looks up Document via `withoutGlobalScopes()` (workers have no request tenant context), re-sets TenantContext from the document, calls `OcrService::extractFromDocument()`, ALWAYS `forget()`s context in `finally` (no tenant leakage between jobs in a long-running worker). **Test-support gotcha**: shared fakes live in `tests/Support/Fakes/` (PSR-4 autoloadable) — inline classes in another test file are NOT autoloadable when running a single test file standalone.

## Review #4 — 5 technical fixes for Cashfree correctness (COMPLETED)

Suite now **618 pass / 1681 assertions**.

- **Raw-body + timestamp signature (review #1)** — `CashfreePaymentProvider::verifyWebhookSignature(string $rawBody, string $signature, ?string $timestamp = null)` computes `base64(hmac_sha256(timestamp + rawBody, secretKey))`. NEVER re-encode parsed JSON (`JSON_UNESCAPED_SLASHES` roundtrip rejects legit webhooks). `WebhookController` passes `$request->getContent()` + `x-webhook-timestamp` header (signature headers tried: `x-webhook-signature` → `X-Cf-Signature` → `X-Cashfree-Signature` → `x-cf-signature`). `WebhookProcessor::process($payload, $signature, ?$rawBody = null, ?$timestamp = null)` falls back to re-encoded JSON only when rawBody omitted (direct test calls). `generateSignature(string $rawBody, ?string $timestamp = null)` is the test helper. Tests in `CashfreeIntegrationTest`: roundtrip, timestamp-binds, tamper, and a re-encoding rejector.
- **verify() selects SUCCESS attempt (#2)** — `CashfreePaymentProvider::verify()` iterates the payments array for `payment_status === 'SUCCESS'` (first attempt may be FAILED while a retry succeeded); settlement uses THAT attempt's `cf_payment_id` + amount. Tests: success-among-multiple, unverified-when-no-success (Http::fake).
- **No fabricated checkout URL (#3)** — `createOrder()` returns `checkout_url` extracted from `payment_checkout_url` / `payment_links.web` / `checkout_url` (null if absent, documented on `PaymentGatewayInterface`). `OnlineBookingService` maps `$order['checkout_url'] ?? null` → `payment_url`; when null the booking stays payment-pending (no redirect). Old fabricated `'https://payments.cashfree.com/order/'.$orderId` removed. Test fake helpers in `OnlineBookingPaymentTest`: `swapGatewayWithFake` now returns `checkout_url`, new `swapGatewayWithoutCheckoutUrl` asserts null URL → pending.
- **Counter table for SequentialNumber (#4)** — new `sequence_counters` table (scope string UNIQUE, next_value bigint) + `App\Models\SequenceCounter::lockFor($scope, callable $initializer)/consume()` (global by design, NOT BelongsToTenant; unique-index race-safe creation). `SequentialNumber::next($table, $prefix, $numberColumn)` scope = `"{$table}.{$numberColumn}:{$prefix}"`; initializes from existing numeric-tail MAX (PHP-computed, portable); existence-check loop retained as backstop. Same row-lock mechanism as `TokenSequence`. Regression test: `test_next_is_backed_by_a_dedicated_counter_row`.
- **PatientUid fallback on counter (#5)** — `PatientUidService::generateSequential()` uses `SequenceCounter` scope `patients.k360_uid:K360-P` instead of `max('id')+1`.

- **Post-review tightening**: `WebhookProcessor::process()` now REQUIRES `string $rawBody` (non-nullable) — no accidental re-encoded-JSON verification path remains. Tests pass `(string) json_encode($payload)` explicitly.

**Remaining in review's ordered list (future phases)**: Super Admin control plane, tenant onboarding UI, Billing/Cash-Register UI, Doctor availability UI, Treatment/IPD UI, Documents OCR UI, Teleconsultation UI, AI Scribe, DB-backed RBAC, MySQL 8 integration testing, queue/scheduler ops docs, pentest/adversarial pass.
