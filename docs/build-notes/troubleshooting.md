# Troubleshooting

Setup is meant to be trivial: `cp .env.example .env` then
`docker compose up -d --wait`. If the stack does not become healthy, this page
covers the handful of things that actually cause it. Start with
`bin/assignment-status` - it prints the compose state, the web health JSON, the
stub's recorded-refund count, and the logs of anything unhealthy, and it runs
the OpenSearch preflight automatically when `search` is the problem.

## First: read the health signal

- `bin/assignment-status` - one-shot summary of the whole stack.
- Web health JSON directly: open http://localhost:8080/assignment-health.php.
  It returns HTTP 200 with `{"status":"ok"}` only when every check passes;
  otherwise HTTP 503 with a `checks` object naming what failed (writable
  paths, DB connect, seed-version marker, search alias, stub, boot marker).
- The `assignment-ready` service prints a banner with the local URLs once the
  whole stack answers; `docker compose up -d --wait` returns when it is healthy.

## OpenSearch will not become healthy (`vm.max_map_count`)

OpenSearch needs the Docker VM kernel setting `vm.max_map_count` to be at least
`262144`. This is a setting inside the Docker VM, not on your host. Set it in
the VM, not on the machine running Docker:

- Linux (Docker engine on the host):
  `sudo sysctl -w vm.max_map_count=262144`
- Windows + Docker Desktop (WSL2 backend):
  `wsl -d docker-desktop sysctl -w vm.max_map_count=262144`
- WSL2 distro directly:
  `sudo sysctl -w vm.max_map_count=262144`
- macOS + Docker Desktop:
  `docker run --rm --privileged busybox sysctl -w vm.max_map_count=262144`

This resets when the Docker VM restarts; re-apply it and bring the stack up
again if needed. `docker/opensearch/preflight.sh` reads the current value and
prints the matching remedy; `bin/assignment-status` runs it for you when
`search` is unhealthy.

## Docker has too little memory

The stack fits in roughly 5.2 GB but needs headroom. Give Docker at least
8 GB of RAM (Docker Desktop: Settings > Resources > Memory). With less, the
web or search container can be killed mid-boot and the stack never reaches
healthy. The preflight reports the Docker memory total and flags it if low.

## Port conflicts

The stack publishes two host ports by default: `8080` (storefront + Admin) and
`8081` (ERP stub). If either is already in use, the affected container fails to
publish and the stack will not come up. Change the port in `.env`:

```
WEB_PORT=8080
STUB_PORT=8081
DB_PORT=33306
```

`WEB_PORT` and `STUB_PORT` are the two that matter for a normal run. `DB_PORT`
is only published when you opt in to `build/compose.debug.yaml`; the application
talks to the database over the internal Compose network, so you never need it
for the assignment. The preflight checks both `WEB_PORT` and `STUB_PORT` for
conflicts.

## Stale database volume after an image change

The web health endpoint compares a seed-version marker baked into the image
against the value stored in the database. If you ever pull a newer set of
images on top of an old `db-data` volume, the marker can mismatch and health
reports `seed_version` failing. Recreate the volumes so the fresh seeded
datadir is used:

```
bin/reset-assignment            # tears down this project's volumes and re-ups
```

This is project-scoped: it only removes this stack's containers and volumes and
never touches images or other Compose projects.

## Line endings on Windows

The shell scripts and PHP files must stay LF even though the repo is authored
and often cloned on Windows. `.gitattributes` enforces this (`* text=auto
eol=lf`, with `*.sh`/`*.php`/`*.phtml` pinned to LF and `*.ps1` twins kept
CRLF), so a normal `git clone` produces correct line endings. If scripts fail
with `bad interpreter` or `\r` errors, your Git client has line-ending
conversion forced on - re-clone with the defaults. Clone under a local drive
(not a network share); on Windows, a clone inside the WSL2 filesystem gives the
best bind-mount performance.

## Reading and resetting state

- Health: `bin/assignment-status`, or `curl` http://localhost:8080/assignment-health.php
- Stub state: the stub records every refund it receives; see
  http://localhost:8081/_debug/refunds for the recorded refunds and attempt
  counts (also surfaced by `bin/assignment-status`).
- Re-seed data only (keep the stack up):
  `bin/reset-assignment --data-only` - re-seeds the assignment data and clears
  the stub state.
- Full reset: `bin/reset-assignment` - tears down this project's containers and
  volumes and brings the stack back up clean (add `--yes` to skip the prompt).

## After editing the module

If a change to the module does not take effect, you probably need a flush - see
the edit / rebuild matrix in [`runtime-and-licenses.md`](runtime-and-licenses.md)
and in the root `README.md`. Rule of thumb: PHP is live; templates and XML need
a plain `bin/assignment-cache-flush`; JS/LESS need `--static`; DI/plugin changes
need `--generated`; `db_schema.xml` needs `--upgrade`; and after switching
branches always run `bin/assignment-cache-flush --all`.

## Running the tests

`bin/assignment-test [unit|integration|smoke|all] [--filter X]` runs the
module's bounded suites inside the web container as `www-data`. If a run behaves
oddly right after an edit, flush first (`bin/assignment-cache-flush --all`) and
re-run.
