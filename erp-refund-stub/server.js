import { createServer } from 'node:http';
import { createHash } from 'node:crypto';
import {
  mkdirSync,
  accessSync,
  constants,
  existsSync,
  readFileSync,
  writeFileSync,
  renameSync,
} from 'node:fs';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { dirname, join } from 'node:path';

const BUSINESS_PREFIX = '/erp-api/v1';

const KNOWN_MODES = new Set([
  'ok',
  'timeout_before_accept',
  'timeout_after_accept',
  'rate_limit_429',
  'server_error_5xx',
  'business_reject',
  'delayed_status',
  'already_confirmed',
]);

// Which operations a failmode alters. `times` counts down once per matching
// operation; when it reaches zero the mode reverts to `ok`.
function modeMatches(mode, op) {
  switch (mode) {
    case 'timeout_before_accept':
    case 'timeout_after_accept':
    case 'business_reject':
      return op === 'create';
    case 'rate_limit_429':
    case 'server_error_5xx':
      return op === 'create' || op === 'read' || op === 'confirm';
    case 'delayed_status':
      return op === 'read';
    case 'already_confirmed':
      return op === 'confirm';
    default:
      return false;
  }
}

function freshState() {
  return {
    counter: 0,
    refunds: {},
    byRefundNo: {},
    failmode: { mode: 'ok', times: null },
  };
}

function resolveStateDir() {
  const preferred = process.env.STATE_DIR || '/data';
  try {
    mkdirSync(preferred, { recursive: true });
    accessSync(preferred, constants.W_OK);
    return preferred;
  } catch {
    const fallback = join(dirname(fileURLToPath(import.meta.url)), 'state');
    mkdirSync(fallback, { recursive: true });
    return fallback;
  }
}

function holdMs() {
  const value = Number(process.env.STUB_HOLD_MS);
  return Number.isFinite(value) && value > 0 ? value : 6000;
}

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function stableStringify(value) {
  if (value === null || typeof value !== 'object') {
    return JSON.stringify(value) ?? 'null';
  }
  if (Array.isArray(value)) {
    return '[' + value.map(stableStringify).join(',') + ']';
  }
  const keys = Object.keys(value).sort();
  return (
    '{' +
    keys.map((k) => JSON.stringify(k) + ':' + stableStringify(value[k])).join(',') +
    '}'
  );
}

function hashPayload(body) {
  return createHash('sha256').update(stableStringify(body)).digest('hex');
}

function formatErpId(counter) {
  return 'ERP-8' + String(counter).padStart(6, '0');
}

