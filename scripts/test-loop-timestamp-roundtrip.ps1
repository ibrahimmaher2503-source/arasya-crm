$ErrorActionPreference = 'Stop'
$ast = [Management.Automation.Language.Parser]::ParseFile((Join-Path $PSScriptRoot 'task-loop.ps1'), [ref]$null, [ref]$null)
foreach ($function in $ast.FindAll({ param($node) $node -is [Management.Automation.Language.FunctionDefinitionAst] }, $false)) { Invoke-Expression $function.Extent.Text }
$stamp = '2026-10-17T00:00:00.7654321Z'
$state = [pscustomobject]@{ State = [pscustomobject]@{ revision = 0; updated_utc = $stamp; leases = @(); events = @() } }
$plan = [pscustomobject]@{ lease_change = 'None'; event = [pscustomobject]@{ event_id = 'timestamp-fixture'; timestamp_utc = $stamp }; state_updated_utc = $stamp }
$expected = Get-StateAfterText $state $plan
$root = Join-Path ([IO.Path]::GetTempPath()) ('arasya-timestamp-' + [guid]::NewGuid().ToString('N'))
[void][IO.Directory]::CreateDirectory((Join-Path $root 'docs/governance/loop'))
try {
    $plan | Add-Member handoff_path 'docs/governance/loop/handoffs/fixture.md'
    $plan | Add-Member handoff_content 'fixture'
    $plan | Add-Member handoff_hash (Get-TextSha256 'fixture')
    $plan | Add-Member task_status_target 'ready'
    $plan.event | Add-Member task_id 'fixture'
    $plan.event | Add-Member owner 'fixture'
    $plan.event | Add-Member handoff_path $plan.handoff_path
    $payload = [pscustomobject]@{ schema_version = 1; tasks_path = 'docs/backlog/tasks.json'; state_path = 'docs/governance/loop/state.json'; task_id = 'fixture'; owner = 'fixture'; plans = @($plan) }
    $expected = Get-StateAfterText $state $plan
    $written = Write-PendingIntent $payload $root
    $read = Read-PendingIntent $root
    $actual = Get-StateAfterText $state $read.Payload.plans[0]
    if ($expected -cne $actual) { throw 'Persisted intent timestamp changed recomputed state bytes/hash.' }
    if ($read.Payload.plans[0].state_updated_utc -cne $stamp) { throw 'Timestamp precision/type not preserved.' }
    if ((Get-TextSha256 $actual) -cne (Get-TextSha256 $expected)) { throw 'State hash changed after journal round trip.' }
    Write-Output 'PASS: timestamp journal roundtrip assertions=3'
} finally {
    $full = [IO.Path]::GetFullPath($root)
    if ($full.StartsWith([IO.Path]::GetTempPath()) -and (Split-Path $full -Leaf) -match '^arasya-timestamp-[a-f0-9]{32}$') { [IO.Directory]::Delete($full, $true) }
}
