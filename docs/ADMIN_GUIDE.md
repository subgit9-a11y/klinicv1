## Production operations quick reference

- **Health probes**: `GET /health` (liveness) and `GET /health/ready` (DB + queue tables).
- **Backups**: `klinic:backup-database` (mysqldump → `storage/app/backups/`, 30-day retention) runs daily at 03:00; off-host copy is your responsibility. Test a restore before going live.
- **Security headers**: applied globally by `SecurityHeaders` middleware (X-Frame-Options DENY, CSP, nosniff, referrer policy, permissions policy).
- **Pre-launch checklist**: `docs/PRODUCTION_CHECKLIST.md`; **Architecture decisions (auth/DB/queue/AI/payments)**: `docs/ARCHITECTURE_DECISIONS.md`.

---
# Admin Guide

Operations and administration guide for Klinic 360 — intended for Super Admins and clinic owners. For installation, see [INSTALLATION.md](INSTALLATION.md). For environment variables, see [ENVIRONMENT.md](ENVIRONMENT.md).

## 1. Roles and access model

Klinic 360 uses a role-based access control (RBAC) model. The roles are:

| Role | Scope | Purpose |
|---|---|---|
| `SUPER_ADMIN` | Global (no tenant) | Platform operator. Bypasses all permission checks. Manages tenants, plans, features, AI models. |
| `CLINIC_OWNER` | One tenant | Full access within their tenant. Manages staff, settings, billing. |
| `DOCTOR` | One tenant | Sees own appointments, consultations, prescriptions, patients. |
| `RECEPTIONIST` | One tenant | Patient registration, appointment booking, queue, cash collection. |
| `THERAPIST` | One tenant | Treatment sessions and therapist availability. |
| `NURSE` | One tenant | Vitals, nursing notes, IPD care. |
| `IPD_STAFF` | One tenant | IPD admissions, bed management, discharge. |
| `ASSISTANT` | One tenant | Limited helper access. |

Permissions are dotted abilities (`patients.view`, `appointments.create`, `billing.refund`, …) resolved by `App\Services\Auth\PermissionService`. `SUPER_ADMIN` is bypassed entirely; `CLINIC_OWNER` receives all permissions; other roles get a scoped default grant. Per-user grants/revokes can override the role defaults via the `users.permissions` JSON column (`{grants:[], revokes:[]}`).

Check a permission in Blade: `@can('patients.create')` or `@if(auth()->user()->hasPermission('patients.create'))`.

## 2. Super Admin configuration panel

URL: `/super-admin/configuration` (route `super-admin.configuration`). Requires `SUPER_ADMIN` role (enforced by the `RequireRole` middleware).

The panel has three tabs:

### Plans
Manage the SaaS subscription catalogue. Each plan has: `code` (e.g. `SOLO_DOCTOR`, `SMALL_CLINIC`), `name`, `price_cents`, `billing_cycle` (MONTHLY/YEARLY), `max_users`, `max_doctors`, `is_active`.

Seeded plans:

| Code | Price | Max users | Max doctors |
|---|---|---|---|
| `SOLO_DOCTOR` | ₹999.00/mo | 1 | 1 |
| `SMALL_CLINIC` | ₹1,999.00/mo | 10 | 5 |

### Features
Feature flags (`feature_flags` table) gate product capabilities per plan/tenant. Each has: `key`, `description`, `is_global`, `default_enabled`. Toggle a feature to enable/disable it across the platform.

### AI Models
Manage AI provider models (`ai_models`): provider, model name, capabilities, cost, enabled/disabled. Used by `AIManager` to route clinical draft generation.

All changes are audit-logged via `App\Services\Audit\AuditService` (action `super_admin.feature_flag.save` / `delete`).

## 3. Tenant management

Tenants are created via the Super Admin panel or the API (`POST /api/v1/subscriptions/activate` binds a plan to a tenant). A tenant row carries: `slug`, `name`, `plan_code`, `status` (`TRIAL`/`ACTIVE`/`SUSPENDED`), `system` (`AYURVEDA`/`SIDDHA`/`HOMEOPATHY`/`GENERAL`), `country_code`, `currency`, `timezone`, `trial_ends_at`.

Tenant isolation is enforced by the `BelongsToTenant` trait (global scope + force-stamping `tenant_id` on create) and the `SetTenantContext` middleware. **Never trust a client-supplied `tenant_id`** — it is always resolved server-side from the authenticated user or API token.

## 4. User management

Within a tenant, the clinic owner manages staff (`DoctorService` handles doctor onboarding/profile/availability). The `users` table links a user to a tenant via `tenant_id` and carries `role`, `is_active`, and per-user `permissions`.

- A doctor **must** be an active practitioner belonging to the booking tenant to be selectable for online/public booking.
- Inactive users are hidden from public booking and cannot log in (`EnsureAccountIsActive` middleware).

## 5. Plans and subscriptions

Subscriptions (`subscriptions` table) link a tenant to a plan with a billing cycle, start/end dates, and status (`TRIAL`/`ACTIVE`/`EXPIRED`/`CANCELLED`). `PlanService::activate()` / `cancel()` are the canonical state transitions.

The scheduler's `klinic:check-subscriptions` command expires subscriptions past their `end_date` daily at 00:30.