export async function startServer(options = {}) {
  const stateDir = resolveStateDir();
  const stateFile = join(stateDir, 'refunds.json');

  let state;
  if (existsSync(stateFile)) {
    try {
      state = { ...freshState(), ...JSON.parse(readFileSync(stateFile, 'utf8')) };
    } catch {
      state = freshState();
    }
  } else {
    state = freshState();
  }

  function persist() {
    const tmp = stateFile + '.tmp';
    writeFileSync(tmp, JSON.stringify(state));
    renameSync(tmp, stateFile);
  }

  // Applies the active failmode countdown for one operation and returns the
  // mode to enact, or null when normal behaviour should proceed.
  function takeFailmode(op) {
    const fm = state.failmode;
    if (!fm || fm.mode === 'ok' || !modeMatches(fm.mode, op)) {
      return null;
    }
    const mode = fm.mode;
    if (typeof fm.times === 'number') {
      fm.times -= 1;
      if (fm.times <= 0) {
        state.failmode = { mode: 'ok', times: null };
      }
    }
    return mode;
  }

  function uniqueRefunds() {
    return Object.keys(state.refunds).length;
  }

  function attemptsTotal() {
    return Object.values(state.refunds).reduce((sum, r) => sum + r.attempts.length, 0);
  }

  function createRecord(body, requestId) {
    const refundNo =
      typeof body.refund_no === 'string' ? body.refund_no.trim() : '';
    const hash = hashPayload(body);
    const now = new Date().toISOString();

    if (refundNo) {
      const existingId = state.byRefundNo[refundNo];
      if (existingId) {
        const rec = state.refunds[existingId];
        if (rec.payload_hash === hash) {
          rec.attempts.push({
            request_id: requestId,
            received_at: now,
            outcome: 'idempotent-replay',
          });
          persist();
          return {
            status: 200,
            body: { erp_refund_id: rec.erp_refund_id, status: rec.status },
          };
        }
        rec.attempts.push({
          request_id: requestId,
          received_at: now,
          outcome: 'conflict',
        });
        persist();
        return {
          status: 409,
          body: {
            error_code: 'REFUND_CONFLICT',
            message:
              'A refund with this refund_no already exists with a different payload.',
          },
        };
      }
    }

    state.counter += 1;
    const erpId = formatErpId(state.counter);
    const rec = {
      erp_refund_id: erpId,
      refund_no: refundNo || null,
      payload_hash: hash,
      status: 'refund-pending',
      transaction_number: null,
      transaction_date: null,
      attempts: [
        { request_id: requestId, received_at: now, outcome: 'created' },
      ],
    };
    state.refunds[erpId] = rec;
    if (refundNo) {
      state.byRefundNo[refundNo] = erpId;
    }
    persist();
    return {
      status: 200,
      body: { erp_refund_id: erpId, status: 'refund-pending' },
    };
  }

  async function handleCreate(res, body, requestId) {
    const mode = takeFailmode('create');

    if (mode === 'rate_limit_429') {
      persist();
      return sendJson(
        res,
        429,
        { error_code: 'RATE_LIMITED', message: 'Too many requests.' },
        { 'Retry-After': '1' }
      );
    }
    if (mode === 'server_error_5xx') {
      persist();
      return sendJson(res, 503, {
        error_code: 'SERVER_ERROR',
        message: 'Temporary server error.',
      });
    }
    if (mode === 'business_reject') {
      persist();
      return sendJson(res, 422, {
        error_code: 'REFUND_REJECTED',
        message: 'The refund was rejected.',
      });
    }
    if (mode === 'timeout_before_accept') {
      persist();
      await sleep(holdMs());
      // Nothing is persisted; the client has already given up by now.
      return sendJson(res, 200, {
        erp_refund_id: formatErpId(state.counter + 1),
        status: 'refund-pending',
      });
    }

    const result = createRecord(body, requestId);
    if (mode === 'timeout_after_accept') {
      await sleep(holdMs());
    }
    return sendJson(res, result.status, result.body, result.headers);
  }

  function handleRead(res, erpId) {
    const mode = takeFailmode('read');

    if (mode === 'server_error_5xx') {
      persist();
      return sendJson(res, 503, {
        error_code: 'SERVER_ERROR',
        message: 'Temporary server error.',
      });
    }
    if (mode === 'rate_limit_429') {
      persist();
      return sendJson(
        res,
        429,
        { error_code: 'RATE_LIMITED', message: 'Too many requests.' },
        { 'Retry-After': '1' }
      );
    }
    if (mode === 'delayed_status') {
      persist();
      return sendJson(res, 404, {
        error_code: 'NOT_FOUND',
        message: 'Refund not found yet.',
      });
    }

    const rec = state.refunds[erpId];
    if (!rec) {
      return sendJson(res, 404, {
        error_code: 'NOT_FOUND',
        message: 'Refund not found.',
      });
    }
    return sendJson(res, 200, {
      erp_refund_id: rec.erp_refund_id,
      status: rec.status,
    });
  }

  function handleConfirm(res, erpId, body) {
    const mode = takeFailmode('confirm');

    if (mode === 'server_error_5xx') {
      persist();
      return sendJson(res, 503, {
        error_code: 'SERVER_ERROR',
        message: 'Temporary server error.',
      });
    }
    if (mode === 'rate_limit_429') {
      persist();
      return sendJson(
        res,
        429,
        { error_code: 'RATE_LIMITED', message: 'Too many requests.' },
        { 'Retry-After': '1' }
      );
    }
    if (mode === 'already_confirmed') {
      const rec = state.refunds[erpId];
      if (rec) {
        rec.status = 'refund-confirmed';
        persist();
      } else {
        persist();
      }
      return sendJson(res, 200, {
        erp_refund_id: erpId,
        status: 'refund-confirmed',
      });
    }

    const rec = state.refunds[erpId];
    if (!rec) {
      return sendJson(res, 404, {
        error_code: 'NOT_FOUND',
        message: 'Refund not found.',
      });
    }

    const txn = typeof body.transaction_number === 'string' ? body.transaction_number : null;

    if (rec.status === 'refund-confirmed') {
      if (!txn || rec.transaction_number === txn) {
        return sendJson(res, 200, {
          erp_refund_id: rec.erp_refund_id,
          status: rec.status,
        });
      }
      return sendJson(res, 409, {
        error_code: 'CONFIRM_CONFLICT',
        message:
          'This refund is already confirmed with a different transaction reference.',
      });
    }

    rec.status = 'refund-confirmed';
    rec.transaction_number = txn;
    rec.transaction_date =
      typeof body.transaction_date === 'string' ? body.transaction_date : null;
    persist();
    return sendJson(res, 200, {
      erp_refund_id: rec.erp_refund_id,
      status: rec.status,
    });
  }

  function handleFailmode(res, body) {
    const mode = body.mode;
    if (!KNOWN_MODES.has(mode)) {
      return sendJson(res, 400, {
        error_code: 'BAD_MODE',
        message: 'Unknown failmode.',
      });
    }
    const times = typeof body.times === 'number' ? body.times : null;
    state.failmode = { mode, times };
    persist();
    return sendJson(res, 200, { mode, times });
  }

  function handleDebugRefunds(res) {
    const refunds = Object.values(state.refunds).map((r) => ({
      erp_refund_id: r.erp_refund_id,
      refund_no: r.refund_no,
      status: r.status,
      attempts: r.attempts.map((a) => ({
        request_id: a.request_id,
        received_at: a.received_at,
        outcome: a.outcome,
      })),
    }));
    return sendJson(res, 200, {
      unique_refunds: uniqueRefunds(),
      attempts_total: attemptsTotal(),
      refunds,
    });
  }

  function handleReset(res) {
    state = freshState();
    persist();
    return sendJson(res, 200, { status: 'reset' });
  }

  const server = createServer(async (req, res) => {
    try {
      const url = new URL(req.url, 'http://localhost');
      const parts = url.pathname.split('/').filter(Boolean);
      const method = req.method;
      const requestId = req.headers['x-request-id'] || null;

      if (method === 'GET' && parts.length === 1 && parts[0] === 'health') {
        return sendJson(res, 200, { status: 'ok' });
      }

      if (
        method === 'GET' &&
        parts.length === 2 &&
        parts[0] === '_debug' &&
        parts[1] === 'refunds'
      ) {
        return handleDebugRefunds(res);
      }

      if (
        method === 'POST' &&
        parts.length === 2 &&
        parts[0] === '_debug' &&
        parts[1] === 'reset'
      ) {
        return handleReset(res);
      }

      if (
        method === 'POST' &&
        parts.length === 4 &&
        parts[0] === 'erp-api' &&
        parts[1] === 'v1' &&
        parts[2] === '_debug' &&
        parts[3] === 'failmode'
      ) {
        const body = await readBody(req);
        return handleFailmode(res, body);
      }

      if (
        parts.length >= 3 &&
        parts[0] === 'erp-api' &&
        parts[1] === 'v1' &&
        parts[2] === 'refunds'
      ) {
        if (method === 'POST' && parts.length === 3) {
          const body = await readBody(req);
          return await handleCreate(res, body, requestId);
        }
        if (method === 'GET' && parts.length === 4) {
          return handleRead(res, decodeURIComponent(parts[3]));
        }
        if (method === 'POST' && parts.length === 5 && parts[4] === 'confirm') {
          const body = await readBody(req);
          return handleConfirm(res, decodeURIComponent(parts[3]), body);
        }
      }

      return sendJson(res, 404, {
        error_code: 'NOT_FOUND',
        message: 'Unknown endpoint.',
      });
    } catch (err) {
      return sendJson(res, 400, {
        error_code: 'BAD_REQUEST',
        message: 'Malformed request.',
      });
    }
  });

  const port = options.port ?? (Number(process.env.PORT) || 8081);
  await new Promise((resolve) => server.listen(port, resolve));

  return {
    server,
    port: server.address().port,
    stateDir,
    close: () => new Promise((resolve) => server.close(resolve)),
  };
}

function readBody(req) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    req.on('data', (chunk) => chunks.push(chunk));
    req.on('end', () => {
      const raw = Buffer.concat(chunks).toString('utf8').trim();
      if (!raw) {
        resolve({});
        return;
      }
      try {
        resolve(JSON.parse(raw));
      } catch (err) {
        reject(err);
      }
    });
    req.on('error', reject);
  });
}

function sendJson(res, status, body, extraHeaders = {}) {
  const payload = JSON.stringify(body);
  res.writeHead(status, {
    'Content-Type': 'application/json',
    'Content-Length': Buffer.byteLength(payload),
    ...extraHeaders,
  });
  res.end(payload);
}

if (
  process.argv[1] &&
  import.meta.url === pathToFileURL(process.argv[1]).href
) {
  startServer().then((handle) => {
    // eslint-disable-next-line no-console
    console.log('ERP Refund API stub listening on port ' + handle.port);
  });
}
