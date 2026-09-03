# Calibration log

A template for a timed dry-run of the assignment environment, filled in by a
strong Magento engineer on a clean machine before the repository is handed to a
candidate. Nothing here can be measured at authoring time - every value is a
human measurement. Rows are marked `PENDING (human step)` until the dry-run is
done.

Goal: confirm a candidate can go from a fresh clone to a healthy, usable stack
in well under a minute of `up` time, and that the edit loop behaves as the docs
describe.

## Environment

| Field | Value |
|---|---|
| Date of run | PENDING (human step) |
| Operator | PENDING (human step) |
| Host OS / version | PENDING (human step) |
| Docker / Compose version | PENDING (human step) |
| CPU / cores | PENDING (human step) |
| RAM allocated to Docker | PENDING (human step) |
| Disk type (SSD/NVMe) | PENDING (human step) |
| Clone location (local drive / WSL2 / network) | PENDING (human step) |

## 1. Clone

| Step | Measurement | Notes |
|---|---|---|
| `git clone` wall time | PENDING (human step) | |
| Repository size on disk after clone | PENDING (human step) | |

## 2. Image pull

Run `docker logout ghcr.io` first so the pull is anonymous, as a candidate's
would be.

| Step | Measurement | Notes |
|---|---|---|
| `docker compose pull` wall time | PENDING (human step) | |
| Total download size (compressed) | PENDING (human step) | |
| Total image size on disk (uncompressed) | PENDING (human step) | |

## 3. Cold start (`up --wait`)

Fresh volumes (`docker compose down -v` beforehand, or a first-ever `up`).

| Step | Measurement | Notes |
|---|---|---|
| `cp .env.example .env` | PENDING (human step) | trivial |
| `time docker compose up -d --wait` (cold) | PENDING (human step) | target: well under 60s |
| Time to `assignment-ready` banner | PENDING (human step) | |
| First-boot db datadir copy time | PENDING (human step) | few seconds expected |
| First-boot reindex time (if alias missing) | PENDING (human step) | |

## 4. Warm start

Images pulled and volumes already present (`docker compose down` without `-v`,
then `up`).

| Step | Measurement | Notes |
|---|---|---|
| `time docker compose up -d --wait` (warm) | PENDING (human step) | |
| `docker compose restart web` to serving | PENDING (human step) | no reindex expected |

## 5. Health snapshot

Paste the output of `curl -fsS http://localhost:8080/assignment-health.php`
(pretty-printed) once the stack is healthy.

```json
PENDING (human step) - paste the assignment-health.php JSON here
```

| Check | Result |
|---|---|
| HTTP status of `/assignment-health.php` | PENDING (human step) |
| `bin/assignment-status` all healthy | PENDING (human step) |
| Storefront http://localhost:8080 loads | PENDING (human step) |
| Admin http://localhost:8080/admin login works | PENDING (human step) |
| Stub http://localhost:8081/health returns ok | PENDING (human step) |
| Stub http://localhost:8081/_debug/refunds reachable | PENDING (human step) |

## 6. Test suites (`bin/assignment-test`)

| Suite | Result | Wall time | Notes |
|---|---|---|---|
| `bin/assignment-test unit` | PENDING (human step) | PENDING | |
| `bin/assignment-test integration` | PENDING (human step) | PENDING | |
| `bin/assignment-test smoke` | PENDING (human step) | PENDING | |
| `bin/assignment-test all` (exit code) | PENDING (human step) | PENDING | expect exit 0 |
| PowerShell twin (`.ps1`) from a `C:\` clone | PENDING (human step) | PENDING | Windows only |

## 7. Edit-loop checks

Confirm each edit type behaves as the edit/rebuild matrix promises.

| Edit | Flush used | Visible after | Result | Notes |
|---|---|---|---|---|
| PHP logic change | none (developer mode) | immediate | PENDING (human step) | |
| `.phtml` / `etc/*.xml` / email change | `bin/assignment-cache-flush` | after flush | PENDING (human step) | |
| JS / LESS change | `bin/assignment-cache-flush --static` + hard reload | after flush | PENDING (human step) | |
| `di.xml` / constructor / plugin change | `bin/assignment-cache-flush --generated` | after flush | PENDING (human step) | |
| `db_schema.xml` change | `bin/assignment-cache-flush --upgrade` | after flush | PENDING (human step) | |
| Branch switch | `bin/assignment-cache-flush --all` | after flush | PENDING (human step) | store still serves |

## 8. Reset and teardown

| Step | Measurement / Result | Notes |
|---|---|---|
| `bin/reset-assignment --data-only` wall time | PENDING (human step) | |
| `bin/reset-assignment` (full) wall time | PENDING (human step) | |
| `docker compose down -v` leaves no project volumes | PENDING (human step) | other projects untouched |

## 9. Notes and follow-ups

PENDING (human step) - record anything surprising: slow steps, retries needed,
environment-specific fixes, and whether any figure in
[`runtime-and-licenses.md`](runtime-and-licenses.md) should be updated (in
particular the boot-timing TODO).
