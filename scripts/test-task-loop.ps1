$ErrorActionPreference = 'Stop'
$loopScript = Join-Path $PSScriptRoot 'task-loop.ps1'
$fixtureRoot = Join-Path ([IO.Path]::GetTempPath()) ('my-arasya-task-loop-tests-' + [guid]::NewGuid().ToString('N'))
$script:fixtureRoot = $fixtureRoot
$script:loopScript = $loopScript
$script:arabicTitle = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String('2YXZh9mF2Kkg2LnYsdio2YrYqSDigJQgVVRGLTgg2YXYrdmB2YjYuA=='))
$script:arabicNotes = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String('2K3Yp9mB2Lgg2LnZhNmJINin2YTZhti1INin2YTYudix2KjZiiDZiNin2K7Yqtio2KfYsSBVVEYtOC4='))
$script:arabicValue = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String('2YLZitmF2Kkg2YXYs9iq2YLZhNip'))

function Assert-True([bool]$Condition, [string]$Message) {
    if (-not $Condition) { throw "ASSERTION FAILED: $Message" }
}

function Invoke-FixtureLoop {
    param(
        [Parameter(Mandatory = $true)][string]$Action,
        [string]$TaskId,
        [string]$Owner,
        [hashtable]$Extra = @{}
    )
    $parameters = @{ Action = $Action; Root = $script:fixtureRoot }
    if (-not [string]::IsNullOrWhiteSpace($TaskId)) { $parameters.TaskId = $TaskId }
    if (-not [string]::IsNullOrWhiteSpace($Owner)) { $parameters.Owner = $Owner }
    foreach ($key in $Extra.Keys) { $parameters[$key] = $Extra[$key] }
    & $script:loopScript @parameters
}

function Expect-FixtureFailure {
    param([string]$Name, [string]$ExpectedText, [scriptblock]$Command)
    $caught = $null
    try { & $Command | Out-Null } catch { $caught = $_.Exception.Message }
    if ($null -eq $caught) { throw "ASSERTION FAILED: $Name unexpectedly succeeded." }
    if ($caught -notmatch [regex]::Escape($ExpectedText)) {
        throw "ASSERTION FAILED: $Name failed unexpectedly: $caught"
    }
}

function New-FixtureTask {
    param(
        [string]$Id,
        [string]$Title,
        [string]$Status,
        [string[]]$Paths,
        [string[]]$Modules,
        [string[]]$DependsOn = @(),
        [string[]]$Blocks = @(),
        [string[]]$DecisionDependencies = @()
    )
    return [pscustomobject]@{
        id = $Id
        title = $Title
        status = $Status
        depends_on = @($DependsOn)
        blocks = @($Blocks)
        decision_dependencies = @($DecisionDependencies)
        scope = [pscustomobject]@{ paths = @($Paths); modules = @($Modules) }
        agent_notes = $script:arabicNotes
        unrelated = [pscustomobject]@{ keep = $script:arabicValue; items = @(1, 2) }
    }
}

function Write-FixtureTasks($Tasks) {
    $data = [pscustomobject]@{ schema_version = '1.0'; planning_only = $true; tasks = @($Tasks) }
    $path = Join-Path $script:fixtureRoot 'docs/backlog/tasks.json'
    $json = ConvertTo-Json -InputObject $data -Depth 20
    [IO.File]::WriteAllText($path, $json + "`n", [Text.UTF8Encoding]::new($false))
}

function Set-FixtureTaskStatus([string]$Id, [string]$Status) {
    $path = Join-Path $script:fixtureRoot 'docs/backlog/tasks.json'
    $data = ConvertFrom-Json -InputObject ([IO.File]::ReadAllText($path, [Text.Encoding]::UTF8))
    $task = @($data.tasks | Where-Object { $_.id -eq $Id }) | Select-Object -First 1
    if ($null -eq $task) { throw "Fixture task not found: $Id" }
    $task.status = $Status
    [IO.File]::WriteAllText($path, (ConvertTo-Json -InputObject $data -Depth 20) + "`n", [Text.UTF8Encoding]::new($false))
}

function Get-FixtureTask([string]$Id) {
    $path = Join-Path $script:fixtureRoot 'docs/backlog/tasks.json'
    $data = ConvertFrom-Json -InputObject ([IO.File]::ReadAllText($path, [Text.Encoding]::UTF8))
    return @($data.tasks | Where-Object { $_.id -eq $Id }) | Select-Object -First 1
}

function Get-FixtureHash([string]$Path) {
    return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash
}

