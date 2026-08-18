#!/usr/bin/env bash
# Resolve the published image digests for a release and pin them into
# compose.yaml, replacing the REPLACE_AT_PUBLISH placeholders (or a previously
# pinned digest). Run after `build/bake.sh <rN> --push`, or on its own once the
# tags exist in the registry.
#
#   build/pin-digests.sh <rN>
#
# REGISTRY overrides the default org.
set -euo pipefail
export MSYS_NO_PATHCONV=1

REL=${1:-}
if [ -z "$REL" ]; then
  echo "usage: build/pin-digests.sh <rN>   (e.g. build/pin-digests.sh r1)" >&2
  exit 2
fi

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
REPO_ROOT=$(cd "$SCRIPT_DIR/.." && pwd)
cd "$REPO_ROOT"

REGISTRY=${REGISTRY:-ghcr.io/cidroy-tech}
COMPOSE=compose.yaml
DIGEST_NOTE=build/artifacts/digests.md

# image-short-name  tag
IMAGES=(
  "seller-refund-web:2.4.7-p10-${REL}"
  "seller-refund-db:2.4.7-p10-${REL}"
  "seller-refund-search:2.12.0-${REL}"
  "seller-refund-erp-stub:1.0.0"
)

mkdir -p "$(dirname "$DIGEST_NOTE")"
{
  echo "# Pinned image digests - release ${REL}"
  echo ""
  echo "| Image | Tag | Digest |"
  echo "|---|---|---|"
} > "$DIGEST_NOTE"

for entry in "${IMAGES[@]}"; do
  name=${entry%%:*}
  tag=${entry#*:}
  ref="${REGISTRY}/${name}:${tag}"
  echo "[pin] resolving ${ref}"
  digest=$(docker buildx imagetools inspect "$ref" --format '{{.Manifest.Digest}}')
  if [ -z "$digest" ]; then
    echo "[pin] ERROR: could not resolve digest for ${ref}" >&2
    exit 1
  fi
  # Replace any existing tag@digest (placeholder or real) for this image.
  sed -i -E "s#(${REGISTRY}/${name}):[^@\"[:space:]]*@sha256:[0-9a-fA-F]+#\1:${tag}@${digest}#g" "$COMPOSE"
  echo "| ${name} | ${tag} | ${digest} |" >> "$DIGEST_NOTE"
  echo "[pin] ${name} -> ${digest}"
done

echo "[pin] compose.yaml updated; digest table written to ${DIGEST_NOTE}"
echo "[pin] paste that table into docs/build-notes/runtime-and-licenses.md"
