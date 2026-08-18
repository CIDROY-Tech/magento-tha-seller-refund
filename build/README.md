# Build pipeline

This directory produces the four images the assignment runs on. Candidates
never touch it - `docker compose up` pulls pre-built, digest-pinned images. It
is here so the images are reproducible and auditable.

Images:

| Image | Base | Purpose |
|---|---|---|
| `seller-refund-web` | `php:8.3-fpm-bookworm` | Magento + nginx + php-fpm + cron (one container) |
| `seller-refund-db` | `mariadb:10.11` | MariaDB with the seeded datadir baked in |
| `seller-refund-search` | `opensearchproject/opensearch:2.12.0` | OpenSearch with ICU + Kuromoji, trimmed plugins |
| `seller-refund-erp-stub` | `node:20-alpine` | ERP Refund API stub |

## Two-pass bake

`bake.sh` runs both passes:

1. **Pass 1 (install/seed/compile/warm).** Resolves the base-image digests,
   builds the search and stub images and a throwaway `installer` web image
   (which has Composer), brings up a temporary stack (stock MariaDB + search +
   stub + installer web), and runs `bake-inside.sh` inside `web`. That installs
   Magento from the Mage-OS mirror, applies store config, enables and upgrades
   the module, seeds the assignment data, compiles DI, deploys static content,
   reindexes, warms caches, and runs the module suites. It then exports:
   - `build/artifacts/web/web-artifacts.tgz` - warmed `env.php`, `config.php`,
     `generated`, `pub/static`, caches, and `pub/media`;
   - `build/artifacts/db/seed.sql.gz` - the database dump.
2. **Pass 2 (ship).** Builds the shippable `runtime` web image (no Composer;
   unpacks the artefacts) and the `db` image (imports the dump into a datadir at
   build time so first boot is a fast copy), verifies the whole stack locally
   with `compose.yaml` + `compose.local.yaml`, then - with `--push` - pushes all
   four images to GHCR and pins their digests into `compose.yaml`.

```bash
# Local only (no push):
build/bake.sh r1

# Build, verify, push to GHCR, and pin digests:
build/bake.sh r1 --push
```

`REGISTRY` overrides the default org (`ghcr.io/cidroy-tech`).

### Seed dump placeholder

`build/artifacts/db/seed.sql.gz` is produced by pass 1 and is git-ignored. The
db `Dockerfile` `COPY`s it, so it must exist before the db image is built - pass
1 always writes it before pass 2 builds the db image. There is a documented
fallback if the pre-baked datadir path ever fails: place the dump in the
container's `/docker-entrypoint-initdb.d` and let the stock MariaDB entrypoint
import it on first boot (slower: +40-90s per fresh volume, versus a few seconds
for the baked-datadir copy).

## Publishing (one-time, per release)

1. Grant the token package scope once:
   `gh auth refresh -h github.com -s write:packages,read:packages`
2. Log Docker in to GHCR:
   `gh auth token | docker login ghcr.io -u <user> --password-stdin`
3. `build/bake.sh <rN> --push`
4. In the GitHub UI, set each of the four packages to **Public**
   (`github.com/orgs/CIDROY-Tech/packages/container/<name>/settings`); public
   visibility cannot be reverted.
5. Verify anonymously: `docker logout ghcr.io && docker compose pull`.

CI can do the same via `.github/workflows/bake.yml` (`workflow_dispatch`).

### `BAKE=local` fallback

If GHCR publishing is unavailable, build locally and run against the local tags
without pinning digests:

```bash
build/bake.sh r1
WEB_IMAGE=seller-refund-web:local \
DB_IMAGE=seller-refund-db:local \
SEARCH_IMAGE=seller-refund-search:local \
STUB_IMAGE=seller-refund-erp-stub:local \
  docker compose -f compose.yaml -f build/compose.local.yaml up -d --wait
```

## Magento source and auth

Magento is installed from the public **Mage-OS mirror**
(`https://mirror.mage-os.org/`), which serves `2.4.7-p10` with no credentials,
so no `auth.json` is needed. `auth.json.example` documents the optional
alternative of installing from `repo.magento.com` with your own keys.

## Files

| File | Role |
|---|---|
| `Dockerfile` | Multi-stage web image: `base`, `vendor`, `installer`, `runtime` |
| `bake.sh` | Two-pass bake driver |
| `bake-inside.sh` | Install/seed/compile/warm/test, run inside the installer web container |
| `compose.bake.yaml` | Pass-1 temporary stack |
| `compose.local.yaml` | Pass-2 local verification overlay (local tags) |
| `compose.debug.yaml` | Optional: publish the DB port to the host |
| `pin-digests.sh` | Resolve published digests and pin them into `compose.yaml` |
| `auth.json.example` | Optional repo.magento.com credentials template |