try {
    [void][IO.Directory]::CreateDirectory((Join-Path $fixtureRoot 'docs/backlog'))
    [void][IO.Directory]::CreateDirectory((Join-Path $fixtureRoot 'docs/governance'))
    [void][IO.Directory]::CreateDirectory((Join-Path $fixtureRoot 'docs/architecture/adr'))
    [void][IO.Directory]::CreateDirectory((Join-Path $fixtureRoot 'docs/governance/evidence'))
    Copy-Item -LiteralPath (Join-Path (Split-Path $script:loopScript -Parent) '..\docs\governance\gate-policy.json') -Destination (Join-Path $fixtureRoot 'docs/governance/gate-policy.json')
    Copy-Item -LiteralPath (Join-Path (Split-Path $script:loopScript -Parent) '..\docs\governance\evidence\FOUNDER-LED-GOVERNANCE.md') -Destination (Join-Path $fixtureRoot 'docs\governance\evidence\FOUNDER-LED-GOVERNANCE.md')

    $tasks = @(
        (New-FixtureTask 'A0-CORE-998' $script:arabicTitle 'ready' @('docs/a.md') @('Alpha') @() @('A0-CORE-997')),
        (New-FixtureTask 'A0-CORE-997' 'Dependent task' 'planned' @('docs/b.md') @('Beta') @('A0-CORE-998') @()),
        (New-FixtureTask 'A0-CORE-996' 'Decision-gated task' 'blocked' @('docs/c.md') @('Gamma') @() @() @('OPEN-99')),
        (New-FixtureTask 'A0-CORE-992' 'ADR-gated task' 'blocked' @('docs/adr.md') @('Architecture') @() @() @('ADR-099')),
        (New-FixtureTask 'A0-CORE-995' 'Same-module task' 'ready' @('docs/other.md') @('Alpha')),
        (New-FixtureTask 'A0-CORE-994' 'Unrelated scope' 'ready' @('docs/shared') @('Beta')),
        (New-FixtureTask 'A0-CORE-993' 'Nested path scope' 'ready' @('docs/shared/config.json') @('Delta')),
        (New-FixtureTask 'A0-CORE-901' 'Reassess predecessor' 'planned' @('docs/reassess-predecessor.md') @('ReassessPrerequisite') @() @('A0-CORE-900')),
        (New-FixtureTask 'A0-CORE-900' 'General reassessment fixture' 'blocked' @('docs/reassess.md') @('ReassessTarget') @('A0-CORE-901') @() @('ADR-001')),
        (New-FixtureTask 'A0-CORE-902' 'Concurrent task fixture' 'blocked' @('docs/reassess-concurrent-task.md') @('ReassessConcurrentTask')),
        (New-FixtureTask 'A0-CORE-903' 'Concurrent state fixture' 'blocked' @('docs/reassess-concurrent-state.md') @('ReassessConcurrentState'))
    )
    Write-FixtureTasks $tasks
    # Reopening a mistaken completion uses the same audited, recoverable CLI boundary.
    $reopenArgs = @{ Reason = 'Completion evidence was incomplete'; Blocker = 'Acceptance proof missing'; BlockerOwner = 'acceptance-owner'; NextAction = 'Supply acceptance proof'; Evidence = 'Independent review found the missing proof.' }
    Set-FixtureTaskStatus 'A0-CORE-903' 'ready'
    Invoke-FixtureLoop -Action Claim -TaskId 'A0-CORE-903' -Owner 'lease-worker' | Out-Null
    Set-FixtureTaskStatus 'A0-CORE-903' 'done'
    Expect-FixtureFailure 'Reopen refuses existing lease' 'while any lease exists' { Invoke-FixtureLoop -Action Reopen -TaskId 'A0-CORE-903' -Owner 'fixture-coordinator' -Extra $reopenArgs }
    Set-FixtureTaskStatus 'A0-CORE-903' 'in_progress'
    Invoke-FixtureLoop -Action Release -TaskId 'A0-CORE-903' -Owner 'lease-worker' -Extra @{ Reason = 'fixture cleanup'; Evidence = 'Lease rejection checked.' } | Out-Null
    Invoke-FixtureLoop -Action Claim -TaskId 'A0-CORE-995' -Owner 'scope-worker' | Out-Null
    Set-FixtureTaskStatus 'A0-CORE-998' 'done'
    Expect-FixtureFailure 'Reopen refuses overlapping lease' 'overlapping active lease' { Invoke-FixtureLoop -Action Reopen -TaskId 'A0-CORE-998' -Owner 'fixture-coordinator' -Extra $reopenArgs }
    Set-FixtureTaskStatus 'A0-CORE-998' 'ready'
    Invoke-FixtureLoop -Action Release -TaskId 'A0-CORE-995' -Owner 'scope-worker' -Extra @{ Reason = 'fixture cleanup'; Evidence = 'Scope rejection checked.' } | Out-Null
    Set-FixtureTaskStatus 'A0-CORE-903' 'done'
    Invoke-FixtureLoop -Action Reopen -TaskId 'A0-CORE-903' -Owner 'fixture-coordinator' -Extra $reopenArgs | Out-Null
    Assert-True ((Get-FixtureTask 'A0-CORE-903').status -eq 'blocked') 'Reopen must correct done to blocked.'
    $reopenStatePath = Join-Path $fixtureRoot 'docs/governance/loop/state.json'
    $reopenState = ConvertFrom-Json ([IO.File]::ReadAllText($reopenStatePath))
    $reopenEvent = @($reopenState.events)[-1]
    Assert-True ($reopenEvent.action -eq 'Reopen' -and $reopenEvent.previous_status -eq 'done' -and $reopenEvent.blocker_owner -eq 'acceptance-owner' -and $reopenEvent.evidence -match 'Completion evidence was incomplete') 'Reopen audit must retain correction reason and blocker details.'
    Expect-FixtureFailure 'Reopen requires done' 'Reopen requires done status' { Invoke-FixtureLoop -Action Reopen -TaskId 'A0-CORE-903' -Owner 'fixture-coordinator' -Extra $reopenArgs }
    foreach ($required in @('Reason', 'Blocker', 'BlockerOwner', 'NextAction', 'Evidence')) {
        $missing = $reopenArgs.Clone(); $missing[$required] = ''
        Expect-FixtureFailure "Reopen missing $required" 'Reopen requires' { Invoke-FixtureLoop -Action Reopen -TaskId 'A0-CORE-903' -Owner 'fixture-coordinator' -Extra $missing }
    }
    foreach ($boundary in @('AfterIntent', 'AfterHandoff', 'AfterTaskStatus', 'AfterStateFinalize')) {
        Set-FixtureTaskStatus 'A0-CORE-903' 'done'
        $beforeEvents = @((ConvertFrom-Json ([IO.File]::ReadAllText($reopenStatePath))).events).Count
        $faultArgs = $reopenArgs.Clone(); $faultArgs.FaultAt = $boundary
        Expect-FixtureFailure "Reopen $boundary" "Injected test fault at $boundary" { Invoke-FixtureLoop -Action Reopen -TaskId 'A0-CORE-903' -Owner 'fixture-coordinator' -Extra $faultArgs }
        Invoke-FixtureLoop -Action Recover -TaskId 'A0-CORE-903' -Owner 'fixture-coordinator' | Out-Null
        $afterState = ConvertFrom-Json ([IO.File]::ReadAllText($reopenStatePath))
        Assert-True ((Get-FixtureTask 'A0-CORE-903').status -eq 'blocked' -and @($afterState.events).Count -eq ($beforeEvents + 1)) 'Recovery must append exactly one correction and preserve history.'
        Assert-True (Test-Path -LiteralPath (Join-Path $fixtureRoot (@($afterState.events)[-1].handoff_path))) 'Recovered correction must retain its handoff.'
    }
    Set-FixtureTaskStatus 'A0-CORE-903' 'blocked'
    $backlogRows = @('# Fixture backlog', '', '| ID | title | status | predecessors |', '|---|---|---|---|')
    foreach ($task in $tasks) {
        $predecessors = @($task.depends_on) -join ', '
        $backlogRows += '| {0} | {1} | {2} | {3} |' -f $task.id, $task.title, $task.status, $predecessors
    }
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/backlog/BACKLOG.md'), ($backlogRows -join "`n") + "`n", [Text.UTF8Encoding]::new($false))
    $register = @(
        '# Fixture decision register',
        '',
        '| ID | owner | blocker | options | evidence | status |',
        '|---|---|---|---|---|---|',
        '| OPEN-99 | Owner | Test | Alternatives | Evidence | OPEN |',
        ''
    ) -join "`n"
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/governance/OPEN_DECISIONS.md'), $register, [Text.UTF8Encoding]::new($false))
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/architecture/ADR-INDEX.md'), "# Fixture ADR index`n`n| ADR | status | owner |`n|---|---|---|`n| [ADR-001: Reassess fixture] | Proposed | Engineering |`n| [ADR-099: Fixture] | Proposed | Engineering |`n", [Text.UTF8Encoding]::new($false))
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/architecture/adr/ADR-001.md'), "# ADR-001 fixture`n`nStatus: Proposed`n", [Text.UTF8Encoding]::new($false))
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/architecture/adr/ADR-099.md'), "# ADR-099 fixture`n`nStatus: Proposed`n", [Text.UTF8Encoding]::new($false))

    $tasksPath = Join-Path $fixtureRoot 'docs/backlog/tasks.json'
    $readOnlyHash = Get-FixtureHash $tasksPath
    $statusOutput = @(Invoke-FixtureLoop -Action Status)
    Assert-True ((Get-FixtureHash $tasksPath) -eq $readOnlyHash) 'Status must be read-only.'
    $readyOutput = @(Invoke-FixtureLoop -Action Ready)
    Assert-True (($readyOutput -join "`n") -notmatch 'READY A0-CORE-996') 'An unresolved OPEN decision must not be reported ready.'
    Assert-True (($readyOutput -join "`n") -notmatch 'READY A0-CORE-992') 'An unresolved ADR must not be reported ready.'

    # Mutation must also support valid compact JSON task records, as produced by some backlog tools.
    [IO.File]::WriteAllText($tasksPath, (ConvertTo-Json -InputObject ([pscustomobject]@{ schema_version = '1.0'; planning_only = $true; tasks = @($tasks) }) -Depth 20 -Compress) + "`n", [Text.UTF8Encoding]::new($false))
    Invoke-FixtureLoop -Action Claim -TaskId 'A0-CORE-998' -Owner 'compact-json-worker' | Out-Null
    Assert-True ((Get-FixtureTask 'A0-CORE-998').status -eq 'in_progress') 'Compact JSON task records must be claimable.'
    Invoke-FixtureLoop -Action Release -TaskId 'A0-CORE-998' -Owner 'compact-json-worker' -Extra @{ Reason = 'compact JSON fixture'; Evidence = 'Compact JSON mutation verified.' } | Out-Null
    Assert-True ((Get-FixtureTask 'A0-CORE-998').status -eq 'ready') 'Compact JSON release must restore ready status.'
    Write-FixtureTasks $tasks

    # A pre-existing CreateNew lock must block mutation and remain untouched.
    $lockPath = Join-Path $fixtureRoot 'docs/governance/loop/coordinator.lock'
    [void][IO.Directory]::CreateDirectory((Split-Path $lockPath -Parent))
    [IO.File]::WriteAllText($lockPath, 'foreign lock', [Text.Encoding]::UTF8)
    Expect-FixtureFailure 'conflicting coordinator lock' 'Coordinator lock already exists' {
        Invoke-FixtureLoop -Action Claim -TaskId 'A0-CORE-998' -Owner 'alpha-worker'
    }
    Assert-True (Test-Path -LiteralPath $lockPath -PathType Leaf) 'A failed lock contender must not delete another lock.'
    [IO.File]::Delete($lockPath)

    # Dependency readiness is recalculated, not trusted from a stale planned status.
    Expect-FixtureFailure 'unmet dependency' 'dependency A0-CORE-998 is ready' {
        Invoke-FixtureLoop -Action Claim -TaskId 'A0-CORE-997' -Owner 'beta-worker'
    }

    # A Claim interrupted immediately after its durable intent is recovered idempotently.
    $beforeIndependent = ConvertTo-Json -InputObject (Get-FixtureTask 'A0-CORE-995') -Depth 20 -Compress
    $rawBeforeClaim = [IO.File]::ReadAllText($tasksPath, [Text.Encoding]::UTF8)
    $neutralBeforeClaim = [regex]::Replace($rawBeforeClaim, '(?s)("id"\s*:\s*"A0-CORE-998".*?"status"\s*:\s*")[^"]+(")', '$1<status>$2', 1)
    Expect-FixtureFailure 'Claim after intent boundary' 'Injected test fault at AfterIntent' {
        Invoke-FixtureLoop -Action Claim -TaskId 'A0-CORE-998' -Owner 'alpha-worker' -Extra @{ FaultAt = 'AfterIntent' }
    }
    Assert-True ((Get-FixtureTask 'A0-CORE-998').status -eq 'ready') 'AfterIntent fault must not write task status.'
    $pendingPath = Join-Path $fixtureRoot 'docs/governance/loop/pending-transaction.json'
    Assert-True (Test-Path -LiteralPath $pendingPath -PathType Leaf) 'Interrupted Claim must persist a pending journal.'
    Assert-True ((@(Invoke-FixtureLoop -Action Status -TaskId 'A0-CORE-998') -join "`n") -match 'pending-recovery') 'Status must diagnose an unfinished intent.'
    Assert-True ((@(Invoke-FixtureLoop -Action Status) -join "`n") -match 'currently_ready=0') 'A pending transaction must fail closed for new task selection.'
    Expect-FixtureFailure 'wrong recovery owner' "recorded owner 'alpha-worker'" {
        Invoke-FixtureLoop -Action Recover -TaskId 'A0-CORE-998' -Owner 'unauthorized-recovery-worker'
    }
    Assert-True ((Get-FixtureTask 'A0-CORE-998').status -eq 'ready') 'A wrong recovery owner must not change task status.'
    Invoke-FixtureLoop -Action Recover -TaskId 'A0-CORE-998' -Owner 'alpha-worker' | Out-Null
    Assert-True ((Get-FixtureTask 'A0-CORE-998').status -eq 'in_progress') 'Recover must finish the Claim status update.'
    $rawAfterClaim = [IO.File]::ReadAllText($tasksPath, [Text.Encoding]::UTF8)
    $neutralAfterClaim = [regex]::Replace($rawAfterClaim, '(?s)("id"\s*:\s*"A0-CORE-998".*?"status"\s*:\s*")[^"]+(")', '$1<status>$2', 1)
    Assert-True ($neutralBeforeClaim -ceq $neutralAfterClaim) 'Claim recovery must preserve all unrelated UTF-8 task bytes.'
    $claimState = ConvertFrom-Json -InputObject ([IO.File]::ReadAllText((Join-Path $fixtureRoot 'docs/governance/loop/state.json'), [Text.Encoding]::UTF8))
    $claimEvent = @($claimState.events | Where-Object { $_.task_id -eq 'A0-CORE-998' -and $_.action -eq 'Claim' }) | Select-Object -Last 1
    Assert-True (-not [string]::IsNullOrWhiteSpace([string]$claimEvent.handoff_path)) 'Committed event must persist its handoff_path.'
    Assert-True (Test-Path -LiteralPath (Join-Path $fixtureRoot ([string]$claimEvent.handoff_path)) -PathType Leaf) 'Committed Claim event must link to an existing handoff.'
    Expect-FixtureFailure 'module overlap' "module 'Alpha' is leased" {
        Invoke-FixtureLoop -Action Claim -TaskId 'A0-CORE-995' -Owner 'second-alpha-worker'
    }
    Expect-FixtureFailure 'Claim after handoff boundary' 'Injected test fault at AfterHandoff' {
        Invoke-FixtureLoop -Action Claim -TaskId 'A0-CORE-994' -Owner 'beta-worker' -Extra @{ FaultAt = 'AfterHandoff' }
    }
    Assert-True ((Get-FixtureTask 'A0-CORE-994').status -eq 'ready') 'AfterHandoff fault must leave task status unchanged.'
    $pendingEnvelope = ConvertFrom-Json -InputObject ([IO.File]::ReadAllText($pendingPath, [Text.Encoding]::UTF8))
    $pendingPayload = ConvertFrom-Json -InputObject ([string]$pendingEnvelope.payload_json)
    $claimHandoff = [string]$pendingPayload.plans[0].handoff_path
    Assert-True (Test-Path -LiteralPath (Join-Path $fixtureRoot $claimHandoff) -PathType Leaf) 'AfterHandoff fault must leave the recorded handoff artifact.'
    Invoke-FixtureLoop -Action Recover -TaskId 'A0-CORE-994' -Owner 'beta-worker' | Out-Null
    Assert-True ((Get-FixtureTask 'A0-CORE-994').status -eq 'in_progress') 'Recover must accept an already-written matching handoff.'
    Expect-FixtureFailure 'nested path overlap' "overlaps lease A0-CORE-994" {
        Invoke-FixtureLoop -Action Claim -TaskId 'A0-CORE-993' -Owner 'delta-worker'
    }
    Invoke-FixtureLoop -Action Release -TaskId 'A0-CORE-994' -Owner 'beta-worker' -Extra @{ Reason = 'fixture release'; Evidence = 'Scoped test lease released.' } | Out-Null
    Expect-FixtureFailure 'Claim after task-status boundary' 'Injected test fault at AfterTaskStatus' {
        Invoke-FixtureLoop -Action Claim -TaskId 'A0-CORE-994' -Owner 'beta-worker' -Extra @{ FaultAt = 'AfterTaskStatus' }
    }
    Assert-True ((Get-FixtureTask 'A0-CORE-994').status -eq 'in_progress') 'Claim status boundary must retain its unfinished intent.'
    $stateBeforeClaimRecovery = ConvertFrom-Json -InputObject ([IO.File]::ReadAllText((Join-Path $fixtureRoot 'docs/governance/loop/state.json'), [Text.Encoding]::UTF8))
    Assert-True (@($stateBeforeClaimRecovery.leases | Where-Object { $_.task_id -eq 'A0-CORE-994' }).Count -eq 0) 'Claim must not publish its lease before status write is recoverable.'
    Invoke-FixtureLoop -Action Recover -TaskId 'A0-CORE-994' -Owner 'beta-worker' | Out-Null
    $stateAfterClaimRecovery = ConvertFrom-Json -InputObject ([IO.File]::ReadAllText((Join-Path $fixtureRoot 'docs/governance/loop/state.json'), [Text.Encoding]::UTF8))
    Assert-True (@($stateAfterClaimRecovery.leases | Where-Object { $_.task_id -eq 'A0-CORE-994' }).Count -eq 1) 'Recover must persist Claim lease after task status.'
    Invoke-FixtureLoop -Action Release -TaskId 'A0-CORE-994' -Owner 'beta-worker' -Extra @{ Reason = 'fixture release'; Evidence = 'Claim status boundary recovered.' } | Out-Null

    Expect-FixtureFailure 'Claim after state-finalize boundary' 'Injected test fault at AfterStateFinalize' {
        Invoke-FixtureLoop -Action Claim -TaskId 'A0-CORE-993' -Owner 'delta-worker' -Extra @{ FaultAt = 'AfterStateFinalize' }
    }
    Assert-True ((Get-FixtureTask 'A0-CORE-993').status -eq 'in_progress') 'AfterStateFinalize fault must have the planned status.'
    $stateBeforeCleanup = ConvertFrom-Json -InputObject ([IO.File]::ReadAllText((Join-Path $fixtureRoot 'docs/governance/loop/state.json'), [Text.Encoding]::UTF8))
    $eventCountBeforeCleanup = @($stateBeforeCleanup.events).Count
    Invoke-FixtureLoop -Action Recover -TaskId 'A0-CORE-993' -Owner 'delta-worker' | Out-Null
    $stateAfterCleanup = ConvertFrom-Json -InputObject ([IO.File]::ReadAllText((Join-Path $fixtureRoot 'docs/governance/loop/state.json'), [Text.Encoding]::UTF8))
    Assert-True (@($stateAfterCleanup.events).Count -eq $eventCountBeforeCleanup) 'Recover after state finalization must not duplicate the event.'
    Invoke-FixtureLoop -Action Release -TaskId 'A0-CORE-993' -Owner 'delta-worker' -Extra @{ Reason = 'fixture release'; Evidence = 'Finalized claim recovered idempotently.' } | Out-Null

    Invoke-FixtureLoop -Action Review -TaskId 'A0-CORE-998' -Owner 'alpha-worker' -Extra @{ Evidence = 'Fixture review evidence.' } | Out-Null
    Expect-FixtureFailure 'Complete after task-status boundary' 'Injected test fault at AfterTaskStatus' {
        Invoke-FixtureLoop -Action Complete -TaskId 'A0-CORE-998' -Owner 'alpha-worker' -Extra @{ Reviewer = 'independent-reviewer'; Evidence = 'Reviewed fixture acceptance evidence.'; FaultAt = 'AfterTaskStatus' }
    }
    Assert-True ((Get-FixtureTask 'A0-CORE-998').status -eq 'done') 'Injected Complete boundary must expose a pending done status for recovery.'
    $stateDuringComplete = ConvertFrom-Json -InputObject ([IO.File]::ReadAllText((Join-Path $fixtureRoot 'docs/governance/loop/state.json'), [Text.Encoding]::UTF8))
    Assert-True (@($stateDuringComplete.leases | Where-Object { $_.task_id -eq 'A0-CORE-998' }).Count -eq 1) 'Interrupted Complete must retain its lease until state finalization.'
    Assert-True ((@(Invoke-FixtureLoop -Action Status -TaskId 'A0-CORE-998') -join "`n") -match 'pending-recovery') 'Interrupted Complete must be reported as not finalized.'
    Invoke-FixtureLoop -Action Recover -TaskId 'A0-CORE-998' -Owner 'alpha-worker' | Out-Null
    Assert-True ((Get-FixtureTask 'A0-CORE-998').status -eq 'done') 'Recover must finalize Complete when prerequisites remain satisfied.'
    $completedState = ConvertFrom-Json -InputObject ([IO.File]::ReadAllText((Join-Path $fixtureRoot 'docs/governance/loop/state.json'), [Text.Encoding]::UTF8))
    $completeEvent = @($completedState.events | Where-Object { $_.task_id -eq 'A0-CORE-998' -and $_.action -eq 'Complete' }) | Select-Object -Last 1
    Assert-True (-not [string]::IsNullOrWhiteSpace([string]$completeEvent.handoff_path)) 'Complete event must persist its handoff_path.'
    Assert-True (Test-Path -LiteralPath (Join-Path $fixtureRoot ([string]$completeEvent.handoff_path)) -PathType Leaf) 'Complete event must link to its recorded handoff evidence.'
    $afterIndependent = ConvertTo-Json -InputObject (Get-FixtureTask 'A0-CORE-995') -Depth 20 -Compress
    Assert-True ($beforeIndependent -ceq $afterIndependent) 'Unrelated JSON task content must remain semantically unchanged.'
    Assert-True ((Get-FixtureTask 'A0-CORE-998').title -eq $script:arabicTitle) 'Arabic title must survive status mutation.'
    Assert-True ((Get-FixtureTask 'A0-CORE-998').unrelated.keep -eq $script:arabicValue) 'Unrelated nested Arabic JSON must survive status mutation.'
    $rawTasks = [IO.File]::ReadAllText($tasksPath, [Text.Encoding]::UTF8)
    Assert-True ($rawTasks.Contains($script:arabicTitle)) 'Raw UTF-8 backlog text must remain readable.'

    # The predecessor is now done, so the dependent becomes claimable.
    Invoke-FixtureLoop -Action Claim -TaskId 'A0-CORE-997' -Owner 'beta-worker' | Out-Null
    Invoke-FixtureLoop -Action Release -TaskId 'A0-CORE-997' -Owner 'beta-worker' -Extra @{ Reason = 'fixture handoff'; Evidence = 'Dependency completion verified.' } | Out-Null
    Assert-True ((Get-FixtureTask 'A0-CORE-997').status -eq 'ready') 'Release must recalculate readiness after predecessor completion.'

    # An incorrectly saved ready status with an unresolved decision must fail closed.
    Set-FixtureTaskStatus 'A0-CORE-996' 'ready'
    Expect-FixtureFailure 'unresolved decision' 'ready with unresolved decision OPEN-99' {
        Invoke-FixtureLoop -Action Claim -TaskId 'A0-CORE-996' -Owner 'gamma-worker'
    }
    Set-FixtureTaskStatus 'A0-CORE-996' 'planned'
    $candidateOutput = @(Invoke-FixtureLoop -Action Ready)
    Assert-True (($candidateOutput -join "`n") -notmatch 'READY A0-CORE-996') 'Unresolved decision must remain unavailable in ready report.'

    # A valid, separate approval record plus existing evidence is accepted by the verifier.
    $approvalDir = Join-Path $fixtureRoot 'docs/governance/decision-approvals'
    [void][IO.Directory]::CreateDirectory($approvalDir)
    $evidencePath = Join-Path $fixtureRoot 'docs/governance/evidence/OPEN-99.md'
    [IO.File]::WriteAllText($evidencePath, '# Fixture evidence', [Text.UTF8Encoding]::new($false))
    $approvalRecord = @(
        '# Fixture decision approval',
        'Decision: Approved',
        'Owner: fixture-business-owner',
        'Approved on: 2026-01-15',
        'Approved option: explicit fixture option',
        'Evidence: docs/governance/evidence/OPEN-99.md',
        'Affected tasks: A0-CORE-996',
        'Tests: fixture verification command',
        'Review: independent fixture review'
    ) -join "`n"
    [IO.File]::WriteAllText((Join-Path $approvalDir 'OPEN-99.md'), $approvalRecord, [Text.UTF8Encoding]::new($false))
    $approvedRegister = $register.Replace('| OPEN-99 | Owner | Test | Alternatives | Evidence | OPEN |', '| OPEN-99 | Owner | Test | Alternatives | Evidence | Approved |')
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/governance/OPEN_DECISIONS.md'), $approvedRegister, [Text.UTF8Encoding]::new($false))
    $approvedOutput = @(Invoke-FixtureLoop -Action Ready)
    Assert-True (($approvedOutput -join "`n") -match 'READY A0-CORE-996') 'A fully evidenced explicit approval must unblock readiness.'
    Invoke-FixtureLoop -Action Claim -TaskId 'A0-CORE-996' -Owner 'gamma-worker' | Out-Null
    Invoke-FixtureLoop -Action Release -TaskId 'A0-CORE-996' -Owner 'gamma-worker' -Extra @{ Reason = 'fixture test complete'; Evidence = 'Explicit approval record was validated.' } | Out-Null

    Set-FixtureTaskStatus 'A0-CORE-992' 'planned'
    Assert-True ((@(Invoke-FixtureLoop -Action Ready) -join "`n") -notmatch 'READY A0-CORE-992') 'ADR status without acceptance evidence must remain unavailable.'
    $adrEvidencePath = Join-Path $fixtureRoot 'docs/architecture/adr/ADR-099-evidence.md'
    [IO.File]::WriteAllText($adrEvidencePath, '# Fixture ADR acceptance evidence', [Text.UTF8Encoding]::new($false))
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/architecture/ADR-INDEX.md'), "# Fixture ADR index`n`n| ADR | status | owner |`n|---|---|---|`n| [ADR-099: Fixture] | Accepted | Engineering |`n", [Text.UTF8Encoding]::new($false))
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/architecture/adr/ADR-099.md'), "# ADR-099 fixture`n`nStatus: Accepted`n", [Text.UTF8Encoding]::new($false))
    $adrApproval = @(
        '# Fixture ADR acceptance record',
        'Decision: Accepted',
        'Owner: fixture-adr-owner',
        'Approved on: 2026-01-16',
        'Approved option: explicit fixture architecture option',
        'Evidence: docs/architecture/adr/ADR-099-evidence.md',
        'Affected tasks: A0-CORE-992',
        'Tests: fixture ADR verification',
        'Review: independent fixture review'
    ) -join "`n"
    [IO.File]::WriteAllText((Join-Path $approvalDir 'ADR-099.md'), $adrApproval, [Text.UTF8Encoding]::new($false))
    Assert-True ((@(Invoke-FixtureLoop -Action Ready) -join "`n") -match 'READY A0-CORE-992') 'A fully evidenced Accepted ADR must unblock readiness.'
    Invoke-FixtureLoop -Action Claim -TaskId 'A0-CORE-992' -Owner 'architecture-worker' | Out-Null
    Invoke-FixtureLoop -Action Review -TaskId 'A0-CORE-992' -Owner 'architecture-worker' -Extra @{ Evidence = 'Fixture ADR implementation review.' } | Out-Null
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/architecture/ADR-INDEX.md'), "# Fixture ADR index`n`n| ADR | status | owner |`n|---|---|---|`n| [ADR-099: Fixture] | Proposed | Engineering |`n", [Text.UTF8Encoding]::new($false))
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/architecture/adr/ADR-099.md'), "# ADR-099 fixture`n`nStatus: Proposed`n", [Text.UTF8Encoding]::new($false))
    Expect-FixtureFailure 'Complete with newly unresolved ADR' 'Cannot complete A0-CORE-992: prerequisites must still be satisfied' {
        Invoke-FixtureLoop -Action Complete -TaskId 'A0-CORE-992' -Owner 'architecture-worker' -Extra @{ Reviewer = 'independent-reviewer'; Evidence = 'Must be rejected while ADR is unresolved.' }
    }
    Assert-True ((Get-FixtureTask 'A0-CORE-992').status -eq 'review') 'Unresolved decision must not transition implementation task to done.'
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/architecture/ADR-INDEX.md'), "# Fixture ADR index`n`n| ADR | status | owner |`n|---|---|---|`n| [ADR-099: Fixture] | Accepted | Engineering |`n", [Text.UTF8Encoding]::new($false))
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/architecture/adr/ADR-099.md'), "# ADR-099 fixture`n`nStatus: Accepted`n", [Text.UTF8Encoding]::new($false))
    Expect-FixtureFailure 'Complete fault after task status' 'Injected test fault at AfterTaskStatus' {
        Invoke-FixtureLoop -Action Complete -TaskId 'A0-CORE-992' -Owner 'architecture-worker' -Extra @{ Reviewer = 'independent-reviewer'; Evidence = 'Crash-recovery fixture.'; FaultAt = 'AfterTaskStatus' }
    }
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/architecture/ADR-INDEX.md'), "# Fixture ADR index`n`n| ADR | status | owner |`n|---|---|---|`n| [ADR-099: Fixture] | Proposed | Engineering |`n", [Text.UTF8Encoding]::new($false))
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/architecture/adr/ADR-099.md'), "# ADR-099 fixture`n`nStatus: Proposed`n", [Text.UTF8Encoding]::new($false))
    Invoke-FixtureLoop -Action Recover -TaskId 'A0-CORE-992' -Owner 'architecture-worker' | Out-Null
    Assert-True ((Get-FixtureTask 'A0-CORE-992').status -eq 'review') 'Recovery must roll back an unfinalized done status when its ADR becomes unresolved.'
    $rollbackState = ConvertFrom-Json -InputObject ([IO.File]::ReadAllText((Join-Path $fixtureRoot 'docs/governance/loop/state.json'), [Text.Encoding]::UTF8))
    Assert-True (@($rollbackState.leases | Where-Object { $_.task_id -eq 'A0-CORE-992' }).Count -eq 1) 'Rollback recovery must retain the original task lease.'
    Assert-True (@($rollbackState.events | Where-Object { $_.task_id -eq 'A0-CORE-992' -and $_.action -eq 'CompleteCancelled' }).Count -eq 1) 'Rollback recovery must record a separate cancellation event.'
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/architecture/ADR-INDEX.md'), "# Fixture ADR index`n`n| ADR | status | owner |`n|---|---|---|`n| [ADR-099: Fixture] | Accepted | Engineering |`n", [Text.UTF8Encoding]::new($false))
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/architecture/adr/ADR-099.md'), "# ADR-099 fixture`n`nStatus: Accepted`n", [Text.UTF8Encoding]::new($false))
    Invoke-FixtureLoop -Action Complete -TaskId 'A0-CORE-992' -Owner 'architecture-worker' -Extra @{ Reviewer = 'independent-reviewer'; Evidence = 'Completed after explicit ADR evidence was restored.' } | Out-Null
    Assert-True ((Get-FixtureTask 'A0-CORE-992').status -eq 'done') 'A later, separately reviewed Complete may proceed when prerequisites are restored.'

    # Reassess is a generic audited transition, using only accepted fixture evidence.
    $statePath = Join-Path $fixtureRoot 'docs/governance/loop/state.json'
    $reassessTaskId = 'A0-CORE-900'
    $stateBeforeUnready = Get-FixtureHash $statePath
    $tasksBeforeUnready = Get-FixtureHash $tasksPath
    Invoke-FixtureLoop -Action Reassess -TaskId $reassessTaskId -Owner 'fixture-coordinator' -Extra @{ Reason = 'Verify gates before resuming.'; Evidence = 'Fixture readiness was checked.' } | Out-Null
    Assert-True ((Get-FixtureHash $tasksPath) -ceq $tasksBeforeUnready) 'Unapproved ADR must leave task bytes unchanged.'
    Assert-True ((Get-FixtureHash $statePath) -ceq $stateBeforeUnready) 'Unapproved ADR must not add an audit transition.'
    Assert-True ((Get-FixtureTask $reassessTaskId).status -eq 'blocked') 'Unapproved ADR must leave saved blocked status.'

    $approvalDir = Join-Path $fixtureRoot 'docs/governance/decision-approvals'
    [void][IO.Directory]::CreateDirectory($approvalDir)
    $adr001Evidence = Join-Path $fixtureRoot 'docs/architecture/adr/ADR-001-evidence.md'
    [IO.File]::WriteAllText($adr001Evidence, '# Distinct fixture acceptance evidence', [Text.UTF8Encoding]::new($false))
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/architecture/ADR-INDEX.md'), "# Fixture ADR index`n`n| ADR | status | owner |`n|---|---|---|`n| [ADR-001: Reassess fixture] | Accepted | Engineering |`n| [ADR-099: Fixture] | Accepted | Engineering |`n", [Text.UTF8Encoding]::new($false))
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/architecture/adr/ADR-001.md'), "# ADR-001 fixture`n`nStatus: Accepted`n", [Text.UTF8Encoding]::new($false))
    $adr001Approval = @('# Fixture ADR-001 acceptance record', 'Decision: Accepted', 'Owner: fixture-adr-owner', 'Approved on: 2026-01-16', 'Approved option: explicit distinct fixture option', 'Evidence: docs/architecture/adr/ADR-001-evidence.md', 'Affected tasks: A0-CORE-900', 'Tests: fixture ADR-001 verification', 'Review: independent fixture review') -join "`n"
    [IO.File]::WriteAllText((Join-Path $approvalDir 'ADR-001.md'), $adr001Approval, [Text.UTF8Encoding]::new($false))
    Invoke-FixtureLoop -Action Reassess -TaskId $reassessTaskId -Owner 'fixture-coordinator' -Extra @{ Reason = 'Verify gates before resuming.'; Evidence = 'ADR-001 is accepted with a separate evidence artifact.' } | Out-Null
    Assert-True ((Get-FixtureTask $reassessTaskId).status -eq 'blocked') 'Incomplete predecessor must keep reassessed task blocked.'
    Set-FixtureTaskStatus 'A0-CORE-901' 'done'
    $rawBeforeReassess = [IO.File]::ReadAllText($tasksPath, [Text.Encoding]::UTF8)
    $neutralBeforeReassess = [regex]::Replace($rawBeforeReassess, '(?s)("id"\s*:\s*"A0-CORE-900".*?"status"\s*:\s*")[^"]+(")', '$1<status>$2', 1)
    Invoke-FixtureLoop -Action Reassess -TaskId $reassessTaskId -Owner 'fixture-coordinator' -Extra @{ Reason = 'All gates explicitly verified.'; Evidence = 'ADR-001 accepted; predecessor A0-CORE-901 is done.' } | Out-Null
    Assert-True ((Get-FixtureTask $reassessTaskId).status -eq 'ready') 'Fully satisfied gates must transition blocked to ready.'
    $rawAfterReassess = [IO.File]::ReadAllText($tasksPath, [Text.Encoding]::UTF8)
    $neutralAfterReassess = [regex]::Replace($rawAfterReassess, '(?s)("id"\s*:\s*"A0-CORE-900".*?"status"\s*:\s*")[^"]+(")', '$1<status>$2', 1)
    Assert-True ($neutralBeforeReassess -ceq $neutralAfterReassess) 'Reassess must preserve dependencies and non-status task bytes.'
    $stateAfterReassess = ConvertFrom-Json -InputObject ([IO.File]::ReadAllText($statePath, [Text.Encoding]::UTF8))
    $reassessEvent = @($stateAfterReassess.events | Where-Object { $_.task_id -eq $reassessTaskId -and $_.action -eq 'Reassess' }) | Select-Object -Last 1
    Assert-True ($reassessEvent.owner -ceq 'fixture-coordinator' -and $reassessEvent.evidence -match 'All gates explicitly verified') 'Reassess audit event must record actor, reason, and evidence.'
    Assert-True (Test-Path -LiteralPath (Join-Path $fixtureRoot ([string]$reassessEvent.handoff_path)) -PathType Leaf) 'Reassess must persist an event-linked handoff.'
    $tasksAfterReady = Get-FixtureHash $tasksPath
    $stateAfterReady = Get-FixtureHash $statePath
    Invoke-FixtureLoop -Action Reassess -TaskId $reassessTaskId -Owner 'fixture-coordinator' -Extra @{ Reason = 'Repeat should be idempotent.'; Evidence = 'No new transition expected.' } | Out-Null
    Assert-True ((Get-FixtureHash $tasksPath) -ceq $tasksAfterReady -and (Get-FixtureHash $statePath) -ceq $stateAfterReady) 'Repeated Reassess on ready task must not mutate files or duplicate history.'

    Set-FixtureTaskStatus $reassessTaskId 'blocked'
    $stateWithLease = ConvertFrom-Json -InputObject ([IO.File]::ReadAllText($statePath, [Text.Encoding]::UTF8))
    $stateWithLease.leases = @([pscustomobject]@{ lease_id = 'expired-fixture-lease'; task_id = $reassessTaskId; owner = 'old-owner'; expires_utc = '2000-01-01T00:00:00Z'; scopes = [pscustomobject]@{ paths = @('docs/reassess.md'); modules = @('ReassessTarget') } })
    [IO.File]::WriteAllText($statePath, (ConvertTo-Json -InputObject $stateWithLease -Depth 30) + "`n", [Text.UTF8Encoding]::new($false))
    $leasedTaskHash = Get-FixtureHash $tasksPath
    $leasedStateHash = Get-FixtureHash $statePath
    Expect-FixtureFailure 'Reassess with expired lease' 'while any lease exists' {
        Invoke-FixtureLoop -Action Reassess -TaskId $reassessTaskId -Owner 'fixture-coordinator' -Extra @{ Reason = 'lease test'; Evidence = 'must remain untouched' }
    }
    Assert-True ((Get-FixtureHash $tasksPath) -ceq $leasedTaskHash -and (Get-FixtureHash $statePath) -ceq $leasedStateHash) 'Any lease, including expired, must block Reassess without mutation.'
    $stateWithLease.leases = @()
    [IO.File]::WriteAllText($statePath, (ConvertTo-Json -InputObject $stateWithLease -Depth 30) + "`n", [Text.UTF8Encoding]::new($false))
    Set-FixtureTaskStatus $reassessTaskId 'in_progress'
    Expect-FixtureFailure 'Reassess active task without lease' 'requires blocked status' {
        Invoke-FixtureLoop -Action Reassess -TaskId $reassessTaskId -Owner 'fixture-coordinator' -Extra @{ Reason = 'active status test'; Evidence = 'must fail closed' }
    }
    Set-FixtureTaskStatus $reassessTaskId 'blocked'

    foreach ($fault in @('AfterIntent', 'AfterHandoff', 'AfterTaskStatus', 'AfterStateFinalize')) {
        Set-FixtureTaskStatus $reassessTaskId 'blocked'
        Expect-FixtureFailure "Reassess $fault" "Injected test fault at $fault" {
            Invoke-FixtureLoop -Action Reassess -TaskId $reassessTaskId -Owner 'fixture-coordinator' -Extra @{ Reason = "crash boundary $fault"; Evidence = 'fixture recovery path'; FaultAt = $fault }
        }
        Invoke-FixtureLoop -Action Recover -TaskId $reassessTaskId -Owner 'fixture-coordinator' | Out-Null
        Assert-True ((Get-FixtureTask $reassessTaskId).status -eq 'ready') "Recovery at $fault must leave a ready task when all gates remain satisfied."
    }
    Set-FixtureTaskStatus $reassessTaskId 'blocked'
    Expect-FixtureFailure 'Reassess gate-revoke boundary' 'Injected test fault at AfterTaskStatus' {
        Invoke-FixtureLoop -Action Reassess -TaskId $reassessTaskId -Owner 'fixture-coordinator' -Extra @{ Reason = 'test pending gate revocation'; Evidence = 'fixture recovery cancellation'; FaultAt = 'AfterTaskStatus' }
    }
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/architecture/ADR-INDEX.md'), "# Fixture ADR index`n`n| ADR | status | owner |`n|---|---|---|`n| [ADR-001: Reassess fixture] | Proposed | Engineering |`n| [ADR-099: Fixture] | Accepted | Engineering |`n", [Text.UTF8Encoding]::new($false))
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/architecture/adr/ADR-001.md'), "# ADR-001 fixture`n`nStatus: Proposed`n", [Text.UTF8Encoding]::new($false))
    Expect-FixtureFailure 'Reassess cancellation state boundary' 'Injected test fault at AfterStateFinalize' {
        Invoke-FixtureLoop -Action Recover -TaskId $reassessTaskId -Owner 'fixture-coordinator' -Extra @{ FaultAt = 'AfterStateFinalize' }
    }
    Assert-True ((Get-FixtureTask $reassessTaskId).status -eq 'blocked') 'Recovery must cancel Reassess if ADR acceptance is revoked before state finalization.'
    $cancelledReassessState = ConvertFrom-Json -InputObject ([IO.File]::ReadAllText($statePath, [Text.Encoding]::UTF8))
    $cancelledEventCount = @($cancelledReassessState.events | Where-Object { $_.task_id -eq $reassessTaskId -and $_.action -eq 'ReassessCancelled' }).Count
    Assert-True ($cancelledEventCount -eq 1) 'Revoked readiness must produce an explicit cancellation audit event.'
    Invoke-FixtureLoop -Action Recover -TaskId $reassessTaskId -Owner 'fixture-coordinator' | Out-Null
    $cancelledReassessState = ConvertFrom-Json -InputObject ([IO.File]::ReadAllText($statePath, [Text.Encoding]::UTF8))
    Assert-True (@($cancelledReassessState.events | Where-Object { $_.task_id -eq $reassessTaskId -and $_.action -eq 'ReassessCancelled' }).Count -eq $cancelledEventCount) 'Recovery after cancellation finalization must not duplicate its audit event.'

    # Block retains the lease; explicit release records the resolution and restores readiness.
    Invoke-FixtureLoop -Action Claim -TaskId 'A0-CORE-995' -Owner 'alpha-two' | Out-Null
    Invoke-FixtureLoop -Action Block -TaskId 'A0-CORE-995' -Owner 'alpha-two' -Extra @{ Blocker = 'fixture blocker'; BlockerOwner = 'fixture-owner'; NextAction = 'confirm test'; Evidence = 'Test a blocked lease.' } | Out-Null
    Assert-True ((Get-FixtureTask 'A0-CORE-995').status -eq 'blocked') 'Block must persist blocked status.'
    Invoke-FixtureLoop -Action Release -TaskId 'A0-CORE-995' -Owner 'alpha-two' -Extra @{ Reason = 'fixture blocker cleared'; Evidence = 'Fixture resolution recorded.' } | Out-Null
    Assert-True ((Get-FixtureTask 'A0-CORE-995').status -eq 'ready') 'Release must restore ready status when prerequisites pass.'

    Invoke-FixtureLoop -Action RefreshBacklog | Out-Null
    $snapshot = [IO.File]::ReadAllText((Join-Path $fixtureRoot 'docs/backlog/BACKLOG.md'), [Text.Encoding]::UTF8)
    Assert-True ($snapshot.Contains('| A0-CORE-998 | ' + $script:arabicTitle + ' | done |')) 'RefreshBacklog must mirror task status into the generated snapshot.'
    Assert-True ($snapshot.Contains('| A0-CORE-997 | Dependent task | ready |')) 'RefreshBacklog must keep dependent task status consistent.'

    $statePath = Join-Path $fixtureRoot 'docs/governance/loop/state.json'
    $state = ConvertFrom-Json -InputObject ([IO.File]::ReadAllText($statePath, [Text.Encoding]::UTF8))
    Assert-True (@($state.leases).Count -eq 0) 'All completed/released fixture leases must be removed.'
    Assert-True (@($state.events).Count -ge 10) 'Transitions must be persisted in loop state.'
    $handoffs = @(Get-ChildItem -LiteralPath (Join-Path $fixtureRoot 'docs/governance/loop/handoffs') -Filter '*.md' -File)
    Assert-True ($handoffs.Count -ge 8) 'Handoff evidence must be stored separately from state.'

    # Reassess recovery must retain intent on concurrent task or state hashes.
    Expect-FixtureFailure 'Reassess concurrent task boundary' 'Injected test fault at AfterIntent' {
        Invoke-FixtureLoop -Action Reassess -TaskId 'A0-CORE-902' -Owner 'fixture-coordinator' -Extra @{ Reason = 'stale task test'; Evidence = 'fixture concurrent edit'; FaultAt = 'AfterIntent' }
    }
    $concurrentTaskData = ConvertFrom-Json -InputObject ([IO.File]::ReadAllText($tasksPath, [Text.Encoding]::UTF8))
    $concurrentTask = @($concurrentTaskData.tasks | Where-Object { $_.id -eq 'A0-CORE-902' }) | Select-Object -First 1
    $concurrentTask.agent_notes = 'Preserve concurrent task edit.'
    [IO.File]::WriteAllText($tasksPath, (ConvertTo-Json -InputObject $concurrentTaskData -Depth 20) + "`n", [Text.UTF8Encoding]::new($false))
    $concurrentTaskHash = Get-FixtureHash $tasksPath
    Expect-FixtureFailure 'Reassess recovery with stale task hash' 'tasks.json hash matches neither' {
        Invoke-FixtureLoop -Action Recover -TaskId 'A0-CORE-902' -Owner 'fixture-coordinator'
    }
    Assert-True ((Get-FixtureHash $tasksPath) -ceq $concurrentTaskHash) 'Reassess recovery must preserve concurrent task edits.'
    Assert-True (Test-Path -LiteralPath $pendingPath -PathType Leaf) 'Stale task hash must retain Reassess journal.'
    [IO.File]::Delete($pendingPath)

    Expect-FixtureFailure 'Reassess concurrent state boundary' 'Injected test fault at AfterIntent' {
        Invoke-FixtureLoop -Action Reassess -TaskId 'A0-CORE-903' -Owner 'fixture-coordinator' -Extra @{ Reason = 'stale state test'; Evidence = 'fixture concurrent state edit'; FaultAt = 'AfterIntent' }
    }
    $concurrentState = ConvertFrom-Json -InputObject ([IO.File]::ReadAllText($statePath, [Text.Encoding]::UTF8))
    $concurrentState.revision = [int]$concurrentState.revision + 1
    $concurrentState.updated_utc = '2026-01-17T00:00:00.0000000Z'
    [IO.File]::WriteAllText($statePath, (ConvertTo-Json -InputObject $concurrentState -Depth 30) + "`n", [Text.UTF8Encoding]::new($false))
    $concurrentStateHash = Get-FixtureHash $statePath
    Expect-FixtureFailure 'Reassess recovery with stale state hash' 'Loop state hash matches neither' {
        Invoke-FixtureLoop -Action Recover -TaskId 'A0-CORE-903' -Owner 'fixture-coordinator'
    }
    Assert-True ((Get-FixtureHash $statePath) -ceq $concurrentStateHash) 'Reassess recovery must preserve concurrent state edits.'
    Assert-True (Test-Path -LiteralPath $pendingPath -PathType Leaf) 'Stale state hash must retain Reassess journal.'
    [IO.File]::Delete($pendingPath)

    # A foreign edit after intent is retained; recovery refuses to overwrite its unknown hash.
    Expect-FixtureFailure 'unknown edit claim boundary' 'Injected test fault at AfterIntent' {
        Invoke-FixtureLoop -Action Claim -TaskId 'A0-CORE-995' -Owner 'edit-test-worker' -Extra @{ FaultAt = 'AfterIntent' }
    }
    $editedData = ConvertFrom-Json -InputObject ([IO.File]::ReadAllText($tasksPath, [Text.Encoding]::UTF8))
    $editedTask = @($editedData.tasks | Where-Object { $_.id -eq 'A0-CORE-995' }) | Select-Object -First 1
    $editedTask.agent_notes = 'Concurrent fixture edit must be preserved.'
    $editedText = (ConvertTo-Json -InputObject $editedData -Depth 20) + "`n"
    [IO.File]::WriteAllText($tasksPath, $editedText, [Text.UTF8Encoding]::new($false))
    $foreignEditHash = Get-FixtureHash $tasksPath
    Expect-FixtureFailure 'recovery with unknown tasks edit' 'tasks.json hash matches neither' {
        Invoke-FixtureLoop -Action Recover -TaskId 'A0-CORE-995' -Owner 'edit-test-worker'
    }
    Assert-True ((Get-FixtureHash $tasksPath) -ceq $foreignEditHash) 'Recovery must preserve unknown concurrent task edits byte-for-byte.'
    Assert-True (Test-Path -LiteralPath $pendingPath -PathType Leaf) 'Recovery must retain the intent for manual diagnosis after an unknown edit.'

    Write-Output ("PASS: task-loop fixtures; assertions covered locks, scope overlap, prerequisites, decision evidence, intent/handoff/status/state crash recovery, rollback, unknown edits, UTF-8 preservation, and event-linked handoffs ({0} events)." -f @($state.events).Count)
} finally {
    $fullTemp = [IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd([char[]]@('\', '/')) + [IO.Path]::DirectorySeparatorChar
    $fullFixture = [IO.Path]::GetFullPath($fixtureRoot)
    if ($fullFixture.StartsWith($fullTemp, [StringComparison]::OrdinalIgnoreCase) -and
        [IO.Path]::GetFileName($fullFixture).StartsWith('my-arasya-task-loop-tests-', [StringComparison]::Ordinal) -and
        -not $fullFixture.Equals([IO.Path]::GetFullPath((Split-Path $PSScriptRoot -Parent)), [StringComparison]::OrdinalIgnoreCase) -and
        (Test-Path -LiteralPath $fullFixture -PathType Container)) {
        [IO.Directory]::Delete($fullFixture, $true)
    }
}
