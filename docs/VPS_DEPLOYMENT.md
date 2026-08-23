## VPS deployment (Ubuntu 24 / Debian 13)

For full control over PHP-FPM + MariaDB + queue worker + scheduler. Prefer
this over cPanel shared hosting for scale. Minimum 2 vCPU / 4 GB RAM.

### One command
```bash
sudo bash deploy/provision-vps.sh "https://github.com/subgit9-a11y/klinicv1.git" "feature/database-design"
```
The script prompts for the `klinic` DB password; everything else is
idempotent (safe re-run). It installs PHP 8.4-FPM, Node 22, MariaDB, Nginx,
Composer; clones the repo; runs migrations + seeders + frontend build; wires
the queue worker (systemd) and scheduler (cron); and enables UFW for
HTTP/HTTPS/SSH.

### After the script
1. Edit `/var/www/klinic/.env` with Cashfree/WhatsApp/Msg91/Gemini keys.
2. `php artisan optimize`
3. Set `your-domain.example.com` in `deploy/nginx-klinic.conf`, reload Nginx.
4. `sudo certbot --nginx -d your-domain.example.com --redirect` (installs python3-certbot-nginx first).
5. Run `bash deploy/preflight.sh https://your-domain.example.com` — must pass.

### First tenant
- Signup at `https://<domain>/signup` or Super Admin → Tenants.
- Complete the guided setup (profile → doctor → service → ward).
- Create a test patient → appointment → invoice → payment receipt.

### Maintenance hosts
- **Worker**: `systemctl status klinic-worker`, restart after deploys → `php artisan queue:restart` (Broadcasts of Failure/DNoS loop).
- **Scheduler**: `tail -f /var/log/syslog | grep klinic:` for cron entries.
- **Backups**: `php artisan klinic:backup-database` (daily at 03:00 via scheduler) + off-host copy to S3/B2.

### Hardening checklist (docs/PRODUCTION_CHECKLIST.md)
- `APP_ENV=production`, `APP_DEBUG=false`
- HTTPS domain + `SESSION_SECURE_COOKIE=true`
- Rotated Super Admin password + 2FA
- Webhook registered in Cashfree dashboard
- Uptime monitor on `/health/ready`
