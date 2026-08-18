#!/usr/bin/env pwsh
# Run the module test suites inside the web container.
#   bin/assignment-test.ps1 [unit|integration|smoke|all] [--filter X]
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)

docker compose exec -T -u www-data web assignment-test @args
exit $LASTEXITCODE
