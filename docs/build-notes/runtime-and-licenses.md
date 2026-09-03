# Runtime provenance and licenses

This document records exactly what the assignment runtime is built from: the
components and their versions, the source each is pulled from, the image
digests shipped to candidates, and the license each component is used under. It
is here so the environment is auditable and reproducible; candidates do not
need to read it to do the assignment.

## The stack

`docker compose up` starts five services (four images). Everything is
pre-baked - no install, Composer, or build step runs on the candidate's
machine.

| Service | Image | Role |
|---|---|---|
| `web` | `seller-refund-web` | Magento Open Source on `php:8.3-fpm` with nginx, php-fpm, and a supervisord-driven `cron:run` loop (every 60s, as `www-data`), all in one container. Serves the storefront, the Admin, and the `/assignment-health.php` endpoint. |
| `db` | `seller-refund-db` | MariaDB 10.11 with the seeded datadir pre-baked at build time. On first boot a wrapper entrypoint copies the baked datadir into the volume (a few seconds), then hands off to the stock MariaDB entrypoint. |
| `search` | `seller-refund-search` | OpenSearch 2.12.0 with the `analysis-icu` and `analysis-kuromoji` plugins added and the bundled security/analytics/ML plugins removed. With the security plugin absent, the image disables transport security itself - no admin password or security env var is set. |
| `erp-refund-stub` | `seller-refund-erp-stub` | The local ERP Refund API stub (Node.js 20). Serves the three ERP contract endpoints and their failure modes, plus `/_debug/*` inspection routes. |
| `assignment-ready` | `seller-refund-web` (reused) | A readiness gate: it reuses the web image but does no serving. It polls every service, prints the ready banner with the local URLs, and touches a marker file its own healthcheck watches. `docker compose up -d --wait` returns once this service is healthy. |

## Versions

| Component | Version | Source |
|---|---|---|
| Magento Open Source | 2.4.7-p10 | https://mirror.mage-os.org/ (public Mage-OS composer mirror; serves 2.4.7-p10 with no credentials) |
| PHP | 8.3 (`php:8.3-fpm-bookworm`) | https://hub.docker.com/_/php |
| Composer | 2.10 (build time only) | https://hub.docker.com/_/composer |
| MariaDB | 10.11 | https://hub.docker.com/_/mariadb |
| OpenSearch | 2.12.0 | https://hub.docker.com/r/opensearchproject/opensearch |
| OpenSearch `analysis-icu` plugin | 2.12.0 (matches engine) | https://opensearch.org/ (installed via `opensearch-plugin install`) |
| OpenSearch `analysis-kuromoji` plugin | 2.12.0 (matches engine) | https://opensearch.org/ (installed via `opensearch-plugin install`) |
| Node.js | 20 (`node:20-alpine`) | https://hub.docker.com/_/node |
| nginx | from Debian bookworm apt (1.22.x) | https://packages.debian.org/bookworm/nginx |
| supervisor | from Debian bookworm apt (4.2.x) | https://packages.debian.org/bookworm/supervisor |
| PHPUnit | 9.x (dev dependency, kept in the image for `bin/assignment-test`) | https://phpunit.de/ |

Store locale is `ja_JP`, currency JPY, timezone `Asia/Tokyo`; the theme is a
child theme derived from `Magento/blank`.

### Base images (resolved digests)

These are the upstream base-image digests the build resolved and pinned as
build-args. They are recorded verbatim from `build/base-images.env`.

| Base image | Pinned digest |
|---|---|
| `php:8.3-fpm-bookworm` | `sha256:84ffb6f84362cd0cc74d6dea47cc1b376b4d7477f97c84b0e5bb287ab9df056c` |
| `composer:2.10` | `sha256:d8f6343d3fae98107426bc49163ccad46ef85aabd4a27d80a74401fab4aba332` |
| `mariadb:10.11` | `sha256:ce66c7be32a03aabe7241d0a10993a2db827ef652a35d25727d92a832ac8ef73` |
| `opensearchproject/opensearch:2.12.0` | `sha256:645d3d9390ade7ebef988d3c9bc61a6616f1863ff41efe97e2347d6bf7972504` |
| `node:20-alpine` | `sha256:fb4cd12c85ee03686f6af5362a0b0d56d50c58a04632e6c0fb8363f609372293` |

