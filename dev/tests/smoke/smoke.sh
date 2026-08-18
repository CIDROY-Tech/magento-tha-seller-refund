#!/usr/bin/env bash
# Environment smoke test. Runs inside the web container (via `assignment-test
# smoke`). Confirms the stack is wired together: the storefront, admin, health
# endpoint, and ERP stub answer, and a Create Refund round-trip against the stub
# happy path (failmode "ok") returns an erp_refund_id and is recorded.
#
# This is a boot/wiring check, not a module test - the full Magento refund flow
# is covered by the integration suite. It stays green on every branch.
set -euo pipefail

WEB=${WEB_BASE_URL:-http://localhost:8080}
ERP=${ERP_STUB_BASE_URL:-http://erp-refund-stub:8081}
ERP=${ERP%/}

pass=0
fail=0

check() {
  local label=$1
  shift
  if "$@" >/dev/null 2>&1; then
    echo "ok   - ${label}"
    pass=$((pass + 1))
  else
    echo "FAIL - ${label}"
    fail=$((fail + 1))
  fi
}

echo "== smoke: reachability =="
check "health endpoint 200" curl -fsS "${WEB}/assignment-health.php"
check "storefront 200"      curl -fsSL "${WEB}/"
check "admin login 200"     curl -fsSL "${WEB}/admin"
check "erp stub health 200" curl -fsS "${ERP}/health"

echo "== smoke: Create Refund happy path (failmode ok) =="
# Start from a clean stub and force the success mode.
curl -fsS -X POST "${ERP}/_debug/reset" >/dev/null 2>&1 || true
curl -fsS -X POST "${ERP}/erp-api/v1/_debug/failmode" \
  -H 'Content-Type: application/json' \
  -d '{"mode":"ok"}' >/dev/null 2>&1 || true

REFUND_NO="SR-SMOKE-000001"
CREATE_BODY=$(cat <<JSON
{
  "refund_no": "${REFUND_NO}",
  "seller_order_id": "SO-SMOKE",
  "tax_mode": "TAX_EXCLUDED",
  "currency": "JPY",
  "lines": [
    { "sku": "SMOKE-01", "quantity": 1, "amount": "1000.0000", "taxes": [{ "code": "010", "amount": "100.0000" }] }
  ],
  "shipping_amount": "0.0000",
  "grand_total": "1100.0000"
}
JSON
)

CREATE_RESP=$(curl -fsS -X POST "${ERP}/erp-api/v1/refunds" \
  -H 'Content-Type: application/json' \
  -d "${CREATE_BODY}" 2>/dev/null || echo "")

if printf '%s' "$CREATE_RESP" | grep -q 'erp_refund_id'; then
  echo "ok   - create returned erp_refund_id"
  pass=$((pass + 1))
else
  echo "FAIL - create did not return erp_refund_id (resp: ${CREATE_RESP})"
  fail=$((fail + 1))
fi

REFUNDS=$(curl -fsS "${ERP}/_debug/refunds" 2>/dev/null || echo "")
if printf '%s' "$REFUNDS" | grep -q "$REFUND_NO"; then
  echo "ok   - stub recorded ${REFUND_NO}"
  pass=$((pass + 1))
else
  echo "FAIL - stub did not record ${REFUND_NO} (resp: ${REFUNDS})"
  fail=$((fail + 1))
fi

echo ""
echo "smoke summary: ${pass} passed, ${fail} failed"
[ "$fail" -eq 0 ]
