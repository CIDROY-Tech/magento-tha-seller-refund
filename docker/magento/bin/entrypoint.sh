#!/usr/bin/env bash
# Web container entrypoint.
#
# Runs as root before supervisord takes over. Waits for the database and
# search to answer, reindexes on a first boot with an empty search volume
# (the product alias is absent), writes the boot marker the health endpoint
# checks, then execs whatever CMD was given (supervisord in normal use).
set -euo pipefail
export MSYS_NO_PATHCONV=1

MAGE_ROOT=/var/www/html
DB_HOST=db
SEARCH_HOST=search
SEARCH_PORT=9200
PRODUCT_ALIAS=magento2_product_1

log() { printf '[entrypoint] %s\n' "$*"; }

wait_for() {
  # wait_for <label> <command...> - polls the command for up to 60 seconds.
  local label=$1
  shift
  local i
  for i in $(seq 1 60); do
    if "$@" >/dev/null 2>&1; then
      log "${label} is up"
      return 0
    fi
    sleep 1
  done
  log "WARNING: ${label} did not respond within 60s; continuing anyway"
  return 1
}

log "waiting for database..."
wait_for "database" mariadb -h "$DB_HOST" -umagento -pmagento -e 'SELECT 1' magento || true

log "waiting for search..."
wait_for "search" curl -fsS "http://${SEARCH_HOST}:${SEARCH_PORT}/_cluster/health?wait_for_status=yellow&timeout=3s" || true

# Install-dependent steps run only once Magento is actually installed. During
# the bake's installer phase the container comes up BEFORE setup:install runs
# (bake-inside.sh installs, seeds and warms via docker exec), so env.php is
# absent here and we simply start the services and wait.
if [ -f "${MAGE_ROOT}/app/etc/env.php" ]; then
  mkdir -p "${MAGE_ROOT}/var/log"
  # Reindex only when the product alias is missing, i.e. the search volume is
  # fresh. On a warm restart the alias is present and this is skipped.
  if curl -fsS "http://${SEARCH_HOST}:${SEARCH_PORT}/_alias/${PRODUCT_ALIAS}" >/dev/null 2>&1; then
    log "product alias ${PRODUCT_ALIAS} present; skipping reindex"
  else
    log "product alias ${PRODUCT_ALIAS} missing; running first-boot reindex"
    gosu www-data php "${MAGE_ROOT}/bin/magento" indexer:reindex \
      >> "${MAGE_ROOT}/var/log/assignment-cron.log" 2>&1 \
      || log "WARNING: reindex returned non-zero; check var/log/assignment-cron.log"
  fi
  gosu www-data touch "${MAGE_ROOT}/var/.assignment-booted"
  log "boot marker written; handing off to: $*"
else
  log "Magento not installed yet (bake installer phase); starting services only"
fi

exec "$@"
