import { test, before, after, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

process.env.STUB_HOLD_MS = '50';
process.env.STATE_DIR = mkdtempSync(join(tmpdir(), 'erp-stub-'));

const { startServer } = await import('../server.js');

let handle;
let base;

before(async () => {
  handle = await startServer({ port: 0 });
  base = `http://127.0.0.1:${handle.port}`;
});

after(async () => {
  await handle.close();
});

beforeEach(async () => {
  await fetch(`${base}/_debug/reset`, { method: 'POST' });
});

function samplePayload(overrides = {}) {
  return {
    refund_no: 'SR-20260824-000123',
    seller_order_id: 'SO-1002',
    tax_mode: 'TAX_EXCLUDED',
    currency: 'JPY',
    lines: [
      {
        sku: 'SELLER-RED-01',
        quantity: 1,
        amount: '1200.0000',
        taxes: [{ code: '010', amount: '120.0000' }],
      },
    ],
    shipping_amount: '0.0000',
    grand_total: '1320.0000',
    ...overrides,
  };
}

async function create(payload, headers = {}) {
  const res = await fetch(`${base}/erp-api/v1/refunds`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...headers },
    body: JSON.stringify(payload),
  });
  const body = await res.json().catch(() => ({}));
  return { res, body };
}

async function debugRefunds() {
  const res = await fetch(`${base}/_debug/refunds`);
  return res.json();
}

async function setFailmode(mode, times) {
  const payload = times === undefined ? { mode } : { mode, times };
  const res = await fetch(`${base}/erp-api/v1/_debug/failmode`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  return res.json();
}

test('health returns ok', async () => {
  const res = await fetch(`${base}/health`);
  assert.equal(res.status, 200);
  assert.deepEqual(await res.json(), { status: 'ok' });
});

test('create returns erp_refund_id and refund-pending', async () => {
  const { res, body } = await create(samplePayload());
  assert.equal(res.status, 200);
  assert.match(body.erp_refund_id, /^ERP-8\d{6}$/);
  assert.equal(body.status, 'refund-pending');
});

test('identical create with same refund_no is idempotent', async () => {
  const first = await create(samplePayload());
  const second = await create(samplePayload());
  assert.equal(second.res.status, 200);
  assert.equal(second.body.erp_refund_id, first.body.erp_refund_id);

  const dbg = await debugRefunds();
  assert.equal(dbg.unique_refunds, 1);
  assert.equal(dbg.attempts_total, 2);
});

test('same refund_no with a different payload returns 409', async () => {
  await create(samplePayload());
  const conflict = await create(samplePayload({ grand_total: '9999.0000' }));
  assert.equal(conflict.res.status, 409);
  assert.equal(conflict.body.error_code, 'REFUND_CONFLICT');

  const dbg = await debugRefunds();
  assert.equal(dbg.unique_refunds, 1);
});

test('empty refund_no creates a distinct record every time', async () => {
  const a = await create(samplePayload({ refund_no: '' }));
  const b = await create(samplePayload({ refund_no: '' }));
  assert.notEqual(a.body.erp_refund_id, b.body.erp_refund_id);

  const dbg = await debugRefunds();
  assert.equal(dbg.unique_refunds, 2);
});

test('rate_limit_429 responds 429 with Retry-After', async () => {
  await setFailmode('rate_limit_429', 1);
  const { res } = await create(samplePayload());
  assert.equal(res.status, 429);
  assert.ok(res.headers.get('retry-after'));

  const dbg = await debugRefunds();
  assert.equal(dbg.unique_refunds, 0);
});

test('server_error_5xx responds 503', async () => {
  await setFailmode('server_error_5xx', 1);
  const { res } = await create(samplePayload());
  assert.equal(res.status, 503);
});

test('business_reject responds 422 with error_code', async () => {
  await setFailmode('business_reject', 1);
  const { res, body } = await create(samplePayload());
  assert.equal(res.status, 422);
  assert.equal(body.error_code, 'REFUND_REJECTED');
});

test('timeout_after_accept persists the refund then holds', async () => {
  await setFailmode('timeout_after_accept', 1);
  const start = Date.now();
  const { res, body } = await create(samplePayload());
  const elapsed = Date.now() - start;
  assert.equal(res.status, 200);
  assert.equal(body.status, 'refund-pending');
  assert.ok(elapsed >= 40, `expected a hold, got ${elapsed}ms`);

  const dbg = await debugRefunds();
  assert.equal(dbg.unique_refunds, 1);
});

test('timeout_before_accept holds and persists nothing', async () => {
  await setFailmode('timeout_before_accept', 1);
  const { res } = await create(samplePayload());
  assert.equal(res.status, 200);

  const dbg = await debugRefunds();
  assert.equal(dbg.unique_refunds, 0);
});

test('delayed_status returns 404 for the first N reads then found', async () => {
  const { body } = await create(samplePayload());
  const id = body.erp_refund_id;

  await setFailmode('delayed_status', 2);

  const r1 = await fetch(`${base}/erp-api/v1/refunds/${id}`);
  assert.equal(r1.status, 404);
  const r2 = await fetch(`${base}/erp-api/v1/refunds/${id}`);
  assert.equal(r2.status, 404);

  const r3 = await fetch(`${base}/erp-api/v1/refunds/${id}`);
  assert.equal(r3.status, 200);
  assert.equal((await r3.json()).status, 'refund-pending');
});

test('confirm is idempotent on erp_refund_id', async () => {
  const { body } = await create(samplePayload());
  const id = body.erp_refund_id;

  const confirmBody = JSON.stringify({
    transaction_number: 'TXN-001',
    transaction_date: '2026-08-25',
  });

  const c1 = await fetch(`${base}/erp-api/v1/refunds/${id}/confirm`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: confirmBody,
  });
  assert.equal(c1.status, 200);
  assert.equal((await c1.json()).status, 'refund-confirmed');

  const c2 = await fetch(`${base}/erp-api/v1/refunds/${id}/confirm`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: confirmBody,
  });
  assert.equal(c2.status, 200);
  assert.equal((await c2.json()).status, 'refund-confirmed');
});

test('already_confirmed makes confirm succeed', async () => {
  const { body } = await create(samplePayload());
  const id = body.erp_refund_id;

  await setFailmode('already_confirmed', 1);
  const res = await fetch(`${base}/erp-api/v1/refunds/${id}/confirm`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ transaction_number: 'TXN-002' }),
  });
  assert.equal(res.status, 200);
  assert.equal((await res.json()).status, 'refund-confirmed');
});

test('reset clears all state', async () => {
  await create(samplePayload());
  await fetch(`${base}/_debug/reset`, { method: 'POST' });
  const dbg = await debugRefunds();
  assert.equal(dbg.unique_refunds, 0);
  assert.equal(dbg.attempts_total, 0);
});

test('X-Request-Id differs across idempotent creates but dedupe collapses them', async () => {
  const first = await create(samplePayload(), { 'X-Request-Id': 'req-aaa' });
  const second = await create(samplePayload(), { 'X-Request-Id': 'req-bbb' });

  assert.equal(first.body.erp_refund_id, second.body.erp_refund_id);

  const dbg = await debugRefunds();
  assert.equal(dbg.unique_refunds, 1);

  const record = dbg.refunds[0];
  const requestIds = record.attempts.map((a) => a.request_id);
  assert.deepEqual(requestIds, ['req-aaa', 'req-bbb']);
});
