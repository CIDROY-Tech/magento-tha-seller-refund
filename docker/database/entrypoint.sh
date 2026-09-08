#!/usr/bin/env bash
# Custom MariaDB entrypoint for the pre-baked db image.
#
# The image ships a fully-initialised datadir under /var/lib/mysql-baked (built
# at image-build time from the seed dump). On the first boot with an empty data
# volume we copy that datadir into the volume - a few seconds - so the store is
# usable immediately instead of importing the dump on every fresh volume. On a
# subsequent boot the volume already holds the data and we start straight up.
set -euo pipefail

BAKED=/var/lib/mysql-baked
DATADIR=/var/lib/mysql

if [ -d "$BAKED" ] && [ -z "$(ls -A "$DATADIR" 2>/dev/null || true)" ]; then
  echo "[db-entrypoint] empty data volume detected; seeding from baked datadir"
  cp -a "$BAKED/." "$DATADIR/"
  chown -R mysql:mysql "$DATADIR"
  echo "[db-entrypoint] seed copy complete"
else
  echo "[db-entrypoint] existing data volume; using it as-is"
fi

# Hand off to the stock MariaDB entrypoint, which starts mariadbd. Because the
# datadir is already populated it skips initialisation and boots directly.
exec docker-entrypoint.sh "$@"
