#!/usr/bin/env bash
# OpenSearch / Docker preflight check.
#
# Run this if `search` will not become healthy. It reports the three things
# that usually cause it: the Docker VM's vm.max_map_count being too low, Docker
# having too little memory, and a host port already in use. It reads, it never
# changes anything.
set -euo pipefail
export MSYS_NO_PATHCONV=1

REQUIRED_MAP_COUNT=262144
REQUIRED_MEM_GB=8

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
REPO_ROOT=$(cd "$SCRIPT_DIR/../.." && pwd)

WEB_PORT=8080
STUB_PORT=8081
if [ -f "$REPO_ROOT/.env" ]; then
  set -a
  # shellcheck disable=SC1090,SC1091
  . "$REPO_ROOT/.env"
  set +a
fi
WEB_PORT=${WEB_PORT:-8080}
STUB_PORT=${STUB_PORT:-8081}

log()     { printf '%s\n' "$*"; }
section() { printf '\n== %s ==\n' "$*"; }

if ! command -v docker >/dev/null 2>&1; then
  log "docker not found on PATH; start Docker Desktop / the Docker engine and retry."
  exit 1
fi

section "vm.max_map_count (Docker VM kernel)"
current=$(docker run --rm busybox cat /proc/sys/vm/max_map_count 2>/dev/null || echo "")
if [ -z "$current" ]; then
  log "could not read vm.max_map_count (is the Docker engine running?)"
elif { [ "$current" -lt "$REQUIRED_MAP_COUNT" ]; } 2>/dev/null; then
  log "current: $current  (required >= $REQUIRED_MAP_COUNT)  -- TOO LOW"
  log ""
  log "Raise it in the Docker VM, not on your host:"
  log "  Linux (Docker engine on the host):"
  log "    sudo sysctl -w vm.max_map_count=262144"
  log "  Windows + Docker Desktop (WSL2 backend):"
  log "    wsl -d docker-desktop sysctl -w vm.max_map_count=262144"
  log "  WSL2 distro directly:"
  log "    sudo sysctl -w vm.max_map_count=262144"
  log "  macOS + Docker Desktop:"
  log "    docker run --rm --privileged busybox sysctl -w vm.max_map_count=262144"
  log ""
  log "This resets when the VM restarts; re-run if needed."
else
  log "current: $current  (required >= $REQUIRED_MAP_COUNT)  -- OK"
fi

section "Docker memory"
mem_bytes=$(docker info --format '{{.MemTotal}}' 2>/dev/null || echo 0)
mem_gb=$(( mem_bytes / 1024 / 1024 / 1024 ))
if [ "$mem_gb" -lt "$REQUIRED_MEM_GB" ]; then
  log "Docker MemTotal: ${mem_gb} GiB  (recommended >= ${REQUIRED_MEM_GB} GiB)  -- LOW"
  log "Raise it in Docker Desktop: Settings > Resources > Memory."
else
  log "Docker MemTotal: ${mem_gb} GiB  (recommended >= ${REQUIRED_MEM_GB} GiB)  -- OK"
fi

section "Host port conflicts"
check_port() {
  local port=$1
  local label=$2
  if (exec 3<>"/dev/tcp/127.0.0.1/${port}") 2>/dev/null; then
    exec 3>&- 2>/dev/null || true
    log "port ${port} (${label}) is IN USE -- stop the other service or change ${label} in .env"
  else
    log "port ${port} (${label}) is free"
  fi
}
check_port "$WEB_PORT" WEB_PORT
check_port "$STUB_PORT" STUB_PORT

log ""
log "preflight complete."
