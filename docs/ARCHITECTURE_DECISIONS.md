# Architecture Decisions

## Auth: Laravel session + Sanctum (not Firebase / Supabase Auth)

Firebase/Supabase Auth was evaluated and rejected. The authentication layer is
tightly bound to the platform: `tenant_id` multi-tenancy, 2FA (Google2FA),
`EnsureAccountIsActive` middleware, tenant suspension enforcement, and the
DB-backed RBAC (`PermissionService`/`RbacService`). Moving identity to an
external provider would still require a local users table (so no simplification),
would push RBAC into un-auditable custom claims, and adds vendor lock-in to a
healthcare SaaS subject to Indian data-residency expectations. The current
implementation already covers MFA, brute-force throttling, API tokens and
tenant-scoped session suspension.

## Database: MySQL 8 / MariaDB (not Supabase/Postgres)

Postgres is a good engine, but this codebase is deliberately MySQL-oriented:
`app/Support/Sql.php` branches per-driver, `TIMESTAMPDIFF()` in reports,
`CAST(... AS SIGNED)` in token sequences, and a full test matrix validated on
both SQLite and MariaDB. Supabase's headline feature (row-level security for
tenant isolation) duplicates what `TenantContext` + `BelongsToTenant` global
scopes + the adversarial isolation matrix already enforce in app code. A
Postgres port would be a planned V2 migration (with its own test run), not a
like-for-like swap.

## Queue/scheduler: database driver, not Redis (yet)

The `database` queue driver is the production default (works on cPanel and
small VPS without extra infra). Redis is a performance upgrade for scale, not
a requirement — see `docs/QUEUE_OPERATIONS.md`.

## AI: draft-only clinical output

AI (scribe, summaries) never writes final clinical data. Every AI generation
records an `AiRequest` with `output_status` DRAFT/ERROR and only moves to the
record when a practitioner calls `approve`. Enforced by `AiScribeService`,
`AiApprovalBoard`, and the policy layer.

## Online payments: Cashfree, fail-closed

Payment verification is never trusted from the browser. Webhooks verify the
raw-body signature, then the order server-side, then the amount; booking
confirmation happens only after a settled payment. In production, a missing
Cashfree configuration fails closed (booking disabled) instead of accepting
free consultations.
