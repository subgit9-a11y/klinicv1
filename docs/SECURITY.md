# Security

Klinic 360 implements the controls mandated by Document 2 §26–27.

## Transport
- HTTPS enforced in production (`FORCE_HTTPS=true`), secure session cookies.
- SSL termination at the host; HSTS recommended.

## Authentication & Authorization
- Passwords hashed with bcrypt via Laravel `Hash`.
- API access via Sanctum tokens (hashed at rest in `api_tokens`); tokens have abilities and expiry.
- Session + Sanctum auth; stateful SPA domains restricted via `SANCTUM_STATEFUL_DOMAINS`.
- RBAC: role-based permissions (`SUPER_ADMIN`, `CLINIC_OWNER`, `DOCTOR`, `RECEPTIONIST`, `THERAPIST`, `NURSE`, `IPD_STAFF`, `ASSISTANT`) with granular dotted abilities (e.g. `patients.view`).
- Laravel Policies enforce per-resource authorization on every API + web action.

## Multi-Tenancy
- `tenant_id` is force-stamped from `TenantContext` on create (overrides client input) — mass-assignment cannot inject another tenant.
- Global scopes filter all tenant-scoped models to the active tenant.
- Super Admin (no tenant) is the only cross-tenant role.

## Input Validation
- Form Requests validate all API + web input.
- Blade uses `{{ }}` (escaped output) — no raw `{!! !!}` on user content.
- Parameterized queries via Eloquent/QueryBuilder (no string-concatenated SQL).

## Rate Limiting
- Login: `throttle:5,1` (5 attempts/min) — deters brute force.
- Authenticated API: `throttle:60,1` (60 requests/min per token).

## Secrets
- `.env` is never committed (gitignored).
- Credentials (gateway keys, AI keys) read from env config, not hard-coded.
- Private documents stored on a protected disk; access via signed URLs / authorized streaming only.

## Audit Logging
`AuditLog` records: auth events, patient access, clinical changes, prescriptions, treatment, IPD, invoices, payments, refunds, AI requests, document access, permission changes, and Super Admin config changes. Retention configurable via `KLINIC_AUDIT_RETENTION_DAYS` (default 365); `klinic:cleanup` purges aged entries.

## Production Hardening Checklist
- `APP_DEBUG=false`
- `APP_ENV=production`
- `FORCE_HTTPS=true`
- `SESSION_SECURE_COOKIE=true`
- Config/route/view/event cache enabled
- Valid SSL certificate
- Cron schedule entry installed
- Queue worker supervised
- Regular database backups
- Credentials rotated periodically
