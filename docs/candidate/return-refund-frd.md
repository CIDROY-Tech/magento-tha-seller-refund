# Functional Requirements (FRD)

| | |
|---|---|
| **Document** | Seller Return & Refund Flow — Functional Requirements |
| **Module** | `Acme_SellerRefund` (Magento Open Source) |
| **Version** | 1.0 (draft for build) |
| **Status** | Approved for development |
| **Audience** | Engineering, QA, CS Operations, Finance |
| **Platform** | Magento Open Source 2.4.7-p10, PHP 8.3 |
| **Local services** | MariaDB 10.11; OpenSearch 2.12 (ICU + Kuromoji optional); file cache/session |
| **Store configuration** | `ja_JP`, JPY, `Asia/Tokyo` |

> This is the functional specification for the Seller Return & Refund subsystem. It is written to be built against directly. The company, the ERP, all identifiers, and all assignment data are **synthetic**; below production the ERP is served by a **local stub** that mirrors the contract. No live settlement system, vendor connector, or commercial infrastructure is involved.

---

## 1. Purpose and scope

The store lets Customer Support (CS) operators issue full or partial refunds against delivered seller orders, register the offline cash refund performed by Finance, and confirm the settled refund back to the company **ERP** (the seller-settlement system of record). This document covers the refund lifecycle, the data model, the three ERP Refund API interactions, the operator screens, tax handling, and the presentation of refunded amounts on customer-facing surfaces.

**The business story.** The store registers the refund in the ERP as a *pending credit note* against the seller's ledger. Finance then pays the customer offline by bank transfer, on their own schedule. When the transfer is done, the operator registers the payment (bank transaction number and date), and the store confirms the settlement to the ERP so the credit note closes. This read-before-confirm shape exists because the credit note must be seen to be pending on the ERP side before the store may close it.

Out of scope: the original order and fulfilment flow, first-party (non-seller) returns handled by the legacy path, exchange orders, and Finance's internal bank-transfer tooling. The cash transfer itself happens outside this system; we record its completion.

---

## 2. Definitions

- **ERP** — the company's seller-settlement system of record. Reached only through the **ERP Refund API** connector; in all environments below production it is a local stub that mirrors the contract.
- **Seller order** — an order, or the portion of an order, fulfilled by a seller. An order may contain both seller and first-party (core) items ("mixed order").
- **ERP Refund API** — the ERP's outbound HTTP API. This subsystem uses three operations: **Create Refund**, **Read Refund Status**, and **Confirm Refund** (§9).
- **Refund request (`mp_refund`)** — one CS-initiated refund against one seller order.
- **Credit note** — the pending settlement entry the store opens in the ERP against the seller's ledger; it closes when the store confirms the settled refund.
- **Cash refund** — the offline bank transfer performed by Finance to the customer. The system does not move money; it records that Finance has done so.
- **`refund_no`** — the human-readable refund number shown to operators and printed on the reissued receipt (§10), and the idempotency key for the Create Refund call (§9). Format `SR-YYYYMMDD-NNNNNN`, allocated in date sequence at the moment the refund record is created.
- **`erp_refund_id`** — the identifier the ERP returns for the credit note on Create Refund; the idempotency key for Confirm Refund (§9).

---

## 3. Business flow (narrative)

A delivered seller order develops a problem — a defect, a shortage, a return. A CS operator opens the order in the Admin, chooses **Refund** (available under the conditions in §8), and builds the refund: full order, or specific lines at specific quantities. The system calculates the refundable amounts, including consumption tax (§11), and the operator submits.

On submit, the refund record is committed to the database, and only then is the ERP notified via **Create Refund** (§9). The ERP opens the credit note as *refund-pending* and returns its `erp_refund_id`. The order now waits on Finance, who perform the bank transfer offline on their own schedule. When Finance confirm the transfer is done, the operator returns to the refund, enters the bank transaction number and date, and registers the cash refund as complete. The store then confirms the settlement to the ERP — first reading that the ERP still shows the credit note *refund-pending* (**Read Refund Status**), then confirming (**Confirm Refund**) so the credit note closes — and the refund reaches its terminal state.

The refunded amounts must then be visible to the customer: on a reissued receipt, on the order-details screen, in order history, and in the confirmation email, each showing the original pre-refund figures together with a clearly separated refund section (§10), and the same figures flow to the downstream core system.

---

## 4. Actors and operational flow

| Actor | Responsibility |
|---|---|
| **CS operator** (refund role) | Creates the refund, registers cash-refund completion, triggers the settlement confirmation. |
| **Finance** | Performs the offline bank transfer; supplies the transaction number and date. Not a system user. |
| **ERP** | Records the pending and confirmed credit-note states; returns `erp_refund_id`. |
| **Downstream core system** | Receives the canonical refund record and amounts through the standard order export. |

