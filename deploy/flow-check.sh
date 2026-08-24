#!/usr/bin/env bash
# End-to-end flow check against a deployed Klinic 360 instance.
# Usage: bash deploy/flow-check.sh http://50.6.242.113
BASE="${1:-http://localhost}"
CURL="curl -s --max-time 20"
PASS=0; FAIL=0

assert_code() {
  local path="$1" expected="$2" label="$3" extra="${4:-}"
  local actual
  actual=$(eval "$CURL -o /dev/null -w '%{http_code}' $extra '$BASE$path'")
  if [ "$actual" = "$expected" ]; then echo "  PASS $label ($actual)"; PASS=$((PASS+1));
  else echo "  FAIL $label (expected $expected, got $actual)"; FAIL=$((FAIL+1)); fi
}

echo "==> Health"
assert_code /health 200 "liveness"
assert_code /health/ready 200 "readiness"

echo "==> Public pages"
assert_code /login 200 "login page"
assert_code /signup 200 "signup page"
assert_code /book 200 "public booking"
assert_code /book/status 200 "booking status"

echo "==> Static assets"
assert_code /livewire/livewire.min.js 200 "livewire.js"
assert_code /build/assets/app-C5n3mdp1.css 200 "vite css"
assert_code /build/assets/app-DW0rLh6C.js 200 "vite js"

echo "==> Security headers"
for h in X-Frame-Options X-Content-Type-Options Referrer-Policy; do
  if $CURL -I "$BASE/login" | grep -qi "^$h"; then echo "  PASS header $h"; PASS=$((PASS+1)); else echo "  FAIL header $h"; FAIL=$((FAIL+1)); fi
done

echo "==> Auth walls (guest redirects)"
assert_code /dashboard 302 "guest dashboard -> login"
assert_code /patients 302 "guest patients -> login"
assert_code /super-admin/tenants 302 "guest super-admin -> login"

echo "==> API auth"
assert_code /api/v1/patients 401 "unauthenticated API -> 401"

echo "==> Livewire endpoints"
assert_code /livewire/update 405 "livewire update GET -> 405 (POST-only)"
# POST with an empty body should be 419 (CSRF mismatch) when session is fresh.
post_code=$($CURL -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' -d '{}' "$BASE/livewire/update")
if [ "$post_code" = "419" ] || [ "$post_code" = "200" ]; then echo "  PASS livewire update POST ($post_code)"; PASS=$((PASS+1)); else echo "  FAIL livewire update POST ($post_code)"; FAIL=$((FAIL+1)); fi

echo ""
echo "Results: $PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ] && echo "FLOW CHECK PASSED" || { echo "FLOW CHECK FAILED"; exit 1; }
