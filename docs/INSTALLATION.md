# Installation Guide

This guide covers local development, staging, and production installation for Klinic 360.

For environment variables, see [ENVIRONMENT.md](ENVIRONMENT.md). For cPanel-specific deployment, see [CPANEL_DEPLOYMENT.md](CPANEL_DEPLOYMENT.md). For security hardening, see [SECURITY.md](SECURITY.md).

## 1. System requirements

- **PHP 8.3+** (8.4 recommended) with extensions: `xml`, `mbstring`, `pdo_sqlite` (dev) or `pdo_mysql` (prod), `curl`, `zip`, `gd`, `intl`, `bcmath`, `readline`
- **Composer 2**
- **Node.js 20+** and **npm 10+** (for building frontend assets with Vite)
- **MySQL/MariaDB 10.6+** for production (SQLite is used for dev/test by default)
- A process supervisor or cron daemon for the Laravel scheduler (production)

Install PHP extensions on Debian/Ubuntu:

```bash
sudo apt-get install -y php8.4-cli php8.4-mbstring php8.4-xml php8.4-curl \
  php8.4-mysql php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl \
  php8.4-sqlite3 php8.4-readline
```

Install Composer:

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

## 2. Local development setup

```bash
# 1. Clone and install PHP dependencies
git clone <repo-url> klinic360
cd klinic360
composer install

# 2. Install and build frontend assets
npm install
npm run build      # production build (use `npm run dev` for HMR during dev)

# 3. Configure environment
cp .env.example .env
php artisan key:generate

# 4. Run migrations and seeders (SQLite is the default dev driver)
php artisan migrate --graceful
php artisan db:seed

# 5. Start the development server
php artisan serve   # http://localhost:8000
```

For front-end hot-reload during development, run `npm run dev` in a second terminal alongside `php artisan serve`.

### Default seeded accounts

The `DatabaseSeeder` runs `PlanSeeder`, `SystemSettingsSeeder`, `NotificationTemplateSeeder`, and `AdminUserSeeder`, which create:

| Email | Role | Password | Notes |
|---|---|---|---|
| `superadmin@klinic360.test` | SUPER_ADMIN | `password` | Global, cross-tenant |
| `owner@ayurclinic.test` | CLINIC_OWNER | `password` | Demo tenant "Ayur Clinic (Demo)", SMALL_CLINIC plan |

**Change these credentials immediately in any non-local environment.**

## 3. Database configuration

### SQLite (default dev/test)

No extra setup — the dev database is a SQLite file. `php artisan migrate --graceful` creates it.

### MySQL / MariaDB (production)

1. Create the database and a least-privilege user:
   ```sql
   CREATE DATABASE klinic360 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'klinic_user'@'localhost' IDENTIFIED BY '<strong-password>';
   GRANT ALL PRIVILEGES ON klinic360.* TO 'klinic_user'@'localhost';
   FLUSH PRIVILEGES;
   ```
2. Set in `.env`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=klinic360
   DB_USERNAME=klinic_user
   DB_PASSWORD=<strong-password>
   ```
3. Run migrations:
   ```bash
   php artisan migrate --graceful
   php artisan db:seed --force
   ```

> **Note:** the codebase is written to be driver-portable. Patient search uses `CONCAT(first_name, ' ', COALESCE(last_name, ''))` on MySQL and `first_name || ' ' || last_name` on SQLite; report duration calculations use `TIMESTAMPDIFF(...)` on MySQL and `julianday(...)` on SQLite. Both paths are exercised by the test suite.

## 4. Queue and scheduler (production)

Klinic 360 dispatches async work (notifications, payment reconciliation, cleanup) to the `database` queue and runs scheduled commands via the Laravel scheduler.

### Queue worker

Run a queue worker under a process supervisor (systemd, Supervisor, or Horizon if you switch to Redis):

```bash
php artisan queue:work --queue=default --tries=3 --backoff=60
```

A systemd unit example:

```ini
[Unit]
Description=Klinic 360 queue worker
After=network.target mysql.service

[Service]
User=www-data
WorkingDirectory=/var/www/klinic360
ExecStart=/usr/bin/php artisan queue:work --queue=default --tries=3 --backoff=60
Restart=always

[Install]
WantedBy=multi-user.target
```

### Scheduler

Add a single cron entry:

```
* * * * * cd /var/www/klinic360 && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler wires these commands (see `bootstrap/app.php` `withSchedule()`):

