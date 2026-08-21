# ERP Refund API stub — contract

This service is a local stand-in for the ERP Refund API. It implements the three
business operations used by the refund subsystem (Create Refund, Read Refund
Status, Confirm Refund), plus debug and control endpoints the local harness uses
to drive failure scenarios. It has no external dependencies and stores its state
in a single JSON file, so it starts instantly and can be reset between runs.

## Runtime

- Node 20, standard library only.
- Listens on port `8081` (override with env `PORT`).
- Persists state to `<STATE_DIR>/refunds.json` (env `STATE_DIR`, default `/data`).
  When the state directory is not writable it falls back to `./state` next to
  `server.js`, so the test suite runs without a mounted volume.
- Hold duration for the timeout failmodes is controlled by env `STUB_HOLD_MS`
  (default `6000`).

All business endpoints are served under the `/erp-api/v1` prefix. Request and
response bodies are JSON.

## Idempotency model

- **Create Refund** deduplicates strictly on the request body's non-empty
  `refund_no`, combined with a hash of the full request payload:
  - Same non-empty `refund_no` **and** an identical payload -> the original
    stored record is returned (idempotent replay). No second record is created.
  - Same non-empty `refund_no` **but** a materially different payload -> `409`.
  - Missing or empty `refund_no` -> every call is treated as a brand-new credit
    note and a fresh `erp_refund_id` is generated. This models a client that
    changes its idempotency key and so creates duplicates.
- **Confirm Refund** deduplicates on the `erp_refund_id` in the URL. A refund
  that is already confirmed returns success when the transaction reference
  matches (or is omitted).
- The `X-Request-Id` request header is recorded against each Create attempt for
  audit only. It is **never** used as a deduplication key.

Money values in the payload are strings with four decimal places. The stub
preserves them as received; it does not reformat or recompute them.

## Business endpoints

### POST /erp-api/v1/refunds — Create Refund

Registers the refund and returns its ERP identifier.

Request body:

```json
{
  "refund_no": "SR-20260824-000123",
  "seller_order_id": "SO-1002",
  "tax_mode": "TAX_EXCLUDED",
  "currency": "JPY",
  "lines": [
    {
      "sku": "SELLER-RED-01",
      "quantity": 1,
      "amount": "1200.0000",
      "taxes": [{ "code": "010", "amount": "120.0000" }]
    }
  ],
  "shipping_amount": "0.0000",
  "grand_total": "1320.0000"
}
```

Success response (`200`):

```json
{
  "erp_refund_id": "ERP-8000123",
  "status": "refund-pending"
}
```

- `erp_refund_id` is issued from a monotonic counter with the shape `ERP-8######`.
- New refunds are stored with status `refund-pending`.
- Conflict response (`409`): `{ "error_code": "REFUND_CONFLICT", "message": "..." }`.

Optional headers: `X-Request-Id` (logged only).

### GET /erp-api/v1/refunds/{erp_refund_id} — Read Refund Status

Returns the ERP-side state of one credit note.

- Found (`200`): `{ "erp_refund_id": "ERP-8000123", "status": "refund-pending" }`.
- Not found (`404`): `{ "error_code": "NOT_FOUND", "message": "..." }`.
- Error (`5xx`) or rate limit (`429`) when the matching failmode is active.

### POST /erp-api/v1/refunds/{erp_refund_id}/confirm — Confirm Refund

Closes the credit note and sets its status to `refund-confirmed`.

Request body:

```json
{
  "transaction_number": "TXN-001",
  "transaction_date": "2026-08-25"
}
```

- Success response (`200`): `{ "erp_refund_id": "ERP-8000123", "status": "refund-confirmed" }`.
- Idempotent: a subsequent confirm with a matching transaction reference returns
  success again.
- Not found (`404`) when the identifier is unknown.
- Conflict (`409`) when a confirmed refund is re-confirmed with a different
  transaction reference.

### GET /health

Liveness probe. Always `200`:

```json
{ "status": "ok" }
```

## Control and debug endpoints

### POST /erp-api/v1/_debug/failmode

Selects the active failure behaviour.

Request body:

```json
{ "mode": "rate_limit_429", "times": 2 }
```

- `mode` is one of the modes listed below.
- `times` (optional) is the number of matching operations the mode applies to.
  When it counts down to zero the mode reverts to `ok`. Omit `times` for a sticky
  mode that stays active until changed or reset.

Response (`200`) echoes the applied `{ "mode", "times" }`. An unknown mode
returns `400`.

Failmodes:

| Mode | Affected operation(s) | Behaviour |
| --- | --- | --- |
| `ok` | — | Normal behaviour (default). |
| `timeout_before_accept` | Create | Holds ~`STUB_HOLD_MS` and persists nothing, then responds. The client will already have timed out; no record exists. |
| `timeout_after_accept` | Create | Persists the refund, then holds ~`STUB_HOLD_MS` before responding. The client times out but the credit note exists (duplicate-danger case). |
| `rate_limit_429` | Create, Read, Confirm | Responds `429` with a `Retry-After` header. |
| `server_error_5xx` | Create, Read, Confirm | Responds `503`. |
| `business_reject` | Create | Responds `422` with `{ "error_code": "REFUND_REJECTED", "message": "..." }`. |
| `delayed_status` | Read | Read Refund Status returns `404` for the first `times` reads, then found. |
| `already_confirmed` | Confirm | Confirm returns success as if the refund were already confirmed. |

### GET /_debug/refunds

Returns a snapshot of stored refunds and attempt counters.

```json
{
  "unique_refunds": 1,
  "attempts_total": 2,
  "refunds": [
    {
      "erp_refund_id": "ERP-8000123",
      "refund_no": "SR-20260824-000123",
      "status": "refund-pending",
      "attempts": [
        { "request_id": "req-aaa", "received_at": "2026-08-24T09:00:00.000Z", "outcome": "created" },
        { "request_id": "req-bbb", "received_at": "2026-08-24T09:00:05.000Z", "outcome": "idempotent-replay" }
      ]
    }
  ]
}
```

Each Create attempt is recorded against its refund with the `X-Request-Id`
(`request_id`), an ISO timestamp (`received_at`), and an `outcome` of `created`,
`idempotent-replay`, or `conflict`.

### POST /_debug/reset

Clears all state: stored refunds, the identifier counter, and the active
failmode. Responds `200`.

## Local usage

```bash
# Start
PORT=8081 STATE_DIR=./state node server.js

# Run the test suite
node --test
```
