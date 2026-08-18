# Pre-baked MariaDB image for the assignment.
#
# Ships a fully-initialised datadir so the stack has a ready store the moment
# the volume is created. build/bake.sh (pass 1) produces the seed dump at
# build/artifacts/db/seed.sql.gz; this image imports it at BUILD time against a
# throwaway datadir, then bakes that datadir in. The wrapper entrypoint copies
# it into the runtime volume on first boot. See build/README.md.
#
# The bake resolves the base tag to a pinned digest and passes it as a
# build-arg; the tag below is the documented default.
ARG DB_BASE_IMAGE=mariadb:10.11
FROM ${DB_BASE_IMAGE}

# Synthetic, documented credentials. Used only during the build-time import and
# as the init defaults; the application connects as magento/magento.
ENV MARIADB_DATABASE=magento \
    MARIADB_USER=magento \
    MARIADB_PASSWORD=magento \
    MARIADB_ROOT_PASSWORD=root

COPY docker/database/my.cnf /etc/mysql/conf.d/zz-assignment.cnf
COPY docker/database/entrypoint.sh /usr/local/bin/assignment-db-entrypoint.sh
# Seed dump from pass 1 of the bake (kept out of git; a placeholder keeps the
# build context valid before the first bake).
COPY build/artifacts/db/seed.sql.gz /assignment/seed.sql.gz

RUN chmod +x /usr/local/bin/assignment-db-entrypoint.sh

# Build-time initialisation: run the stock entrypoint against a baked datadir
# with the dump staged in initdb.d, wait for it to come up and finish the
# import, verify the schema is present, then shut down cleanly. Fails loudly if
# the import did not land any tables.
# NOTE: the dump is imported SYNCHRONOUSLY here rather than via initdb.d. The
# initdb.d approach raced with the readiness poll - the server socket answers
# before the (404-table, ~20k-line) import finishes, so a "table count > 0"
# check could pass and shut the server down mid-import, baking a partial datadir.
# Here the entrypoint initialises an empty magento DB + users, then we pipe the
# full dump in and BLOCK until it completes, and assert the full table count.
RUN set -eux; \
    mkdir -p /var/lib/mysql-baked; \
    docker-entrypoint.sh mariadbd \
        --datadir=/var/lib/mysql-baked \
        --skip-networking \
        --socket=/run/mysqld/mysqld.sock & \
    pid="$!"; \
    for i in $(seq 1 120); do \
        if mariadb --socket=/run/mysqld/mysqld.sock -uroot -proot -e 'SELECT 1' >/dev/null 2>&1; then break; fi; \
        sleep 2; \
    done; \
    mariadb --socket=/run/mysqld/mysqld.sock -uroot -proot -e 'SELECT 1' >/dev/null; \
    echo "importing seed dump (synchronous)..."; \
    zcat /assignment/seed.sql.gz | mariadb --socket=/run/mysqld/mysqld.sock -uroot -proot; \
    tables="$(mariadb --socket=/run/mysqld/mysqld.sock -uroot -proot -N -B \
        -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='magento';")"; \
    echo "baked table count: ${tables}"; \
    test "${tables}" -ge 400; \
    mariadb --socket=/run/mysqld/mysqld.sock -uroot -proot -N -B \
        -e "SELECT COUNT(*) FROM magento.store_website WHERE is_default=1" | grep -qx 1; \
    mariadb-admin --socket=/run/mysqld/mysqld.sock -uroot -proot shutdown; \
    wait "$pid" || true; \
    rm -f /assignment/seed.sql.gz

ENTRYPOINT ["assignment-db-entrypoint.sh"]
CMD ["mariadbd"]
