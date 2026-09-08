# Multi-stage build for the assignment web image.
#
#   deps      php-fpm + PHP extensions + apt packages          (stable, expensive)
#   vendor    deps + Composer + Magento (Mage-OS mirror) + app  (only re-runs when deps or app changes)
#   base      deps + nginx/php/supervisor config + ops scripts  (cheap; config edits rebuild only from here)
#   installer base + vendor tree + bake-inside.sh               (pass 1 only; never pushed)
#   runtime   base + vendor tree + warmed artefacts             (the shipped image)
#
# Composer lives in `vendor` which is FROM `deps` (NOT from the config-bearing
# `base`), so editing nginx/supervisor/entrypoint config never invalidates the
# ~15-minute Composer install.
#
# The bake resolves the base tags to pinned digests and passes them as
# build-args; the tags below are the documented defaults. See build/README.md.

ARG PHP_BASE_IMAGE=php:8.3-fpm-bookworm
ARG COMPOSER_IMAGE=composer:2.10
ARG INSTALL_PHP_EXTENSIONS_VERSION=2.11.12

# ---------------------------------------------------------------------------
# deps - php + extensions + system packages (stable, expensive; rarely changes)
# ---------------------------------------------------------------------------
FROM ${PHP_BASE_IMAGE} AS deps

ARG INSTALL_PHP_EXTENSIONS_VERSION
ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    TZ=Asia/Tokyo

# PHP extensions the platform requires that are NOT built into
# php:8.3-fpm-bookworm. opcache/sodium/mbstring/etc. are already present and are
# deliberately not reinstalled. ext-ftp is required and is not in the base.
ADD https://github.com/mlocati/docker-php-extension-installer/releases/download/${INSTALL_PHP_EXTENSIONS_VERSION}/install-php-extensions /usr/local/bin/install-php-extensions
RUN chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions bcmath ftp gd intl pdo_mysql soap sockets xsl zip

# Runtime packages. Removing the nginx default index keeps /var/www/html empty
# so `composer create-project .` does not abort on a non-empty directory.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx supervisor gosu curl unzip git mariadb-client procps ca-certificates tzdata \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default \
    && rm -f /var/www/html/index.nginx-debian.html

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# composer-bin - just to lift the pinned Composer binary
# ---------------------------------------------------------------------------
FROM ${COMPOSER_IMAGE} AS composer-bin

# ---------------------------------------------------------------------------
# vendor - Magento install + application code (FROM deps, so config edits do
#          NOT invalidate this stage's Composer layer)
# ---------------------------------------------------------------------------
FROM deps AS vendor
COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Create the project from the public Mage-OS mirror (no Magento auth keys),
# pin the mirror as the primary repository, then install WITH dev dependencies
# (phpunit is needed by the module test suites).
RUN composer create-project \
        --repository-url=https://mirror.mage-os.org/ \
        magento/project-community-edition=2.4.7-p10 . \
        --no-install --no-interaction \
    && composer config repositories.0 composer https://mirror.mage-os.org/ \
    && composer install --no-interaction --prefer-dist --no-progress

# Application code baked into the image. These directories are produced by the
# other workers; the bake context contains them. They sit AFTER the Composer
# layer, so editing module/seed code does not re-run Composer.
COPY app/code/Acme/SellerRefund /var/www/html/app/code/Acme/SellerRefund
COPY seed/Acme/AssignmentSeed /var/www/html/app/code/Acme/AssignmentSeed
COPY app/design/frontend/Acme /var/www/html/app/design/frontend/Acme
COPY docker/magento/assignment-health.php /var/www/html/pub/assignment-health.php
# Smoke suite is run in-container by `bin/assignment-test smoke`; it is not part
# of the bind-mounted module, so it must be baked in.
COPY dev/tests/smoke /var/www/html/dev/tests/smoke

# composer ran as root, so the tree is root-owned. The installer phase runs
# bake-inside.sh as www-data (setup:install writes app/etc, var, generated,
# pub/static), so hand ownership to www-data and make sure var/log exists.
RUN mkdir -p /var/www/html/var/log /var/www/html/var/session \
    && chown -R www-data:www-data /var/www/html

# ---------------------------------------------------------------------------
# base - deps + our nginx/php/supervisor config and ops scripts (cheap layer;
#        editing config rebuilds only from here, never Composer)
# ---------------------------------------------------------------------------
FROM deps AS base

# PHP / FPM / nginx / supervisor configuration.
COPY docker/magento/php/php.ini /usr/local/etc/php/conf.d/zz-assignment.ini
COPY docker/magento/php-fpm/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf
COPY docker/magento/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/magento/supervisord.conf /etc/supervisor/conf.d/assignment.conf

# Ops helpers and entrypoint on PATH.
COPY docker/magento/bin/assignment-test /usr/local/bin/assignment-test
COPY docker/magento/bin/assignment-cache-flush /usr/local/bin/assignment-cache-flush
COPY docker/magento/bin/assignment-warm.sh /usr/local/bin/assignment-warm.sh
COPY docker/magento/bin/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/assignment-test \
        /usr/local/bin/assignment-cache-flush \
        /usr/local/bin/assignment-warm.sh \
        /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# installer (pass 1 only; never pushed)
# ---------------------------------------------------------------------------
FROM base AS installer
# Bring in the Magento tree + app code from vendor (www-data-owned already).
COPY --from=vendor /var/www/html /var/www/html
COPY build/bake-inside.sh /build/bake-inside.sh
RUN chmod +x /build/bake-inside.sh
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/assignment.conf"]

# ---------------------------------------------------------------------------
# runtime (the shipped image)
# ---------------------------------------------------------------------------
FROM base AS runtime

ARG IMAGE_SOURCE=https://github.com/CIDROY-Tech/magento-tha-seller-refund
ARG SEED_VERSION=dev
LABEL org.opencontainers.image.source=$IMAGE_SOURCE

# Application from the vendor stage; no Composer in the shipped image.
COPY --from=vendor --chown=www-data:www-data /var/www/html /var/www/html

# Warmed artefacts (env.php, config.php, generated, static, caches, media)
# produced by pass 1 of the bake.
ADD --chown=www-data:www-data build/artifacts/web/web-artifacts.tgz /var/www/html/

# Readiness script for the assignment-ready service.
COPY docker/readiness/assignment-ready.sh /var/www/html/assignment-ready.sh
RUN chmod +x /var/www/html/assignment-ready.sh

# Seed-version marker compared by the health endpoint.
RUN mkdir -p /etc/assignment \
    && printf '%s\n' "${SEED_VERSION}" > /etc/assignment/seed-version \
    && chown -R www-data:www-data /var/www/html/var /var/www/html/generated /var/www/html/pub 2>/dev/null || true

WORKDIR /var/www/html
EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/assignment.conf"]
