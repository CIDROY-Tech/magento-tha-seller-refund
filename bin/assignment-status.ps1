#!/usr/bin/env pwsh
# Show the health of the assignment stack: service states, the web health JSON,
# the stub's recorded-refund count, logs for anything unhealthy, and the
# OpenSearch preflight if search is not healthy.
#   bin/assignment-status.ps1
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)

$services = @('db', 'search', 'erp-refund-stub', 'web', 'assignment-ready')

function Get-Health($cid) {
    try {
        return (docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' $cid 2>$null)
    } catch {
        return 'unknown'
    }
}

Write-Host '== compose ps =='
docker compose ps

Write-Host ''
Write-Host '== web health =='
docker compose exec -T web sh -c 'curl -fsS http://127.0.0.1:8080/assignment-health.php' 2>$null
if ($LASTEXITCODE -ne 0) { Write-Host '(web health endpoint not reachable)' }

Write-Host ''
Write-Host '== stub recorded refunds =='
docker compose exec -T web sh -c 'curl -fsS http://erp-refund-stub:8081/_debug/refunds' 2>$null
if ($LASTEXITCODE -ne 0) { Write-Host '(stub not reachable)' }

Write-Host ''
Write-Host '== unhealthy service logs =='
$anyUnhealthy = $false
foreach ($svc in $services) {
    $cid = (docker compose ps -q $svc 2>$null)
    if ([string]::IsNullOrWhiteSpace($cid)) {
        Write-Host "-- ${svc}: not running --"
        $anyUnhealthy = $true
        continue
    }
    $h = Get-Health $cid
    if ($h -ne 'healthy' -and $h -ne 'running') {
        Write-Host "-- ${svc} (${h}): last 30 log lines --"
        docker compose logs --tail 30 $svc 2>$null
        $anyUnhealthy = $true
    }
}
if (-not $anyUnhealthy) { Write-Host '(all services healthy)' }

Write-Host ''
Write-Host '== web exception + cron log tail =='
docker compose exec -T web sh -c 'tail -n 20 /var/www/html/var/log/exception.log 2>/dev/null; echo "---"; tail -n 20 /var/www/html/var/log/assignment-cron.log 2>/dev/null' 2>$null

$searchCid = (docker compose ps -q search 2>$null)
if (-not [string]::IsNullOrWhiteSpace($searchCid)) {
    $sh = Get-Health $searchCid
    if ($sh -ne 'healthy' -and $sh -ne 'running') {
        Write-Host ''
        Write-Host '== search preflight =='
        bash docker/opensearch/preflight.sh
    }
}
