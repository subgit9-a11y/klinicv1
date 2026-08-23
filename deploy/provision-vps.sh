#!/usr/bin/env bash
# Provision an Ubuntu/Debian VPS for Klinic 360.
# Safely idempotent — re-running is a no-op except where noted. Run as root/sudo.

set -euo pipefail

APP_DIR="/var/www/klinic"
REPO_URL="${1:-https://github.com/subgit9-a11y/klinicv1.git}"
BRANCH="${2:-feature/database-design}"

if [ "$(id -u)" -ne 0 ]; then
  echo "Run with sudo: sudo bash deploy/provision-vps.sh" >&2
  exit 1
fi

echo "==> System packages"
apt-get update -qq
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
  php8.4-cli php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl \
  php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl php8.4-sqlite3 php8.4-readline \
  mariadb-server nginx git unzip curl

echo "==> Composer"
if ! command -v composer >/dev/null; then
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

echo "==> Node.js (for frontend build)"
if ! command -v node >/dev/null; then
  curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
  apt-get install -y nodejs
fi

echo "==> deploy user"
id -u deploy >/dev/null 2>&1 || useradd -m -s /bin/bash deploy

echo "==> Database"
if ! systemctl is-active --quiet mariadb; then systemctl enable --now mariadb; fi
read -rsp "MySQL root or SETDB password for 'klinic' user (input hidden): " DB_PASS; echo
mysql -e "CREATE DATABASE IF NOT EXISTS klinic;
          CREATE USER IF NOT EXISTS 'klinic'@'localhost' IDENTIFIED BY '${DB_PASS}';
          GRANT ALL PRIVILEGES ON klinic.* TO 'klinic'@'localhost';
          FLUSH PRIVILEGES;"

echo "==> Clone/update repo"
mkdir -p "${APP_DIR}"
chown deploy:deploy "${APP_DIR}"
if [ ! -d "${APP_DIR}/.git" ]; then
  sudo -u deploy git clone "${REPO_URL}" "${APP_DIR}"
fi
cd "${APP_DIR}"
sudo -u deploy git fetch --all
sudo -u deploy git checkout "${BRANCH}"

echo "==> Env"
if [ ! -f .env ]; then
  sudo -u deploy cp .env.example .env
  # Fill APP_KEY + DB; other providers edited manually later.
  sed -i "s/DB_DATABASE=.*/DB_DATABASE=klinic/" .env
  sed -i "s/DB_USERNAME=.*/DB_USERNAME=klinic/" .env
  sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=${DB_PASS}/" .env
  sed -i "s/APP_ENV=.*/APP_ENV=production/" .env
  sed -i "s/APP_DEBUG=.*/APP_DEBUG=false/" .env
fi
sudo -u deploy php artisan key:generate --force 2>/dev/null || php artisan key:generate --force

echo "==> Dependencies"
sudo -u deploy composer install --no-dev --optimize-autoloader --quiet
sudo -u deploy npm ci --include=dev --silent
sudo -u deploy npm run build --silent

echo "==> Migrate + seed + optimize"
sudo -u deploy php artisan migrate --force
sudo -u deploy php artisan db:seed --force
sudo -u deploy php artisan optimize

echo "==> Permissions"
chown -R deploy:deploy "${APP_DIR}"
chmod -R ug+rwx "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"

echo "==> Nginx"
install -m 644 deploy/nginx-klinic.conf /etc/nginx/sites-available/klinic.conf
ln -sfn /etc/nginx/sites-available/klinic.conf /etc/nginx/sites-enabled/klinic.conf
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

echo "==> Queue worker (systemd)"
install -m 644 deploy/klinic-worker.service /etc/systemd/system/klinic-worker.service
systemctl daemon-reload
systemctl enable --now klinic-worker

echo "==> Scheduler (cron)"
install -m 644 deploy/klinic.cron /etc/cron.d/klinic

echo "==> Firewall"
if command -v ufw >/dev/null; then
  ufw allow OpenSSH
  ufw allow 'Nginx Full'
  yes | ufw enable || true
fi

echo ""
echo "Done."
echo "Next steps:"
echo "  1. Edit ${APP_DIR}/.env with Cashfree/WhatsApp/Msg91/Gemini keys, then: php artisan optimize"
echo "  2. Set your domain in deploy/nginx-klinic.conf, reload nginx, then: certbot --nginx"
echo "  3. Put https://<domain>/health/ready under an uptime monitor"
echo "  4. Rotate the seeded Super Admin password and enable 2FA"