The operator drives the flow by hand, refund by refund. After Finance completes a transfer, the operator opens that specific refund and presses **Refund Complete**, which registers the cash refund and, **for that one refund, calls Read Refund Status on demand** to check the ERP still shows the credit note pending, before confirming. There is no unattended step between Create Refund and Confirm Refund: the confirmation cannot run until a human has registered the offline transfer, so the status read is initiated per refund by the operator, on demand, at the moment they work that refund.

---

## 5. Permissions

The Refund action, the refund worklist, and the Refund Complete action are available only to operators holding the refund role. All other Admin users see the order but not the refund controls. Magento ACL resources separately control view, create, cash-register, retry, and audit-log access.

---

## 6. Data model

**`mp_refund`** — one row per refund request.

| Column | Notes |
|---|---|
| `entity_id` | Primary key. |
| `refund_no` | Display number and Create Refund idempotency key. Allocated in date sequence at record creation, format `SR-YYYYMMDD-NNNNNN` (§2). Unique. |
| `order_id`, `seller_order_id` | Links to the platform order and the seller order. |
| `refund_type` | `full` or `partial`, derived from the selected quantities. |
| `reason_code` | Refund reason (see §13 on granularity). |
| `status` | Lifecycle main status (§7). |
| `subtotal_amount`, `shipping_amount`, `tax_amount`, `grand_total`, `currency_code` | Money snapshot. `grand_total` is auto-calculated and read-only (§8). |
| `erp_refund_id` | Identifier returned by the ERP on Create Refund; idempotency key for Confirm Refund. |
| `create_status`, `status_check_status`, `confirm_status`, `cash_refund_status` | Independent sub-status fields (§7). |
| `transaction_number`, `transaction_date` | Bank transfer reference and date, supplied by Finance at cash-refund registration. |
| `version` | Optimistic-concurrency column. |
| audit columns | `created_at`, `created_by`, `updated_at`, `updated_by`. |

**`mp_refund_item`** — one immutable snapshot per refunded line: `refund_id`, `order_item_id`, `sku`, `product_name`, `qty_ordered`, `qty_refunded_before`, `qty_refund`, `qty_refundable_after`, `unit_price`, `row_amount`, `shipping_amount`, `tax_rate`, `tax_code`, `tax_amount`, `grand_total`, timestamps.

**`mp_refund_event`** — append-only audit log: `refund_id`, `event_type`, `api_code`, `event_status`, `status_from`, `status_to`, redacted `request_payload`, redacted `response_payload`, `error_code`, `attempt_no`, `created_at`, `created_by`. Events are never updated or deleted through the application.

Money is stored as `decimal(20,4)`. The store currency is JPY.

---

## 7. Status lifecycle

The main `status` column moves through:

```
calculated → cash_refund_pending → cash_refunded → erp_confirm_pending → erp_confirmed
```

with two off-path terminal states: **`failed`** (an unrecoverable *business* rejection that requires a new business decision) and **`cancelled`** (a voided refund). A `draft` value is reserved for a future auto-save feature and is unused in this version.

Transient ERP Refund API errors never change the main `status`. A failed or timed-out Create Refund, Read Refund Status, or Confirm Refund is recorded in the corresponding sub-status field (`create_status`, `status_check_status`, `confirm_status`) and left for retry; the main status holds at its current value. **In particular, a Create Refund that fails leaves the record at `calculated`**, ready to retry, with no data loss.

Sub-status fields (`create_status`, `status_check_status`, `confirm_status`, `cash_refund_status`) store operation-specific progress such as `not_started`, `pending`, `succeeded`, `retryable_error`, or `business_rejected`. HTTP 429, timeouts, and retriable 5xx responses update a sub-status and write an audit event; they do not set the main lifecycle to `failed`.

---

## 8. Business rules