## Shipped image digests

The four images below are published to GHCR and referenced by digest in
`compose.yaml`. Digests are pinned into `compose.yaml` at publish time; the
`sha256:` values here are placeholders until then. Reproduce a digest with
`docker buildx imagetools inspect <ref> --format '{{.Manifest.Digest}}'`.

| Image | Tag | Digest |
|---|---|---|
| `ghcr.io/cidroy-tech/seller-refund-web` | `2.4.7-p10` | `sha256:<PINNED-AT-PUBLISH>` |
| `ghcr.io/cidroy-tech/seller-refund-db` | `2.4.7-p10` | `sha256:<PINNED-AT-PUBLISH>` |
| `ghcr.io/cidroy-tech/seller-refund-search` | `2.12.0` | `sha256:<PINNED-AT-PUBLISH>` |
| `ghcr.io/cidroy-tech/seller-refund-erp-stub` | `1.0.0` | `sha256:<PINNED-AT-PUBLISH>` |

## License posture

Each component is used under its own license. The primary license texts are
collected in [`../../LICENSES/`](../../LICENSES/); the component-to-license
summary is in [`../../THIRD_PARTY_NOTICES.md`](../../THIRD_PARTY_NOTICES.md).

| Component | License |
|---|---|
| Magento Open Source | OSL-3.0 / AFL-3.0 |
| OpenSearch (and the ICU / Kuromoji analysis plugins) | Apache-2.0 |
| MariaDB | GPL-2.0 |
| Node.js | MIT (plus its bundled dependencies' licenses) |
| PHP | PHP License v3.01 |
| nginx | BSD-2-Clause |
| Composer (build time only) | MIT |
| PHPUnit (dev dependency) | BSD-3-Clause |

The `Acme_SellerRefund` module, the ERP Refund API stub, the assignment seed
module, and all other scaffolding in this repository are original work,
provided under the terms in [`../../LICENSE`](../../LICENSE).

## Boot timing

`docker compose up -d --wait` reaches all-healthy well under a minute on the
reference host. Observed locally, a warm start (images already pulled, volumes
already created) settles in roughly 30-45 seconds; a cold start after
`docker compose down -v` is a little longer while the seeded datadir is copied
into a fresh volume and, if the product index alias is missing, the store
reindexes on first boot.

These are observed local figures, not a calibrated benchmark. TODO: replace
with the calibrated cold/warm figures from a timed dry-run (see
[`calibration-log.md`](calibration-log.md)).

## Edit / rebuild matrix

The `Acme_SellerRefund` module is bind-mounted from the host into the web
container, so edits are visible without rebuilding the image. The image runs in
Magento developer mode; how much you have to flush after an edit depends on
what you changed. `bin/assignment-cache-flush` (a thin wrapper over the
in-container script) does the right flush for each case.

| You changed | Command | Effect |
|---|---|---|
| PHP logic (class bodies, method internals) | nothing | Picked up live by developer mode. |
| `etc/*.xml`, `.phtml` templates, email templates | `bin/assignment-cache-flush` | Plain `cache:flush`. |
| JS / LESS (files under `view/`) | `bin/assignment-cache-flush --static` | Clears deployed static + `view_preprocessed`, then `cache:flush`. Static assets are copies, not symlinks, so edits are not live until this runs. |
| `di.xml`, constructor signatures, plugins | `bin/assignment-cache-flush --generated` | Clears generated code/metadata for the module, then `cache:flush`. |
| `db_schema.xml` | `bin/assignment-cache-flush --upgrade` | Runs `setup:upgrade --keep-generated`, then `cache:flush`. |
| Switching branches | `bin/assignment-cache-flush --all` | `--generated --static --upgrade` combined, then `cache:flush`. Use this after every `git switch`. |

After a JS or LESS change, do a hard reload in the browser so the client picks
up the freshly deployed static file.
