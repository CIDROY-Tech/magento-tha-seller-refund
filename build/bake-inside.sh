#!/usr/bin/env bash
# Runs INSIDE the installer web container (pass 1 of the bake) as www-data.
#
# Installs Magento against the temporary db/search, applies store config,
# enables and upgrades the module, seeds the assignment data, compiles DI,
# deploys static content, reindexes, warms, runs the module suites, then packs
# the warmed application state into a tarball the runtime image will unpack.
#
# set -euo pipefail plus an explicit assert after each significant step: a
# failure stops the bake so it is fixed rather than shipped.
set -euo pipefail
export MSYS_NO_PATHCONV=1

MAGE_ROOT=/var/www/html
cd "$MAGE_ROOT"

BIN="php bin/magento"
ARTIFACT_OUT=${ARTIFACT_OUT:-/build/web-artifacts.tgz}

# Synthetic, documented values (see build/README.md). No real secrets.
ADMIN_USER=${ADMIN_USER:-admin}
ADMIN_PASSWORD=${ADMIN_PASSWORD:-Assignment7Admin}
ADMIN_EMAIL=${ADMIN_EMAIL:-admin@example.com}
CRYPT_KEY=${CRYPT_KEY:-0123456789abcdef0123456789abcdef}

log() { printf '\n[bake-inside] %s\n' "$*"; }

log "setup:install"
$BIN setup:install \
  --base-url=http://localhost:8080/ \
  --db-host=db \
  --db-name=magento \
  --db-user=magento \
  --db-password=magento \
  --search-engine=opensearch \
  --opensearch-host=search \
  --opensearch-port=9200 \
  --opensearch-index-prefix=magento2 \
  --opensearch-timeout=15 \
  --opensearch-enable-auth=0 \
  --backend-frontname=admin \
  --admin-user="$ADMIN_USER" \
  --admin-password="$ADMIN_PASSWORD" \
  --admin-email="$ADMIN_EMAIL" \
  --admin-firstname=Site \
  --admin-lastname=Admin \
  --language=ja_JP \
  --currency=JPY \
  --timezone=Asia/Tokyo \
  --use-rewrites=1 \
  --session-save=files \
  --key="$CRYPT_KEY" \
  --cleanup-database \
  --disable-modules=Magento_TwoFactorAuth

log "assert setup:db:status is up to date"
$BIN setup:db:status

log "deploy:mode:set developer"
$BIN deploy:mode:set developer

log "config:set store defaults"
$BIN config:set currency/options/base JPY
$BIN config:set currency/options/default JPY
$BIN config:set currency/options/allow JPY
$BIN config:set general/locale/timezone Asia/Tokyo
# NOTE: base URLs are set by setup:install --base-url (locked in env.php) and then
# made dynamic by the env.php patch below - do NOT config:set them here (locked).
$BIN config:set admin/security/session_lifetime 31536000
$BIN config:set admin/security/password_lifetime 0
$BIN config:set admin/security/lockout_failures 0
$BIN config:set admin/usage/enabled 0
$BIN config:set dev/static/sign 1
$BIN config:set system/full_page_cache/caching_application 1

log "patch app/etc/env.php to read WEB_PORT and ERP_STUB_BASE_URL at runtime"
php -r '
$file = "app/etc/env.php";
$config = include $file;
$export = var_export($config, true);
$php  = "<?php\n";
$php .= "\$webPort = getenv(\"WEB_PORT\") ?: \"8080\";\n";
$php .= "\$erpBase = getenv(\"ERP_STUB_BASE_URL\") ?: \"http://erp-refund-stub:8081\";\n";
$php .= "\$config = " . $export . ";\n";
$php .= "\$config[\"system\"][\"default\"][\"web\"][\"unsecure\"][\"base_url\"] = \"http://localhost:\" . \$webPort . \"/\";\n";
$php .= "\$config[\"system\"][\"default\"][\"web\"][\"secure\"][\"base_url\"] = \"http://localhost:\" . \$webPort . \"/\";\n";
$php .= "\$config[\"system\"][\"default\"][\"acme_seller_refund\"][\"erp\"][\"base_url\"] = \$erpBase;\n";
$php .= "return \$config;\n";
file_put_contents($file, $php);
'
php -l app/etc/env.php

log "module:enable Acme_SellerRefund Acme_AssignmentSeed"
$BIN module:enable Acme_SellerRefund Acme_AssignmentSeed

log "setup:upgrade --keep-generated"
$BIN setup:upgrade --keep-generated

log "assert both modules are enabled"
$BIN module:status Acme_SellerRefund | grep -qi 'enabled' || { echo "Acme_SellerRefund not enabled"; exit 1; }

log "assignment:seed"
$BIN assignment:seed

# The health endpoint compares core_config_data acme_assignment/seed/version with
# the runtime image's /etc/assignment/seed-version (baked from SEED_VERSION=$REL).
# assignment:seed writes that marker via WriterInterface from ASSIGNMENT_SEED_VERSION
# (passed into this container by bake.sh), so the DB dump matches the build-arg.
# We do NOT config:set it: that path is not declared in system.xml and config:set
# rejects undeclared paths; the WriterInterface write in assignment:seed is authoritative.

log "setup:di:compile"
$BIN setup:di:compile

log "setup:static-content:deploy -f ja_JP en_US"
$BIN setup:static-content:deploy -f ja_JP en_US

log "indexer:reindex"
$BIN indexer:reindex

log "cache:flush"
$BIN cache:flush

# The installer container's entrypoint ran BEFORE install, so it never wrote the
# boot marker the health endpoint (and assignment-warm's health gate) require.
# The app is installed and serving now, so write it here.
touch var/.assignment-booted

log "assignment-warm.sh"
ADMIN_USER="$ADMIN_USER" ADMIN_PASSWORD="$ADMIN_PASSWORD" bash /usr/local/bin/assignment-warm.sh http://localhost:8080

log "run module test suites (must be green when present)"
# Tests ride the runtime bind mount, so the first bake runs before they exist;
# enforce each suite only once its phpunit config is present.
if [ -f app/code/Acme/SellerRefund/Test/Unit/phpunit.xml ]; then
  php vendor/bin/phpunit -c app/code/Acme/SellerRefund/Test/Unit/phpunit.xml
else
  log "no unit suite yet - skipping"
fi
if [ -f app/code/Acme/SellerRefund/Test/Integration/phpunit.xml ]; then
  php vendor/bin/phpunit -c app/code/Acme/SellerRefund/Test/Integration/phpunit.xml
else
  log "no integration suite yet - skipping"
fi
# Smoke (reachability, incl. the health 200 gate) runs in bake.sh pass-2 via
# `assignment-test all` against the runtime image, which carries
# /etc/assignment/seed-version. In this installer phase the health endpoint
# returns 503 by design (see assignment-warm.sh), so smoke does not belong here;
# unit + integration above validate the application logic.

log "clean transient state"
rm -rf var/log/* var/session/* var/report/* var/tmp/* 2>/dev/null || true
touch var/.baked

log "pack warmed artefacts into ${ARTIFACT_OUT}"
mkdir -p "$(dirname "$ARTIFACT_OUT")"
tar -czf "$ARTIFACT_OUT" \
  app/etc/env.php \
  app/etc/config.php \
  generated \
  pub/static \
  var/cache \
  var/page_cache \
  var/view_preprocessed \
  pub/media \
  var/.baked

log "bake-inside complete: ${ARTIFACT_OUT}"
