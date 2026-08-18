#!/usr/bin/env pwsh
# Clear Magento caches / generated code / static after editing the module.
#   bin/assignment-cache-flush.ps1 [--generated] [--static] [--upgrade] [--compile] [--all]
#
# Edit matrix (see README.md):
#   PHP logic .......................... nothing (developer mode picks it up)
#   etc/*.xml, .phtml templates, email . bin/assignment-cache-flush.ps1
#   JS / LESS .......................... bin/assignment-cache-flush.ps1 --static
#   di.xml / constructors / plugins .... bin/assignment-cache-flush.ps1 --generated
#   db_schema.xml ...................... bin/assignment-cache-flush.ps1 --upgrade
#   switching branches ................. bin/assignment-cache-flush.ps1 --all
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)

docker compose exec -T -u www-data web assignment-cache-flush @args
exit $LASTEXITCODE
