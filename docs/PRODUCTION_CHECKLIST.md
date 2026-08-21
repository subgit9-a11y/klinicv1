# Production Checklist

Run before the first live tenant. Every item maps to code in this repo; do not
go live with a blank answer.

## Environment
- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_URL=https://<domain>` (HTTPS everywhere; session cookie `secure` defaults on `https`)
- [ ] All secrets (DB, Cashfree, WhatsApp/SMS/email, Gemini, S3, OAuth) in env — never committed
- [ ] `SESSION_SECURE_COOKIE=true` (default when APP_URL is https)

## Security
- [ ] Super Admin password rotated from the seeded one; 2FA enabled
- [ ] `klinic:check-subscriptions` (cron) runs — expired trials suspend automatically
- [ ] Tenant suspension tested: suspended clinic users get signed out, API tokens 403
- [ ] `GET /health` returns 200; `GET /health/ready` returns 200 (DB + queue tables)
- [ ] Security headers present on a page (`curl -I https://<domain>/login` → X-Frame-Options DENY, CSP)

## Payments (Cashfree)
- [ ] Sandbox → live credentials swapped; `CASHFREE_APP_ID`/`CASHFREE_SECRET_KEY` set
- [ ] Webhook URL registered in Cashfree dashboard → `POST /api/v1/webhooks/payments`
- [ ] A real ₹1 sandbox booking → payment → webhook → appointment CONFIRMED
- [ ] A real ₹1 sandbox refund → refund row + invoice REFUNDED + cash register entry
- [ ] `ReconcilePayments` (hourly) runs clean

## Background processes
- [ ] Queue worker running (`systemctl status klinic-worker` or the cPanel cron loop)
- [ ] Scheduler running (`* * * * * php artisan schedule:run` in cron)
- [ ] `queue:failed` empty after a smoke run; retry loop verified (`klinic:retry-notifications`)
- [ ] After each deploy: `php artisan queue:restart`

## Backups / DR
- [ ] Daily `klinic:backup-database` cron writes to `storage/app/backups/`
- [ ] Backup directory is OFF the web root; off-host copy (S3/rsync) configured
- [ ] Restore tested once into a scratch database
- [ ] RPO ≤ 24h, RTO ≤ 4h documented in the ops runbook

## Monitoring
- [ ] Uptime monitor on `/health/ready`
- [ ] Laravel logs aggregated (stack → stderr / file) with a retention policy
- [ ] Alert on `queue:failed` count > 0 for > 15 min
- [ ] Alert on disk usage > 80% (storage + backups)

## Compliance / records
- [ ] Audit log retention configured (`klinic.audit_retention_days`, default 365)
- [ ] Patient-data access reviewed (tenant isolation matrix tests pass)
- [ ] AI governance reviewed (all AI output draft-only; approval required)

## First tenant
- [ ] Onboard via Super Admin → Clinics (owner created) or public `/signup`
- [ ] Clinic owner completes the guided setup (profile → doctor → service → ward)
- [ ] A real patient → appointment → invoice → payment → receipt round-trip
