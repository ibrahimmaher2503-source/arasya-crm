$ErrorActionPreference = 'Stop'
$source = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'adr-mariadb-validation.ps1') -Raw

function Assert-FixtureOrdering([string]$Text) {
    foreach ($table in @('adrv_journals', 'adrv_outbox')) {
        $create = $Text.IndexOf("Schema::create('$table'")
        $firstUse = $Text.IndexOf("->table('$table')")
        if ($create -lt 0 -or $firstUse -lt 0 -or $create -gt $firstUse) {
            throw "$table must be created before its first query."
        }
    }
}

Assert-FixtureOrdering $source
Write-Output 'PASS: shared fixture tables are created before first use'
$broken = $source -replace "Schema::create\('adrv_journals'", "Schema::create('missing_fixture'"
$rejected = $false
try { Assert-FixtureOrdering $broken } catch { $rejected = $_.Exception.Message -eq 'adrv_journals must be created before its first query.' }
if (-not $rejected) { throw 'Ordering guard did not reject the missing table setup.' }
Write-Output 'PASS: ordering guard rejects missing journal setup'
if ($source.Contains('fwrite(STDOUT, ''PASS: '' . $migrationEvidence')) {
    throw 'DDL information must not be counted as a PASS assertion.'
}
Write-Output 'PASS: DDL information cannot inflate assertion markers'

$queueSource = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'adr-validation/queue-lifecycle.php') -Raw
if (-not $queueSource.Contains('Artisan::call(''queue:retry'', [''id'' => [$failed->uuid]])')) {
    throw 'Database UUID failed-job provider requires UUID for queue:retry, not the numeric row ID.'
}
Write-Output 'PASS: queue retry uses the failed-job provider UUID'

$ast = [Management.Automation.Language.Parser]::ParseInput($source, [ref]$null, [ref]$null)
$sanitizer = $ast.Find({ param($node) $node -is [Management.Automation.Language.FunctionDefinitionAst] -and $node.Name -eq 'Get-SafeQueueOutput' }, $true)
Invoke-Expression $sanitizer.Extent.Text
$safe = @(Get-SafeQueueOutput "DB_PASSWORD=sentinel-secret`nFAIL: QUEUE_AUTHORIZED_REPLAY_ASSERTION`nSQL_ERROR: SQLSTATE=42S02 code=1146")
if (($safe -join '').Contains('sentinel-secret') -or $safe[1] -ne 'FAIL: QUEUE_AUTHORIZED_REPLAY_ASSERTION' -or $safe[2] -ne 'SQL_ERROR: SQLSTATE=42S02 code=1146') {
    throw 'Queue diagnostics must redact unstructured secrets and preserve safe failure codes.'
}
Write-Output 'PASS: queue diagnostics redact secrets and preserve assertion and SQL codes'
