# Klinic 360

AI-powered multi-tenant B2B SaaS clinic operating system for **Ayurveda, Siddha & Homeopathy** practices. Modular Laravel monolith with two plans: Solo Doctor (₹999/mo) and Small Clinic (₹1,999/mo).

## Tech Stack
- PHP 8.4 / Laravel 13
- Livewire 3 + Alpine.js + Tailwind CSS 4 + Vite 8
- Database: SQLite (dev/test), MySQL/MariaDB (production)
- Auth: Laravel sessions + Sanctum (API tokens)
- Queue: database driver

## Requirements
- PHP 8.4+ with extensions: xml, mbstring, sqlite3 (or pdo_mysql), curl, zip, gd, intl
- Composer 2, Node.js 20+, npm 10+
- MySQL/MariaDB 10.6+ (production)

## Quick Start
```bash
composer install
npm install
npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate --graceful
php artisan db:seed
php artisan serve
```

## Build Commands
- `npm run build` — compile frontend assets (Vite)
- `npm run dev` — Vite dev server with HMR
- `php artisan migrate --graceful` — run migrations
- `php artisan serve` — dev server on :8000
- `php artisan test` — run the PHPUnit/Pest suite (`composer test`)
- `php artisan schedule:run` — run scheduled jobs (cron)
- `php artisan schedule:list` — list scheduled jobs and next-run times

## Scheduler
A single cron entry runs all scheduled jobs (Document 2 §25):
```
* * * * * cd /path-to-app && php artisan schedule:run >> /dev/null 2>&1
```
Scheduled jobs:
- `klinic:send-appointment-reminders` — every 15 min
- `klinic:send-treatment-reminders` — every 15 min
- `klinic:send-followup-reminders` — daily 09:00
- `klinic:retry-notifications` — every 5 min
- `klinic:check-subscriptions` — daily 00:30
- `klinic:reconcile-payments` — hourly
- `klinic:cleanup` — daily 02:00 (purges expired API tokens + aged audit logs)

## Queue Worker
```bash
php artisan queue:work --tries=3 --backoff=10
```
Use a process monitor (Supervisor) in production.

## API
REST API at `/api/v1` with Sanctum token auth, API Resources, Form Requests, tenant context, policies, and rate limiting. See [docs/API.md](docs/API.md).

## Testing
```bash
php artisan test                          # full suite
php artisan test --filter=SchedulerCommandsTest
```
376 tests / 986 assertions.

## Project Structure
See [AGENTS.md](AGENTS.md) for the per-phase repository memory (architecture decisions, gotchas, conventions).

## Deployment
See [docs/CPANEL_DEPLOYMENT.md](docs/CPANEL_DEPLOYMENT.md) for cPanel deployment and [docs/ENVIRONMENT.md](docs/ENVIRONMENT.md) for environment configuration.

---

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