- **BR-01 — Eligibility.** Refund is available only for an order with at least one seller item, within the refund window, with a positive remaining refundable amount, for an operator holding the refund role. A mixed order is eligible, but only seller lines may be selected; core lines must not affect seller refund quantities or amounts.
- **BR-02 — Refund window.** A refund may be created within **14 calendar days of the delivery date**. After that the Refund action is disabled.
- **BR-03 — Per-line quantity guard.** For each line, `qty_refund` must not exceed `qty_ordered − qty_refunded_before`. The remaining refundable quantity is `qty_refundable_after = qty_ordered − qty_refunded_before − qty_refund`. The server re-checks quantities at submission; browser-side validation is advisory.
- **BR-04 — Grand total.** `grand_total` is auto-calculated from the line amounts, shipping, and tax, and is read-only on the screen. It is **fully determined** by the operator's line selections; no operator may edit any money field.
- **BR-05 — One active refund per order.** The system must not allow two refunds to be created against the same order simultaneously, and a browser that repeats a submission after a timeout must not create a second financial action.
- **BR-06 — Commit before notify.** The refund record is committed to the database before Create Refund is called. A Create Refund failure must never leave the ERP notified while the database has no record, nor the reverse without a retry path.
- **BR-07 — Idempotent create.** Create Refund uses `refund_no` as its idempotency key; a retry of a create that already succeeded must not open a second credit note in the ERP.
- **BR-08 — Idempotent confirm.** Confirm Refund uses `erp_refund_id` as its idempotency key. An already-confirmed refund is treated as success when the identifiers and transaction reference match.
- **BR-09 — Irreversibility.** Once a refund is submitted it is final: it cannot be edited, cancelled, or deleted by an operator. Corrections are handled by Finance out of band.
- **BR-10 — Cash refund gates confirmation.** Confirm Refund may not run until the cash refund has been registered (`cash_refund_status = succeeded`) with a transaction number and date.
- **BR-11 — Receipt availability.** The reissued receipt download is enabled once the refund record exists, so CS can hand the customer the corrected receipt promptly.

> **Contract note (procurement).** The signed seller agreement sets the eligible return period at **7 days from the date the customer receives the goods**. Eligibility is enforced from the delivery record, and Customer Support must not be able to override the contractual window.

---

## 9. ERP Refund API contract

All three operations are outbound from the store to the ERP. In every environment below production they are served by the local stub. Each request and response is recorded in `mp_refund_event` with sensitive values redacted; connect and response timeouts are bounded; rate limits, transient failures, and business rejections must be distinguishable.

### 9.1 Create Refund

Endpoint: `POST /erp-api/v1/refunds`. Called immediately after the refund record is committed (BR-06). Opens the credit note in the ERP; the ERP sets it to *refund-pending* and returns an `erp_refund_id`. **Idempotency key: `refund_no`.** The payload declares `tax_mode = TAX_EXCLUDED` and carries a per-line `taxes` array (§11).

