#!/usr/bin/env bash
# Readiness gate entrypoint for the assignment-ready service.
#
# Reuses the web image but does no serving: it polls every service until the
# whole stack answers, prints a banner with the URLs, and touches the marker
# file its healthcheck watches. It then keeps checking and removes the marker
# if anything regresses, so `docker compose ps` reflects the true state.
set -euo pipefail

WEB="http://web:8080"
STUB="http://erp-refund-stub:8081"
SEARCH="http://search:9200"
WEB_PORT=${WEB_PORT:-8080}
STUB_PORT=${STUB_PORT:-8081}
MARKER=/tmp/assignment-ready

# Reachability, NOT following redirects: the storefront and /admin answer with a
# 302 to the configured base URL (http://localhost:8080), which is not reachable
# from inside this container, so -L would wrongly fail. A 2xx/3xx means "up".
# The health endpoint must be a true 200 (503 = not ready).
http_ok() {
  local code
  code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 "$1" 2>/dev/null) || return 1
  [ "$code" -ge 200 ] && [ "$code" -lt 400 ]
}
check_all() {
  http_ok "${WEB}/assignment-health.php" \
    && http_ok "${WEB}/" \
    && http_ok "${WEB}/admin" \
    && http_ok "${STUB}/health" \
    && http_ok "${SEARCH}/_cluster/health?wait_for_status=yellow&timeout=3s"
}

print_banner() {
  echo ""
  echo "==================================================================="
  echo "assignment-ready - storefront http://localhost:${WEB_PORT} | admin /admin | stub http://localhost:${STUB_PORT} | run bin/assignment-status"
  echo "==================================================================="
  echo ""
}

echo "[assignment-ready] waiting for the stack to come up..."
while ! check_all; do
  sleep 3
done

touch "$MARKER"
print_banner

# Keep watching; drop the marker on regression, restore it on recovery.
while true; do
  sleep 30
  if check_all; then
    if [ ! -f "$MARKER" ]; then
      touch "$MARKER"
      echo "[assignment-ready] stack recovered"
    fi
  else
    if [ -f "$MARKER" ]; then
      rm -f "$MARKER"
      echo "[assignment-ready] regression: a service stopped responding"
    fi
  fi
done
