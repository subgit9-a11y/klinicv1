#!/usr/bin/env bash
# Post-deploy preflight: sanity-checks the deployment without touching data.
# Run as deploy (or any user with curl + optional sudo for service checks).

DOMAIN="${1:-https://localhost}"
APP_DIR="${APP_DIR:-/var/www/klinic}"
OK=1

check() { local label="$1" result="$2"; if [ "$result" -eq 0 ]; then echo "  OK  $label"; else echo "FAIL  $label"; OK=0; fi; }

echo "==> HTTP health"
check "GET $DOMAIN/health -> 200" "$(curl -s -o /dev/null -w '%{http_code}' "$DOMAIN/health" | grep -q 200 && echo 0 || echo 1)"
check "GET $DOMAIN/health/ready -> 200" "$(curl -s -o /dev/null -w '%{http_code}' "$DOMAIN/health/ready" | grep -q 200 && echo 0 || echo 1)"

echo "==> Security headers"
HEADERS=$(curl -sI "$DOMAIN/login")
for h in "X-Frame-Options" "X-Content-Type-Options" "Referrer-Policy"; do
  check "header $h"  "$(echo "$HEADERS" | grep -qi "$h" && echo 0 || echo 1)"
done

echo "==> Services"
if sudo -n true 2>/dev/null; then
  sudo systemctl is-active --quiet klinic-worker && Q=0 || Q=1
  check "klinic-worker active" "$Q"
  sudo systemctl is-active --quiet mariadb && M=0 || M=1
  check "mariadb active" "$M"
  sudo systemctl is-active --quiet nginx && N=0 || N=1
  check "nginx active" "$N"
else
  echo "    (skip) service checks need sudo"
fi

echo "==> Scheduler + queue wiring"
cd "$APP_DIR" && check "cron entry file owned" "[ -f /etc/cron.d/klinic ]"
check "no failed jobs backlog" "[ $(php artisan queue:failed 2>/dev/null | grep -c '^|') -eq 0 ]"

echo "==> Env flags"
ENV=$(cd "$APP_DIR" && php artisan about --only=environment 2>/dev/null)
check "APP_ENV=production" "$(echo "$ENV" | grep -qi 'production' && echo 0 || echo 1)"
check "APP_DEBUG=disabled" "$(echo "$ENV" | grep -qi 'Debug mode .. DISABLED\|Debug Mode .. DISABLED\|Disabled' && echo 0 || echo 1)"

echo ""
[ "$OK" -eq 1 ] && echo "Preflight PASSED" || { echo "Preflight FAILED — fix FAIL items above"; exit 1; }