Example request:

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
    },
    {
      "sku": "SELLER-BLU-02",
      "quantity": 2,
      "amount": "1600.0000",
      "taxes": [{ "code": "008", "amount": "128.0000" }]
    }
  ],
  "shipping_amount": "0.0000",
  "grand_total": "3048.0000"
}
```

Example response:

```json
{
  "erp_refund_id": "ERP-8000123",
  "status": "refund-pending"
}
```

### 9.2 Read Refund Status

Endpoint: `GET /erp-api/v1/refunds/{erp_refund_id}`. Confirms the ERP still shows the credit note *refund-pending* before confirmation. **Runs as a scheduled batch** that sweeps all records in `cash_refunded` on a fixed interval, reads each one's ERP state, and advances each to `erp_confirm_pending` once the ERP confirms the pending state. The sweep uses bounded batches and does not read already-confirmed or terminal records. Returns *found*, *not-found*, or *error*; a not-found immediately after a successful Create Refund is treated as eventually consistent and retried within the configured bound.

### 9.3 Confirm Refund

Endpoint: `POST /erp-api/v1/refunds/{erp_refund_id}/confirm`. Closes the credit note (sets it *refund-confirmed*). Requires the cash refund registered first (BR-10) and carries the bank `transaction_number` and `transaction_date`. **Idempotency key: `erp_refund_id`.** A successful response sets `confirm_status = succeeded` and the main state to `erp_confirmed`.

### 9.4 Refund completion to the seller ledger

After the cash refund is registered, the completion is reported to the seller ledger through the **Settlement Adjustment Export** — a batch that exports the order adjustment and its per-line adjustment records to the seller's ledger — and this export is the authoritative record that the refund has settled. The refund service publishes the cash-refund data to that export and treats its acknowledgement as the point at which the refund is fully settled against the seller. The `erp_refund_id` ties the adjustment records back to the original refund.

### 9.5 The `refund_no` allocation

The ERP allocates the refund number on its side and returns it with the Create Refund response; the store persists the returned value into `refund_no` so both systems display the same number, and subsequent retries use the returned value. Reset and formatting rules for that number are owned by the ERP.

### 9.6 Failure behaviour (all operations)

A timeout or 5xx is transient: record it in the sub-status field, hold the main status, retry later honouring the idempotency key. A 429 is a rate-limit signal and should be retried after a backoff. Only a definitive business rejection is terminal. A retry must never open a second credit note. Operators with retry permission can replay a failed operation without editing the financial snapshot.

---

## 10. Screens and presentation

### 10.1 Refund entry screen

The operator selects seller lines and quantities; the screen shows per-line ordered/previously-refunded/remaining quantities, unit price, tax rate, requested quantity, per-line and total refundable amounts, and the read-only `grand_total`. Calculation is server-side whenever a quantity changes and again on submit. On submit, the screen reflects the outcome of the create.

### 10.2 Refund worklist

Lists refunds and their statuses for operators to work through; the primary daily working surface. Supports the cash-registration and confirmation steps and surfaces integration state.

### 10.3 Refund Complete action

Registers the cash refund (transaction number and date) and triggers the settlement confirmation for that refund.

### 10.4 Presentation of refunded amounts

Wherever a refunded order's amounts are shown, the surface must display the **original pre-refund amount** together with a **distinct refund section** beneath it, derived from the same stored refund snapshot used by integration and downstream sync. For a partial refund this section carries a partial-refund label, the pre-refund total, and a separate refund breakdown (refund date, product subtotal, shipping, ex-tax amount, consumption tax, and total); the pre-refund detail above it is left unchanged. These figures appear on the five presentation surfaces:

- the **reissued receipt** — a downloadable PDF that doubles as the qualified tax invoice,
- the **order-details screen** in the customer account,
- **order history** (the refunded order remains visible with the refunded item marked; it is not removed),
- the **refund confirmation email** (full breakdown after ERP confirmation), and
- the **downstream core-system export** — the canonical refund record carried to the core system through the standard order sync.

---

## 11. Tax handling

Consumption tax is calculated **store-side** at the **original order's tax rate** and included in the refund. The ERP stores tax-exclusive base amounts, so the store computes `tax_amount` per line as `(unit_price × qty_refund + line_shipping) × tax_rate` and includes the resulting figures in the Create Refund payload; the ERP does not recompute tax.

The tax rate is driven by a product attribute, `mp_tax_class`, whose admin option **values** are the **business tax codes** — `999` (tax-exempt), `010` (10%), `008` (8% reduced rate). These business codes are what the ERP expects in the Create Refund `taxes` array; they are stable identifiers shared between the two systems. **The store must send the admin option value** (the `store_id = 0` value — i.e. the business code `999`/`010`/`008`), and, on any inbound sync, convert a received business code back to the corresponding local attribute option.

The Create Refund payload declares `tax_mode = TAX_EXCLUDED` and carries the computed `tax_amount` alongside the ex-tax `row_amount`, so the ERP can reconstruct the tax-inclusive figure the customer was charged.

**Shipping on partial refunds.** A full refund includes the remaining refundable shipping allocated to seller items. No calculation may refund more shipping than the seller allocation on the original order.

---

## 12. Non-functional requirements

### 12.1 Scale assumptions

- ~250,000 orders per month.
- ~5,000 refunds per month, with a campaign peak of **25 submissions per minute**.
- **150** Customer Support users, with up to **40 concurrent** operators on the refund worklist.
- **20,000** refund records considered by the daily settlement scan.

The refund worklist must return its **first page within 1.5 seconds** at the stated volume. Interactive requests must not wait through unbounded external retries. Both the worklist and the settlement scan read across orders and lines and must stay responsive as volume grows.

### 12.2 Integrity and recovery

- A confirmed financial result cannot be silently changed. A local refund and its ERP-side credit note must remain traceable to one another.
- Simultaneous or repeated submissions cannot exceed the remaining refundable amount or create duplicate financial actions (BR-05).
- External failure must not lose a committed refund. Retriable operations require visible attempt history, bounded automated retry, and an authorised replay path. Recovery must not require database editing.

### 12.3 Concurrency

BR-05 must hold under concurrent operators. The refund quantity math reads `qty_refunded_before` from the current line state at calculation time. The record carries a `version` column.

### 12.4 Auditability and security

Every state change and every API call is written to `mp_refund_event` with its request and response payloads. All privileged actions and lifecycle transitions are attributable to an actor. Stored payloads redact credentials, tokens, bank details beyond the required transaction reference, and customer personal data not needed for diagnosis.

---

## 13. Reason codes

A refund carries a `reason_code` from a controlled list (defect, shortage, customer return, price adjustment, other).

---

## 14. Admin user experience

- Submission requires an explicit confirmation step showing the calculated total.
- The submit action remains disabled while a submission request is in flight; repeated operator clicks or network retries must not create a second financial action.
- The UI must clearly distinguish four things: local record creation, ERP acceptance of the credit note, cash completion, and final ERP confirmation.
- A Create Refund timeout is displayed as `Saved; ERP notification pending retry`, not as success or permanent failure.
- Refresh must fetch the current server state. The browser must not invent a lifecycle transition or show a confirmed state ahead of the server.
- Integration errors show a safe summary and a correlation identifier; raw payloads are restricted to audit-authorised users.

---

*End of functional requirements.*
