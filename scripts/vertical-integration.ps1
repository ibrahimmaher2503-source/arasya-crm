[CmdletBinding()]
param([string]$Root = (Split-Path $PSScriptRoot -Parent))

$ErrorActionPreference = 'Stop'
Push-Location $Root
try {
    $connection = if ($env:DB_CONNECTION) { $env:DB_CONNECTION } else { 'sqlite' }
    if ($connection -ne 'mysql') {
        Write-Output 'SKIPPED: MariaDB qualification requires DB_CONNECTION=mysql; no fallback was used.'
        exit 0
    }

    php artisan test --filter=A5VINT002VerticalIntegrationTest
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
} finally {
    Pop-Location
}
