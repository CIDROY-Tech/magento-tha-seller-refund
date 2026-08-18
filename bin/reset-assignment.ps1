#!/usr/bin/env pwsh
# Reset the assignment stack.
#   bin/reset-assignment.ps1 [--yes] [--data-only]
#
#   (default)     tear down this project's containers and volumes, then bring
#                 the stack back up (project-scoped; never prunes images or
#                 other projects). Asks for confirmation unless --yes.
#   --data-only   re-seed the assignment data and clear the stub state, leaving
#                 the stack running.
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)

$yes = $false
$dataOnly = $false
foreach ($a in $args) {
    switch ($a) {
        '--yes'       { $yes = $true }
        '--data-only' { $dataOnly = $true }
        default {
            Write-Host 'usage: reset-assignment.ps1 [--yes] [--data-only]'
            exit 2
        }
    }
}

if ($dataOnly) {
    Write-Host 'Re-seeding assignment data and clearing stub state...'
    docker compose exec -T -u www-data web php bin/magento assignment:seed --reset
    docker compose exec -T web sh -c 'curl -fsS -X POST http://erp-refund-stub:8081/_debug/reset || true'
    Write-Host 'Data reset complete.'
    exit $LASTEXITCODE
}

if (-not $yes) {
    $ans = Read-Host "This will DESTROY this project's volumes and re-create the stack. Continue? [y/N]"
    if ($ans -notmatch '^(y|Y|yes|YES)$') {
        Write-Host 'aborted'
        exit 1
    }
}

Write-Host 'Recreating the stack (project-scoped; other projects untouched)...'
docker compose down -v --remove-orphans
docker compose up -d --wait
Write-Host 'Reset complete. Run bin/assignment-status.ps1 to check health.'
