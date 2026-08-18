# Pre-baked OpenSearch image for the assignment.
#
# Adds the ICU and Kuromoji analysis plugins Magento's Japanese store needs,
# and removes every bundled plugin the assignment does not use - including the
# security plugin, whose absence makes the entrypoint disable transport
# security by itself (so no admin password or DISABLE_SECURITY_PLUGIN is set).
#
# Plugins are removed with a retry-until-stable loop: OpenSearch refuses to
# remove a plugin that is still "extended by" another (e.g. opensearch-ml is
# extended by opensearch-skills), and the exact dependency order varies by
# release. Each pass removes whatever it can and defers "extended by" refusals
# to the next pass; ANY other error (a wrong/absent plugin name) fails the build
# loudly, and so does a pass that makes no progress. No `|| true` anywhere:
# a surprise plugin can never ship, and a bad name can never pass silently.
#
# The bake resolves the base tag to a pinned digest and passes it as a
# build-arg; the tag below is the documented default.
ARG SEARCH_BASE_IMAGE=opensearchproject/opensearch:2.12.0
FROM ${SEARCH_BASE_IMAGE}

ENV OPENSEARCH_JAVA_OPTS="-Xms512m -Xmx512m"

# The complete set of bundled plugins to remove (analysis-icu / analysis-kuromoji
# are installed above and kept). Order does not matter; the loop resolves it.
ENV OS_REMOVE_PLUGINS="opensearch-security opensearch-performance-analyzer opensearch-ml opensearch-neural-search opensearch-knn opensearch-security-analytics opensearch-alerting opensearch-anomaly-detection opensearch-asynchronous-search opensearch-cross-cluster-replication opensearch-index-management opensearch-notifications opensearch-notifications-core opensearch-observability opensearch-reports-scheduler opensearch-sql opensearch-geospatial opensearch-job-scheduler opensearch-flow-framework opensearch-skills opensearch-custom-codecs"

RUN set -eu; \
    bin/opensearch-plugin install --batch analysis-icu analysis-kuromoji; \
    remaining="$OS_REMOVE_PLUGINS"; \
    pass=0; \
    while [ -n "$(printf '%s' "$remaining" | tr -d ' ')" ]; do \
      pass=$((pass + 1)); \
      if [ "$pass" -gt 12 ]; then echo "FATAL: too many removal passes; remaining:$remaining" >&2; exit 13; fi; \
      progress=0; still=""; \
      for p in $remaining; do \
        if out="$(bin/opensearch-plugin remove "$p" 2>&1)"; then \
          echo "removed: $p"; progress=1; \
        elif printf '%s' "$out" | grep -q "cannot be removed because it is extended by"; then \
          echo "defer:   $p"; still="$still $p"; \
        else \
          echo "FATAL removing $p:" >&2; printf '%s\n' "$out" >&2; exit 11; \
        fi; \
      done; \
      remaining="$still"; \
      if [ "$progress" -eq 0 ] && [ -n "$(printf '%s' "$remaining" | tr -d ' ')" ]; then \
        echo "FATAL: no progress; remaining:$remaining" >&2; exit 12; \
      fi; \
    done; \
    echo "all target plugins removed"

COPY --chown=1000:1000 docker/opensearch/opensearch.yml /usr/share/opensearch/config/opensearch.yml