| Command | Frequency | Purpose |
|---|---|---|
| `klinic:send-appointment-reminders` | every 15 min | Remind patients of upcoming appointments |
| `klinic:send-treatment-reminders` | every 15 min | Remind patients of treatment sessions |
| `klinic:send-followup-reminders` | daily 09:00 | Remind patients of due follow-ups |
| `klinic:retry-notifications` | every 5 min | Retry pending/failed notification deliveries |
| `klinic:check-subscriptions` | daily 00:30 | Expire subscriptions past their end date |
| `klinic:reconcile-payments` | hourly | Reconcile pending payments against the gateway |
| `klinic:cleanup` | daily 02:00 | Purge stale tokens / old audit logs per retention |

List scheduled jobs and their next run: `php artisan schedule:list`.

## 5. Frontend build

```bash
npm run build    # production assets to public/build/
npm run dev      # Vite dev server with HMR (development only)
```

Always run `npm run build` before deploying so `public/build/manifest.json` is current.

## 6. Testing

```bash
php artisan test          # full Pest suite
composer test             # alias
php artisan test --filter=PatientServiceTest   # a single suite
```

The suite uses `RefreshDatabase` against an in-memory/temp SQLite database. `phpunit.xml` sets `QUEUE_CONNECTION=sync` so queued jobs run inline during tests.

## 7. Production deployment checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` set to the real URL
- [ ] `php artisan key:generate` (or confirm `APP_KEY` is set)
- [ ] `FORCE_HTTPS=true`, `SESSION_SECURE_COOKIE=true`
- [ ] MySQL/MariaDB database created and migrated (`php artisan migrate --graceful --force`)
- [ ] Seeders run **only on first deploy** (`php artisan db:seed --force`), then change default admin passwords
- [ ] `npm run build` run; `public/build/` deployed
- [ ] Storage and bootstrap/cache writable by the web user: `chmod -R 775 storage bootstrap/cache`
- [ ] `php artisan storage:link` (if document/receipt file storage is used)
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] Queue worker running under a process supervisor
- [ ] Scheduler cron entry added
- [ ] Provider credentials set (Cashfree, WhatsApp, SMS, email, AI) — see [ENVIRONMENT.md](ENVIRONMENT.md). The app boots and degrades gracefully when providers are unconfigured, so missing keys are not fatal.
- [ ] Set `KLINIC_PUBLIC_BOOKING_TENANT_ID` to the tenant that owns the public booking page (or leave `0` to disable public booking).

## 8. Public booking configuration

The public consultation booking page (`/book`) is tenant-scoped. Configure in `config/klinic.php` `public_booking` (overridable via env):

| Env var | Default | Meaning |
|---|---|---|
| `KLINIC_PUBLIC_BOOKING_TENANT_ID` | `0` | Tenant that owns the public booking page. `0` = not configured → booking is unavailable. |
| `KLINIC_ONLINE_CONSULTATION_FEE_CENTS` | `49900` (₹499.00) | Consultation fee charged via Cashfree |
| `KLINIC_ONLINE_CONSULTATION_MINUTES` | `30` | Default appointment duration |
| `KLINIC_BOOKING_RETURN_URL` | `/book/done` | Post-payment redirect URL |

Flow: patient picks doctor + real available slot → Cashfree order created → patient pays → Cashfree webhook (signature-verified) + server-side `verify()` → appointment promoted to CONFIRMED → invoice PAID → in-app notification queued. When the gateway is unconfigured, the appointment is created as SCHEDULED with no payment required (dev/test mode).

## 9. Troubleshooting

- **`php: command not found`** — PHP is not installed; install via the apt command in §1.
- **`composer: command not found`** — install Composer per §1.
- **Livewire page blanks on re-render** — a Blade view has multiple root elements or an inline SVG with an `<?xml ?>` prolog. Every Livewire view must have a single root `<div>`.
- **`where('date_col', '2026-08-13')` returns no rows on SQLite** — SQLite stores `date` columns as full datetime text. Use `whereBetween('col', [$dayStart, $dayEnd])` or `whereDate('col', $date)`.
- **Webhook returns 302 instead of 422 JSON** — the exception handler renders JSON only for `api/*` paths or `Accept: application/json` requests. Fetch callers must send the JSON accept header.
