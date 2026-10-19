$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$policy = Get-Content (Join-Path $root 'docs/governance/gate-policy.json') -Raw | ConvertFrom-Json
$evidence = Join-Path $root $policy.development_authorizations[0].evidence
if (-not (Test-Path -LiteralPath $evidence -PathType Leaf)) { throw 'Founder governance evidence is missing.' }
if ($policy.model -ne 'Founder-Led' -or $policy.founder_authority -ne 'Ibrahim') { throw 'Founder authority policy is invalid.' }
$ids = @($policy.development_authorizations | ForEach-Object task_id)
if ($ids.Count -ne 4 -or @($ids | Select-Object -Unique).Count -ne 4) { throw 'Development authorization must be explicit and task-scoped.' }
if (@($policy.development_authorizations | Where-Object { $_.scope -match 'actual deployment|production implementation' }).Count -ne 0) { throw 'Development authorization cannot include production implementation scope.' }
if ((Get-Content (Join-Path $root 'docs/governance/evidence/GOV-AMEND-001.md') -Raw) -notmatch 'PREPARED') { throw 'Historical GOV-AMEND-001 status must remain PREPARED.' }
Write-Output 'PASS: founder governance policy; scoped development authorizations, preserved historical amendment, and production boundary.'