SaaS subscription billing uses Cashfree via `CashfreeSubscriptionProvider`. Clinic-side consultation payments also use Cashfree (online) or CASH/UPI/CARD/BANK_TRANSFER/CHEQUE/OTHER (in-clinic).

## 6. Integration configuration

Provider integrations are configured via environment variables (see [ENVIRONMENT.md](ENVIRONMENT.md)) and stored credentials in `integration_accounts`. The app degrades gracefully when a provider is unconfigured — it boots and runs without keys, and the affected channel reports `FAILED` rather than throwing.

| Domain | Interface | Concrete providers |
|---|---|---|
| Payments | `PaymentGatewayInterface` | `CashfreePaymentProvider` |
| Subscriptions | `SubscriptionProviderInterface` | `CashfreeSubscriptionProvider` |
| Video | `VideoProviderInterface` | `GoogleMeetProvider` |
| WhatsApp | `WhatsAppProviderInterface` | (configured per env) |
| SMS | `SmsProviderInterface` | (configured per env) |
| Email | Laravel mail | SMTP / log |
| Storage | `StorageProviderInterface` | local disk |
| OCR | `OcrProviderInterface` | `GoogleVisionOcrProvider`, `TesseractOcrProvider` |
| Speech | `SpeechProviderInterface` | (configured per env) |
| AI | `AiProviderInterface` | `GeminiProvider` |

## 7. Notification templates

Notification templates (`notification_templates`) define the rendered body/subject for each event/channel. The `NotificationTemplateSeeder` seeds defaults; tenant-specific overrides are supported (`tenant_id` set → override; `tenant_id` null → global fallback).

Channels: `in_app`, `sms`, `whatsapp`, `email`. Event keys include `appointment.confirmation`, `payment.received`, `appointment.reminder`, `treatment.reminder`, `followup.reminder`.

Templates use `{{variable}}` placeholders (e.g. `{{patient_name}}`, `{{doctor_name}}`, `{{appointment_date}}`, `{{start_time}}`, `{{amount}}`, `{{invoice_number}}`). The `NotificationService::render()` method substitutes them.

## 8. Financial operations

### Cash register
`CashRegisterService` manages per-user cash drawers: open (opening cash), record payments/refunds/expenses, compute expected vs actual cash, close. Manual (in-clinic) payments are linked to the active cash register.

### Billing
`BillingService` handles invoices (`DRAFT` → `ISSUED` → `PARTIALLY_PAID` → `PAID`; `→ VOID`; `PAID → REFUNDED`) and payment recording. Payments use the canonical status set: `PENDING`, `SUCCESS`, `FAILED`, `REFUNDED`, `PARTIALLY_REFUNDED`.

Payment recording is concurrency-safe: the invoice is locked with `lockForUpdate()` before recalculating the outstanding balance, and overpayment is rejected. Gateway payments are deduplicated by `gateway_payment_id` (a `payments(gateway, gateway_payment_id)` unique constraint is the hard backstop), so duplicate webhooks never double-record.

### Refunds
`BillingService::refund()` issues refunds against a payment; full refund marks the invoice `REFUNDED`, partial refunds are tracked per-payment. Financial records are immutable — corrections are void/refund/new-record, never delete.

### Webhooks
Cashfree webhooks hit `POST /api/v1/webhooks/payments` → `WebhookController` → `WebhookProcessor`. The processor:
1. Verifies the webhook signature (if the gateway is configured).
2. Verifies the payment server-side via `verify($orderId)` — never trusts the webhook payload alone.
3. Records the payment (idempotent on `gateway_payment_id`), promotes a linked online booking appointment to `CONFIRMED`, marks the invoice `PAID`.
4. Idempotency key is a composite `(gateway_order_id :: gateway_payment_id :: event_type)` so a failed-then-succeeded sequence on the same order is processed in full.

## 9. Audit logging

`AuditService::record()` writes `audit_logs` entries (action, category, before/after JSON, auditable morph, user, IP, user agent). Wired into auth (login/logout), billing (payment/refund), webhooks, and Super Admin config changes. The `klinic:cleanup` command purges old audit logs per `config('klinic.audit_retention_days', 365)`.

## 10. Backups and maintenance

- **Database**: back up the MySQL database and `storage/app` (documents, receipts, PDFs) on your preferred schedule.
- **Logs**: Laravel logs to `storage/logs/laravel.log`; rotate via logrotate.
- **Cleanup**: the `klinic:cleanup` scheduler command purges expired queue tokens and audit logs beyond retention.
- **Reconciliation**: `klinic:reconcile-payments` reconciles pending payments against the Cashfree gateway hourly (no-ops gracefully when the gateway is unconfigured).

## 11. Routine operations

| Task | How |
|---|---|
| Add a plan | Super Admin panel → Plans → create, or `PlanService::activate()` via API |
| Toggle a feature | Super Admin panel → Features |
| Suspend a tenant | Set `tenants.status = SUSPENDED` (the `SetTenantContext` middleware + scopes enforce isolation) |
| Rotate an API token | `POST /api/v1/auth/login` re-issues; old tokens are hashed in `api_tokens` |
| Reconcile a stuck payment | `php artisan klinic:reconcile-payments` |
| Retry a failed notification | `php artisan klinic:retry-notifications` (or wait for the 5-min scheduler tick) |
| Clear cached config/routes | `php artisan optimize:clear` |
