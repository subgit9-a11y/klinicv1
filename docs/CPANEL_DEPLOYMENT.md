# cPanel Deployment Guide

Klinic 360 deploys to cPanel shared/hosting with the Laravel app **outside** the web root and the document root pointing at `/public`.

## Prerequisites on the host
- PHP 8.4 (extensions: xml, mbstring, pdo_mysql, curl, zip, gd, intl, openssl)
- MySQL 8 / MariaDB 10.6+
- Composer 2, SSH access, Git, SSL certificate
- Cron jobs enabled

## 1. Upload the application
Place the project in `~/klinic360` (outside `public_html`). The structure:
```
~/klinic360/
├── app/
├── public/        ← document root points here
├── routes/
├── ...
```

## 2. Configure the document root
In cPanel → **Domains** (or **Addon Domains**), set the document root to:
```
/home/USER/klinic360/public
```
Do **not** serve files from `public_html`.

## 3. Install dependencies
Via SSH:
```bash
cd ~/klinic360
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```
If Node is not available on the host, build assets locally and upload `public/build/`.

## 4. Environment
```bash
cp .env.example .env
php artisan key:generate
```
Edit `.env` (see [ENVIRONMENT.md](ENVIRONMENT.md)) — set `APP_ENV=production`, `APP_DEBUG=false`, the database, queue, and gateway credentials.

## 5. Database
```bash
php artisan migrate --force
php artisan db:seed --force
```

## 6. Storage & cache
```bash
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

## 7. Permissions
The web server needs write access to:
- `storage/`
- `bootstrap/cache/`

## 8. Cron (scheduler)
Add a single cron entry in cPanel → **Cron Jobs**:
```
* * * * * cd /home/USER/klinic360 && php artisan schedule:run >> /dev/null 2>&1
```

## 9. Queue worker
The app uses the `database` queue driver. For reliability run a persistent worker via Supervisor (if available) or via cron:
```
* * * * * cd /home/USER/klinic360 && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
```
For higher throughput, configure a future Redis-based queue.

## 10. SSL
Force HTTPS in cPanel → **SSL/TLS Status** → AutoSSL. Set `APP_URL=https://your-domain` and `FORCE_HTTPS=true` in `.env`.

## 11. Updates
```bash
cd ~/klinic360
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
```

## Troubleshooting
- **500 on install**: check `storage/logs/laravel.log` and that `storage/` + `bootstrap/cache/` are writable.
- **Blank page**: `APP_DEBUG=false` hides errors — temporarily set `true` to diagnose.
- **Cron not running jobs**: confirm the cron entry runs as the correct user and `php` is on PATH (use the full path if needed).
- **Queue stuck**: ensure `QUEUE_CONNECTION=database` and the `jobs` table exists.
