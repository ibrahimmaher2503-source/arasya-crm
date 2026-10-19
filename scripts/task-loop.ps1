[CmdletBinding()]
param(
    [ValidateSet('Status', 'Ready', 'Claim', 'Renew', 'Review', 'Complete', 'Block', 'Release', 'Reassess', 'Reopen', 'Recover', 'RefreshBacklog')]
    [string]$Action = 'Status',
    [string]$TaskId,
    [string]$Owner,
    [string]$Reviewer,
    [string]$Evidence,
    [string]$Blocker,
    [string]$BlockerOwner,
    [string]$NextAction,
    [string]$Reason,
    [ValidateSet('AfterIntent', 'AfterHandoff', 'AfterTaskStatus', 'AfterStateFinalize')][string]$FaultAt,
    [ValidateRange(1, 168)][int]$LeaseHours = 12,
    [string]$Root = (Split-Path $PSScriptRoot -Parent)
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'decision-evidence.ps1')

function ConvertFrom-LoopJson {
    param([string]$InputObject)
    # PowerShell 7.5+ otherwise coerces ISO strings to DateTime and changes intent bytes.
    $options = @{ InputObject = $InputObject; ErrorAction = 'Stop' }
    if ((Get-Command ConvertFrom-Json).Parameters.ContainsKey('DateKind')) { $options.DateKind = 'String' }
    return ConvertFrom-Json @options
}
function Get-Utf8Bytes([string]$Text, [bool]$WithBom) {
    $encoding = [Text.UTF8Encoding]::new($false, $true)
    $body = $encoding.GetBytes($Text)
    if (-not $WithBom) { return ,$body }
    $preamble = [Text.UTF8Encoding]::new($true).GetPreamble()
    $combined = [byte[]]::new($preamble.Length + $body.Length)
    [Array]::Copy($preamble, 0, $combined, 0, $preamble.Length)
    [Array]::Copy($body, 0, $combined, $preamble.Length, $body.Length)
    return ,$combined
}

function Get-BytesSha256([byte[]]$Bytes) {
    $algorithm = [Security.Cryptography.SHA256]::Create()
    try { return [BitConverter]::ToString($algorithm.ComputeHash($Bytes)).Replace('-', '').ToLowerInvariant() }
    finally { $algorithm.Dispose() }
}

function Get-FileSha256([string]$Path) {
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) { return $null }
    return Get-BytesSha256 ([IO.File]::ReadAllBytes($Path))
}

function Read-Utf8File([string]$Path) {
    $bytes = [IO.File]::ReadAllBytes($Path)
    $hasBom = $bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF
    $offset = if ($hasBom) { 3 } else { 0 }
    $encoding = [Text.UTF8Encoding]::new($false, $true)
    try { $text = $encoding.GetString($bytes, $offset, $bytes.Length - $offset) }
    catch { throw "Invalid UTF-8 file '$Path': $($_.Exception.Message)" }
    return [pscustomobject]@{ Text = $text; Bom = [bool]$hasBom; Hash = (Get-BytesSha256 $bytes) }
}

function Write-AtomicUtf8File([string]$Path, [string]$Text, [bool]$Bom, [string]$ExpectedHash) {
    $directory = [IO.Path]::GetDirectoryName([IO.Path]::GetFullPath($Path))
    if (-not (Test-Path -LiteralPath $directory -PathType Container)) { throw "Destination directory does not exist: $directory" }
    $tempPath = Join-Path $directory ('.{0}.{1}.tmp' -f [IO.Path]::GetFileName($Path), [guid]::NewGuid().ToString('N'))
    $backupPath = $null
    $commitVerified = $false
    $stream = $null
    try {
        $bytes = Get-Utf8Bytes $Text $Bom
        $stream = [IO.File]::Open($tempPath, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write, [IO.FileShare]::None)
        $stream.Write($bytes, 0, $bytes.Length)
        $stream.Flush($true)
        $stream.Dispose()
        $stream = $null

        $currentHash = Get-FileSha256 $Path
        $newHash = Get-BytesSha256 $bytes
        if ([string]::IsNullOrEmpty($ExpectedHash)) {
            if ($currentHash -ne $null) { throw "Stale write refused; destination appeared after read: $Path" }
            [IO.File]::Move($tempPath, $Path)
            if ((Get-FileSha256 $Path) -cne $newHash) { throw "Atomic create content hash mismatch: $Path" }
            $commitVerified = $true
        } else {
            if ($currentHash -eq $null -or $currentHash -cne $ExpectedHash) { throw "Stale write refused; file changed after read: $Path (expected=$ExpectedHash; actual=$currentHash)" }
            $backupPath = Join-Path $directory ('.{0}.{1}.bak' -f [IO.Path]::GetFileName($Path), [guid]::NewGuid().ToString('N'))
            [IO.File]::Replace($tempPath, $Path, $backupPath)
            if ((Get-FileSha256 $Path) -cne $newHash) { throw "Atomic replace content hash mismatch: $Path" }
            $commitVerified = $true
        }
    } finally {
        if ($null -ne $stream) { $stream.Dispose() }
        if (Test-Path -LiteralPath $tempPath -PathType Leaf) { [IO.File]::Delete($tempPath) }
        if ($commitVerified -and $null -ne $backupPath -and (Test-Path -LiteralPath $backupPath -PathType Leaf)) { [IO.File]::Delete($backupPath) }
    }
}

function Read-Backlog([string]$RootPath) {
    $path = Join-Path $RootPath 'docs/backlog/tasks.json'
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { throw "Missing backlog: $path" }
    $file = Read-Utf8File $path
    try { $data = ConvertFrom-LoopJson -InputObject $file.Text -ErrorAction Stop }
    catch { throw "tasks.json JSON parse failed: $($_.Exception.Message)" }
    if ($null -eq $data.tasks -or @($data.tasks).Count -eq 0) { throw 'tasks.json has no tasks.' }
    return [pscustomobject]@{ Path = $path; Text = $file.Text; Bom = $file.Bom; Hash = $file.Hash; Data = $data }
}

function New-EmptyLoopState {
    return [pscustomobject]@{
        schema_version = 1
        revision = 0
        updated_utc = [datetime]::UtcNow.ToString('o')
        leases = @()
        events = @()
    }
}

function Read-LoopState([string]$RootPath) {
    $path = Join-Path $RootPath 'docs/governance/loop/state.json'
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
        return [pscustomobject]@{ Path = $path; Hash = $null; State = (New-EmptyLoopState) }
    }
    $file = Read-Utf8File $path
    try { $state = ConvertFrom-LoopJson -InputObject $file.Text -ErrorAction Stop }
    catch { throw "Loop state JSON parse failed: $($_.Exception.Message)" }
    if ($state.schema_version -ne 1 -or $null -eq $state.leases -or $null -eq $state.events) {
        throw "Unsupported or incomplete loop state: $path"
    }
    return [pscustomobject]@{ Path = $path; Hash = $file.Hash; State = $state }
}

function Save-LoopState($State, [string]$Path, [string]$ExpectedHash) {
    $State.revision = [int]$State.revision + 1
    $State.updated_utc = [datetime]::UtcNow.ToString('o')
    $json = ConvertTo-Json -InputObject $State -Depth 60
    Write-AtomicUtf8File $Path ($json + "`n") $false $ExpectedHash
}

function Get-TaskMap($Data) {
    $map = @{}
    foreach ($task in $Data.tasks) { $map[[string]$task.id] = $task }
    return ,$map
}

function Read-GovernanceGatePolicy([string]$RootPath) {
    $path = Join-Path $RootPath 'docs/governance/gate-policy.json'
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { throw "Missing governance gate policy: $path" }
    try {
        $policy = ConvertFrom-LoopJson -InputObject ([IO.File]::ReadAllText($path, [Text.Encoding]::UTF8)) -ErrorAction Stop
    } catch { throw "Governance gate policy JSON parse failed: $($_.Exception.Message)" }
    if ($policy.schema_version -ne 1 -or $policy.model -ne 'Founder-Led') { throw 'Unsupported governance gate policy.' }
    if ($null -eq $policy.development_authorizations) { throw 'Governance gate policy has no development authorizations.' }
    return $policy
}

function Get-TaskGateType($Task, $Policy) {
    $override = @($Policy.task_gate_overrides | Where-Object { [string]$_.task_id -ceq [string]$Task.id }) | Select-Object -First 1
    $gate = if ($null -ne $override) { [string]$override.gate } else { 'DEVELOPMENT' }
    if ($gate -notin @('DEVELOPMENT', 'INTEGRATION', 'PRODUCTION')) { throw "Invalid gate '$gate' for task $($Task.id)." }
    return $gate
}

function Test-DevelopmentAuthorization($Task, [string]$Decision, $Policy, [string]$RootPath) {
    if ((Get-TaskGateType $Task $Policy) -ne 'DEVELOPMENT') { return $false }
    $entry = @($Policy.development_authorizations | Where-Object {
        [string]$_.task_id -ceq [string]$Task.id -and @($_.decisions) -contains $Decision
    }) | Select-Object -First 1
    if ($null -eq $entry -or [string]$entry.authority -cne [string]$Policy.founder_authority) { return $false }
    $evidence = [string]$entry.evidence
    if ([string]::IsNullOrWhiteSpace($evidence) -or [IO.Path]::IsPathRooted($evidence)) { return $false }
    $path = [IO.Path]::GetFullPath((Join-Path $RootPath ($evidence.Replace('/', [IO.Path]::DirectorySeparatorChar))))
    $prefix = $RootPath.TrimEnd([char[]]@('\', '/')) + [IO.Path]::DirectorySeparatorChar
    return $path.StartsWith($prefix, [StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $path -PathType Leaf)
}

function Assert-BacklogIntegrity($Data, [string]$RootPath) {
    $validStatuses = @('blocked', 'ready', 'planned', 'in_progress', 'review', 'done')
    $policy = Read-GovernanceGatePolicy $RootPath
    $map = @{}
    foreach ($task in $Data.tasks) {
        if ($task.id -isnot [string] -or [string]::IsNullOrWhiteSpace($task.id)) { throw 'Backlog task has a missing/non-string id.' }
        if ($map.ContainsKey($task.id)) { throw "Duplicate task ID: $($task.id)" }
        if ($task.status -isnot [string] -or $task.status -notin $validStatuses) { throw "Invalid status for $($task.id): $($task.status)" }
        foreach ($field in @('depends_on', 'blocks', 'decision_dependencies')) {
            if ($task.$field -isnot [array]) { throw "$($task.id): $field must be an array." }
        }
        if ($null -eq $task.scope -or $task.scope.paths -isnot [array] -or $task.scope.modules -isnot [array] -or
            @($task.scope.paths).Count -eq 0 -or @($task.scope.modules).Count -eq 0) {
            throw "$($task.id): scope.paths and scope.modules must be non-empty arrays."
        }
        $map[$task.id] = $task
    }

    foreach ($task in $Data.tasks) {
        foreach ($dependency in $task.depends_on) {
            if (-not $map.ContainsKey([string]$dependency)) { throw "$($task.id): missing dependency $dependency" }
            if ($map[$dependency].blocks -notcontains $task.id) { throw "$($task.id): missing reverse block on $dependency" }
            if ($task.status -eq 'ready' -and $map[$dependency].status -ne 'done') { throw "$($task.id): ready before $dependency is done" }
        }
        foreach ($blockedTask in $task.blocks) {
            if (-not $map.ContainsKey([string]$blockedTask)) { throw "$($task.id): missing blocked task $blockedTask" }
            if ($map[$blockedTask].depends_on -notcontains $task.id) { throw "$($task.id): non-symmetric block $blockedTask" }
        }
        foreach ($decision in $task.decision_dependencies) {
            if ($decision -notmatch '^(OPEN-\d{2}|ADR-\d{3})$') { throw "$($task.id): invalid decision reference $decision" }
            if ($task.status -eq 'ready') {
                $approval = Get-DecisionApprovalCheck -Root $RootPath -Reference $decision
                if (-not $approval.Approved -and -not (Test-DevelopmentAuthorization $task ([string]$decision) $policy $RootPath)) { throw "$($task.id): ready with unresolved decision $decision ($($approval.Message))" }
            }
        }
    }

    # A simple topological pass detects cycles without changing task order or content.
    $finished = @{}
    $remaining = @($Data.tasks).Count
    while ($remaining -gt 0) {
        $progress = $false
        foreach ($task in $Data.tasks) {
            if ($finished.ContainsKey([string]$task.id)) { continue }
            $readyToVisit = $true
            foreach ($dependency in $task.depends_on) {
                if (-not $finished.ContainsKey([string]$dependency)) { $readyToVisit = $false; break }
            }
            if ($readyToVisit) { $finished[[string]$task.id] = $true; $remaining--; $progress = $true }
        }
        if (-not $progress) { throw 'Dependency cycle detected in tasks.json.' }
    }
}

function Get-TaskReadiness($Task, $Map, [string]$RootPath) {
    $reasons = New-Object 'Collections.Generic.List[string]'
    $policy = Read-GovernanceGatePolicy $RootPath
    $gate = Get-TaskGateType $Task $policy
    foreach ($dependency in $Task.depends_on) {
        if (-not $Map.ContainsKey([string]$dependency)) { $reasons.Add("missing dependency $dependency"); continue }
        if ($Map[$dependency].status -ne 'done') { $reasons.Add("dependency $dependency is $($Map[$dependency].status)") }
    }
    foreach ($decision in $Task.decision_dependencies) {
        $approval = Get-DecisionApprovalCheck -Root $RootPath -Reference $decision
        if (-not $approval.Approved -and -not (Test-DevelopmentAuthorization $Task ([string]$decision) $policy $RootPath)) {
            $reasons.Add("decision $decision unresolved for $gate gate: $($approval.Message)")
        }
    }
    if ($Task.PSObject.Properties.Name -contains 'execution_prerequisites') {
        foreach ($path in @($Task.execution_prerequisites)) {
            $candidate = Join-Path $RootPath ([string]$path)
            if (-not (Test-Path -LiteralPath $candidate)) { $reasons.Add("missing execution prerequisite $path") }
        }
    }
    return [pscustomobject]@{ IsReady = ($reasons.Count -eq 0); Reasons = @($reasons.ToArray()); Gate = $gate }
}

function Normalize-ScopePath([string]$RootPath, [string]$RelativePath) {
    if ([string]::IsNullOrWhiteSpace($RelativePath) -or [IO.Path]::IsPathRooted($RelativePath)) { throw "Invalid scoped path: $RelativePath" }
    $candidate = [IO.Path]::GetFullPath((Join-Path $RootPath ($RelativePath.Replace('/', [IO.Path]::DirectorySeparatorChar))))
    $prefix = $RootPath.TrimEnd([char[]]@('\', '/')) + [IO.Path]::DirectorySeparatorChar
    if (-not $candidate.StartsWith($prefix, [StringComparison]::OrdinalIgnoreCase)) { throw "Scope escapes repository root: $RelativePath" }
    return $candidate.Substring($prefix.Length).TrimEnd([char[]]@('\', '/'))
}

function Get-TaskScope($Task, [string]$RootPath) {
    $paths = @()
    foreach ($path in $Task.scope.paths) { $paths += (Normalize-ScopePath $RootPath ([string]$path)) }
    $modules = @()
    foreach ($module in $Task.scope.modules) {
        if ([string]::IsNullOrWhiteSpace([string]$module)) { throw "$($Task.id): empty scope module." }
        $modules += ([string]$module).Trim()
    }
    return [pscustomobject]@{ paths = $paths; modules = $modules }
}

function Test-PathScopeOverlap([string]$Left, [string]$Right) {
    if ($Left.Equals($Right, [StringComparison]::OrdinalIgnoreCase)) { return $true }
    $separator = [IO.Path]::DirectorySeparatorChar
    return $Left.StartsWith($Right.TrimEnd([char[]]@('\', '/')) + $separator, [StringComparison]::OrdinalIgnoreCase) -or
        $Right.StartsWith($Left.TrimEnd([char[]]@('\', '/')) + $separator, [StringComparison]::OrdinalIgnoreCase)
}

function Find-ScopeConflict($Scope, $Leases, [string]$RootPath) {
    foreach ($lease in $Leases) {
        foreach ($module in $Scope.modules) {
            if (@($lease.scopes.modules) -contains $module) { return "module '$module' is leased by $($lease.task_id) ($($lease.owner))" }
        }
        foreach ($path in $Scope.paths) {
            foreach ($leasedPath in $lease.scopes.paths) {
                $normalizedLeasePath = Normalize-ScopePath $RootPath ([string]$leasedPath)
                if (Test-PathScopeOverlap $path $normalizedLeasePath) {
                    return "path '$path' overlaps lease $($lease.task_id) scope '$leasedPath' ($($lease.owner))"
                }
            }
        }
    }
    return $null
}

function Get-TaskLease($State, [string]$Id) {
    foreach ($lease in $State.leases) { if ($lease.task_id -eq $Id) { return $lease } }
    return $null
}

function Get-TaskById($Data, [string]$Id) {
    foreach ($task in $Data.tasks) { if ($task.id -eq $Id) { return $task } }
    return $null
}

function Set-RawTaskStatus([string]$JsonText, [string]$Id, [string]$Status) {
    # Accept both pretty-printed and compact JSON without reserializing unrelated task bytes.
    $idPattern = '(?ms)(?:^|\{)\s*"id"\s*:\s*"' + [regex]::Escape($Id) + '"\s*,'
    $idMatches = [regex]::Matches($JsonText, $idPattern)
    if ($idMatches.Count -ne 1) { throw "Expected exactly one JSON task object for $Id; found $($idMatches.Count)." }

    $target = $idMatches[0].Index + $idMatches[0].Value.IndexOf('"id"', [StringComparison]::Ordinal)
    $braceStack = New-Object 'Collections.Generic.List[int]'
    $inString = $false
    $escaped = $false
    for ($i = 0; $i -lt $target; $i++) {
        $character = $JsonText[$i]
        if ($inString) {
            if ($escaped) { $escaped = $false }
            elseif ($character -eq '\') { $escaped = $true }
            elseif ($character -eq '"') { $inString = $false }
        } else {
            if ($character -eq '"') { $inString = $true }
            elseif ($character -eq '{') { $braceStack.Add($i) }
            elseif ($character -eq '}' -and $braceStack.Count -gt 0) { $braceStack.RemoveAt($braceStack.Count - 1) }
        }
    }
    if ($braceStack.Count -eq 0) { throw "Could not locate containing task object for $Id." }
    $objectStart = $braceStack[$braceStack.Count - 1]

    $depth = 1
    $inString = $false
    $escaped = $false
    $objectEnd = -1
    for ($i = $objectStart + 1; $i -lt $JsonText.Length; $i++) {
        $character = $JsonText[$i]
        if ($inString) {
            if ($escaped) { $escaped = $false }
            elseif ($character -eq '\') { $escaped = $true }
            elseif ($character -eq '"') { $inString = $false }
        } else {
            if ($character -eq '"') { $inString = $true }
            elseif ($character -eq '{') { $depth++ }
            elseif ($character -eq '}') {
                $depth--
                if ($depth -eq 0) { $objectEnd = $i + 1; break }
            }
        }
    }
    if ($objectEnd -lt 0) { throw "Unterminated task object for $Id." }

    $objectText = $JsonText.Substring($objectStart, $objectEnd - $objectStart)
    $statusMatches = [regex]::Matches($objectText, '(?m)(?:^|,)\s*"status"\s*:\s*"([^"]*)"')
    if ($statusMatches.Count -ne 1) { throw "Expected exactly one status property for $Id; found $($statusMatches.Count)." }
    $valueGroup = $statusMatches[0].Groups[1]
    $globalValueIndex = $objectStart + $valueGroup.Index
    return $JsonText.Substring(0, $globalValueIndex) + $Status + $JsonText.Substring($globalValueIndex + $valueGroup.Length)
}

function Acquire-CoordinatorLock([string]$RootPath, [string]$LockOwner) {
    $directory = Join-Path $RootPath 'docs/governance/loop'
    [void][IO.Directory]::CreateDirectory($directory)
    $path = Join-Path $directory 'coordinator.lock'
    $token = [guid]::NewGuid().ToString('N')
    try { $stream = [IO.File]::Open($path, [IO.FileMode]::CreateNew, [IO.FileAccess]::ReadWrite, [IO.FileShare]::None) }
    catch [IO.IOException] { throw "Coordinator lock already exists; inspect it and wait for its owner: $path" }
    $body = "token=$token`r`nowner=$LockOwner`r`npid=$PID`r`ncreated_utc=$([datetime]::UtcNow.ToString('o'))`r`n"
    $bytes = [Text.Encoding]::UTF8.GetBytes($body)
    $stream.Write($bytes, 0, $bytes.Length)
    $stream.Flush($true)
    return [pscustomobject]@{ Path = $path; Token = $token; Stream = $stream }
}

function Release-CoordinatorLock($Lock) {
    if ($null -eq $Lock) { return }
    if ($null -ne $Lock.Stream) { $Lock.Stream.Dispose() }
    if (Test-Path -LiteralPath $Lock.Path -PathType Leaf) {
        $content = [IO.File]::ReadAllText($Lock.Path, [Text.Encoding]::UTF8)
        if ($content -match ('(?m)^token=' + [regex]::Escape($Lock.Token) + '\s*$')) { [IO.File]::Delete($Lock.Path) }
    }
}

function Add-StateEvent($State, $Event) {
    $State.events = @(@($State.events) + @($Event))
}

function Get-TextSha256([string]$Text, [bool]$Bom = $false) {
    return Get-BytesSha256 (Get-Utf8Bytes $Text $Bom)
}

function Test-HashEqual([string]$Left, [string]$Right) {
    if ([string]::IsNullOrEmpty($Left) -and [string]::IsNullOrEmpty($Right)) { return $true }
    return $Left -ceq $Right
}

function Get-PendingIntentPath([string]$RootPath) {
    return (Join-Path $RootPath 'docs/governance/loop/pending-transaction.json')
}

function New-OperationHandoff([string]$RootPath, $Event, [string]$EvidenceText) {
    $directory = Join-Path $RootPath 'docs/governance/loop/handoffs'
    [void][IO.Directory]::CreateDirectory($directory)
    $safeTaskId = [regex]::Replace([string]$Event.task_id, '[^A-Za-z0-9._-]', '_')
    if ([string]::IsNullOrWhiteSpace($safeTaskId)) { $safeTaskId = 'task' }
    $name = '{0}-{1}.md' -f $safeTaskId, $Event.event_id
    $relativePath = 'docs/governance/loop/handoffs/' + $name
    $Event | Add-Member -NotePropertyName handoff_path -NotePropertyValue $relativePath -Force
    $lines = @(
        "# $($Event.action): $($Event.task_id)",
        '',
        "- Event ID: $($Event.event_id)",
        "- Recorded UTC: $($Event.timestamp_utc)",
        "- Owner: $($Event.owner)",
        "- Previous status: $($Event.previous_status)",
        "- New status: $($Event.new_status)",
        "- Lease ID: $($Event.lease_id)",
        "- Scope paths: $((@($Event.scope_paths) -join ', '))",
        "- Scope modules: $((@($Event.scope_modules) -join ', '))",
        "- Reviewer: $($Event.reviewer)",
        "- Blocker: $($Event.blocker)",
        "- Blocker owner: $($Event.blocker_owner)",
        "- Next action: $($Event.next_action)",
        '',
        '## Evidence / reason',
        '',
        $EvidenceText,
        '',
        '> This is supporting evidence, not proof of a committed transition by itself. The matching event_id and handoff_path in state.json are authoritative. This is not a business approval and does not change any OPEN decision or ADR.'
    )
    $text = ($lines -join "`n") + "`n"
    return [pscustomobject]@{
        RelativePath = $relativePath
        FullPath = (Join-Path $RootPath ($relativePath.Replace('/', [IO.Path]::DirectorySeparatorChar)))
        Text = $text
        Hash = (Get-TextSha256 $text $false)
    }
}

function Get-StateAfterText($StateInfo, $Plan) {
    $sourceJson = ConvertTo-Json -InputObject $StateInfo.State -Depth 60
    $target = ConvertFrom-LoopJson -InputObject $sourceJson -ErrorAction Stop
    $leases = @($target.leases)
    switch ([string]$Plan.lease_change) {
        'Add' {
            if (@($leases | Where-Object { $_.lease_id -eq $Plan.lease.lease_id }).Count -ne 0) { throw "Lease already exists in pre-transaction state: $($Plan.lease.lease_id)" }
            $leases += $Plan.lease
            $target.leases = @($leases)
        }
        'Update' {
            $found = 0
            for ($index = 0; $index -lt $leases.Count; $index++) {
                if ($leases[$index].lease_id -eq $Plan.lease.lease_id) { $leases[$index] = $Plan.lease; $found++ }
            }
            if ($found -ne 1) { throw "Expected one lease to update; found $found." }
            $target.leases = @($leases)
        }
        'Remove' {
            $remaining = @($leases | Where-Object { $_.lease_id -ne $Plan.lease_id })
            if ($remaining.Count -eq $leases.Count) { throw "Lease to remove is absent: $($Plan.lease_id)" }
            $target.leases = @($remaining)
        }
        'None' { }
        default { throw "Invalid journal lease change: $($Plan.lease_change)" }
    }

    $events = @($target.events)
    if (@($events | Where-Object { $_.event_id -eq $Plan.event.event_id }).Count -ne 0) { throw "Event already exists in pre-transaction state: $($Plan.event.event_id)" }
    $events += $Plan.event
    $target.events = @($events)
    $target.revision = [int]$target.revision + 1
    $target.updated_utc = [string]$Plan.state_updated_utc
    return ((ConvertTo-Json -InputObject $target -Depth 60) + "`n")
}

function New-TransactionPlan([string]$RootPath, $Backlog, $StateInfo, $Task, [string]$TargetStatus, $Event, $Handoff, [string]$LeaseChange, $Lease, [string]$LeaseId, [string]$Outcome) {
    $taskText = if ($Task.status -ceq $TargetStatus) { $Backlog.Text } else { Set-RawTaskStatus $Backlog.Text ([string]$Task.id) $TargetStatus }
    $updatedData = ConvertFrom-LoopJson -InputObject $taskText -ErrorAction Stop
    Assert-BacklogIntegrity $updatedData $RootPath
    $stateUpdated = [datetime]::UtcNow.ToString('o')
    $plan = [pscustomobject]@{
        outcome = $Outcome
        task_status_before = [string]$Task.status
        task_status_target = $TargetStatus
        tasks_hash_before = $Backlog.Hash
        tasks_hash_after = (Get-TextSha256 $taskText $Backlog.Bom)
        task_bom = [bool]$Backlog.Bom
        state_hash_before = $StateInfo.Hash
        state_hash_after = ''
        state_updated_utc = $stateUpdated
        lease_change = $LeaseChange
        lease = $Lease
        lease_id = $LeaseId
        event = $Event
        handoff_path = [string]$Handoff.RelativePath
        handoff_content = [string]$Handoff.Text
        handoff_hash = [string]$Handoff.Hash
    }
    $stateAfter = Get-StateAfterText $StateInfo $plan
    $plan.state_hash_after = Get-TextSha256 $stateAfter $false
    return $plan
}

function Write-PendingIntent($Payload, [string]$RootPath) {
    $payloadJson = ConvertTo-Json -InputObject $Payload -Depth 60 -Compress
    $checksum = Get-TextSha256 $payloadJson $false
    $envelope = [pscustomobject]@{ payload_json = $payloadJson; checksum = $checksum }
    $text = (ConvertTo-Json -InputObject $envelope -Depth 5) + "`n"
    $path = Get-PendingIntentPath $RootPath
    Write-AtomicUtf8File $path $text $false $null
    return [pscustomobject]@{ Path = $path; Hash = (Get-TextSha256 $text $false); Payload = $Payload }
}

function Read-PendingIntent([string]$RootPath) {
    $path = Get-PendingIntentPath $RootPath
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { return $null }
    $file = Read-Utf8File $path
    try { $envelope = ConvertFrom-LoopJson -InputObject $file.Text -ErrorAction Stop }
    catch { throw "Pending transaction journal JSON is invalid: $($_.Exception.Message)" }
    if ($null -eq $envelope.payload_json -or $null -eq $envelope.checksum -or
        (Get-TextSha256 ([string]$envelope.payload_json) $false) -cne [string]$envelope.checksum) {
        throw 'Pending transaction journal checksum mismatch; preserve it and inspect before recovery.'
    }
    try { $payload = ConvertFrom-LoopJson -InputObject ([string]$envelope.payload_json) -ErrorAction Stop }
    catch { throw "Pending transaction payload is invalid: $($_.Exception.Message)" }
    if ($payload.schema_version -ne 1 -or [string]$payload.tasks_path -cne 'docs/backlog/tasks.json' -or
        [string]$payload.state_path -cne 'docs/governance/loop/state.json' -or
        [string]::IsNullOrWhiteSpace([string]$payload.task_id) -or [string]::IsNullOrWhiteSpace([string]$payload.owner) -or
        @($payload.plans).Count -lt 1) { throw 'Pending transaction journal has an unsupported or incomplete shape.' }
    foreach ($plan in $payload.plans) {
        if ($plan.task_status_target -notin @('blocked', 'ready', 'planned', 'in_progress', 'review', 'done') -or
            $plan.handoff_path -notmatch '^docs/governance/loop/handoffs/[^/\\]+\.md$' -or
            (Get-TextSha256 ([string]$plan.handoff_content) $false) -cne [string]$plan.handoff_hash -or
            $plan.event.task_id -cne [string]$payload.task_id -or $plan.event.owner -cne [string]$payload.owner -or
            $plan.event.handoff_path -cne [string]$plan.handoff_path) {
            throw 'Pending transaction plan failed path/content/event integrity checks.'
        }
    }
    return [pscustomobject]@{ Path = $path; Hash = $file.Hash; Payload = $payload }
}

function Ensure-PlanHandoff([string]$RootPath, $Plan) {
    $handoffRoot = [IO.Path]::GetFullPath((Join-Path $RootPath 'docs/governance/loop/handoffs'))
    $fullPath = [IO.Path]::GetFullPath((Join-Path $RootPath ($Plan.handoff_path.Replace('/', [IO.Path]::DirectorySeparatorChar))))
    $prefix = $handoffRoot.TrimEnd([char[]]@('\', '/')) + [IO.Path]::DirectorySeparatorChar
    if (-not $fullPath.StartsWith($prefix, [StringComparison]::OrdinalIgnoreCase) -or [IO.Path]::GetExtension($fullPath) -cne '.md') {
        throw 'Pending handoff path escapes the handoff directory.'
    }
    if (Test-Path -LiteralPath $fullPath -PathType Leaf) {
        if ((Get-FileSha256 $fullPath) -cne [string]$Plan.handoff_hash) { throw "Handoff file changed after intent was recorded: $($Plan.handoff_path)" }
    } else {
        Write-AtomicUtf8File $fullPath ([string]$Plan.handoff_content) $false $null
        if ((Get-FileSha256 $fullPath) -cne [string]$Plan.handoff_hash) { throw "Handoff create hash mismatch: $($Plan.handoff_path)" }
    }
}

function Invoke-FaultPoint([string]$Point) {
    if (-not [string]::IsNullOrWhiteSpace($script:FaultAt) -and $script:FaultAt -ceq $Point) { throw "Injected test fault at $Point." }
}

function Remove-PendingIntent($IntentInfo) {
    $currentHash = Get-FileSha256 $IntentInfo.Path
    if ($currentHash -cne [string]$IntentInfo.Hash) { throw 'Pending journal changed before cleanup; refusing to remove it.' }
    [IO.File]::Delete($IntentInfo.Path)
}

function Invoke-PendingTransaction([string]$RootPath, $IntentInfo, [switch]$Recovery) {
    $payload = $IntentInfo.Payload
    $plans = @($payload.plans)
    $backlog = Read-Backlog $RootPath
    $stateInfo = Read-LoopState $RootPath
    $selected = $null
    foreach ($plan in $plans) {
        if (Test-HashEqual $stateInfo.Hash ([string]$plan.state_hash_after)) {
            $selected = $plan
            break
        }
    }

    if ($null -ne $selected) {
        if (-not (Test-HashEqual $backlog.Hash ([string]$selected.tasks_hash_after))) { throw 'State is finalized but tasks.json no longer matches the recorded transaction; preserve all files for manual review.' }
        if ($payload.action -eq 'Reassess' -and $selected.outcome -eq 'commit') {
            $finalTask = Get-TaskById $backlog.Data ([string]$payload.task_id)
            if ($null -eq $finalTask -or $finalTask.status -ne 'ready' -or -not (Get-TaskReadiness $finalTask (Get-TaskMap $backlog.Data) $RootPath).IsReady) {
                throw 'Reassess was finalized but readiness is no longer satisfied; preserve the journal for coordinator review.'
            }
        } elseif ($payload.action -eq 'Reassess' -and $selected.outcome -eq 'rollback') {
            $finalTask = Get-TaskById $backlog.Data ([string]$payload.task_id)
            if ($null -eq $finalTask -or $finalTask.status -ne 'blocked') { throw 'Reassess cancellation is finalized but the blocked status does not match; preserve the journal for coordinator review.' }
        }
        Ensure-PlanHandoff $RootPath $selected
        Remove-PendingIntent $IntentInfo
        Write-Output ("RECOVERED {0} task={1} outcome={2} (already finalized; journal cleaned)" -f $payload.action, $payload.task_id, $selected.outcome)
        return
    }

    if (-not (Test-HashEqual $stateInfo.Hash ([string]$plans[0].state_hash_before))) {
        throw 'Loop state hash matches neither the pre-transaction state nor a recorded final state; refusing recovery.'
    }
    $selected = @($plans | Where-Object { $_.outcome -eq 'commit' }) | Select-Object -First 1
    if ($payload.action -eq 'Complete') {
        $task = Get-TaskById $backlog.Data ([string]$payload.task_id)
        if ($null -eq $task) { throw "Pending task is missing: $($payload.task_id)" }
        $readiness = Get-TaskReadiness $task (Get-TaskMap $backlog.Data) $RootPath
        if (-not $readiness.IsReady) {
            $selected = @($plans | Where-Object { $_.outcome -eq 'rollback' }) | Select-Object -First 1
            if ($null -eq $selected) { throw "Complete recovery is blocked by newly unresolved prerequisites: $($readiness.Reasons -join '; ')" }
        }
    } elseif ($payload.action -eq 'Claim') {
        $task = Get-TaskById $backlog.Data ([string]$payload.task_id)
        if ($null -eq $task) { throw "Pending task is missing: $($payload.task_id)" }
        $readiness = Get-TaskReadiness $task (Get-TaskMap $backlog.Data) $RootPath
        if (-not $readiness.IsReady) { throw "Claim recovery is blocked by changed prerequisites: $($readiness.Reasons -join '; '); journal retained." }
    } elseif ($payload.action -eq 'Reassess') {
        $task = Get-TaskById $backlog.Data ([string]$payload.task_id)
        if ($null -eq $task) { throw "Pending task is missing: $($payload.task_id)" }
        $readiness = Get-TaskReadiness $task (Get-TaskMap $backlog.Data) $RootPath
        if (-not $readiness.IsReady) {
            $selected = @($plans | Where-Object { $_.outcome -eq 'rollback' }) | Select-Object -First 1
            if ($null -eq $selected) { throw "Reassess recovery is blocked by changed prerequisites: $($readiness.Reasons -join '; '); journal retained." }
        }
    }
    if ($null -eq $selected) { throw 'Pending journal has no commit plan.' }

    $allowedTaskHashes = @([string]$payload.tasks_hash_before) + @($plans | ForEach-Object { [string]$_.tasks_hash_after })
    if (-not (@($allowedTaskHashes | Where-Object { Test-HashEqual $backlog.Hash $_ }).Count -gt 0)) {
        throw 'tasks.json hash matches neither the recorded pre-state nor any transaction outcome; refusing recovery.'
    }
    if ($payload.action -eq 'Reassess' -and $selected.outcome -eq 'commit') {
        $task = Get-TaskById $backlog.Data ([string]$payload.task_id)
        $readiness = if ($null -eq $task) { $null } else { Get-TaskReadiness $task (Get-TaskMap $backlog.Data) $RootPath }
        if ($null -eq $task -or -not $readiness.IsReady) {
            $selected = @($plans | Where-Object { $_.outcome -eq 'rollback' }) | Select-Object -First 1
            if ($null -eq $selected) { throw 'Reassess prerequisites changed before task write; no cancellation plan is present in the journal.' }
        }
    }
    Ensure-PlanHandoff $RootPath $selected
    Invoke-FaultPoint 'AfterHandoff'

    if (-not (Test-HashEqual $backlog.Hash ([string]$selected.tasks_hash_after))) {
        $taskText = Set-RawTaskStatus $backlog.Text ([string]$payload.task_id) ([string]$selected.task_status_target)
        if ((Get-TextSha256 $taskText $backlog.Bom) -cne [string]$selected.tasks_hash_after) {
            throw 'Recomputed status edit does not match the persisted intent hash; refusing to overwrite tasks.json.'
        }
        $updatedData = ConvertFrom-LoopJson -InputObject $taskText -ErrorAction Stop
        Assert-BacklogIntegrity $updatedData $RootPath
        Write-AtomicUtf8File $backlog.Path $taskText $backlog.Bom $backlog.Hash
    }
    Invoke-FaultPoint 'AfterTaskStatus'

    if ($payload.action -eq 'Complete' -and $selected.outcome -eq 'commit') {
        $latestBacklog = Read-Backlog $RootPath
        if (-not (Test-HashEqual $latestBacklog.Hash ([string]$selected.tasks_hash_after))) {
            throw 'tasks.json changed after the completion status write; refusing to finalize state.'
        }
        $latestTask = Get-TaskById $latestBacklog.Data ([string]$payload.task_id)
        $latestReadiness = Get-TaskReadiness $latestTask (Get-TaskMap $latestBacklog.Data) $RootPath
        if (-not $latestReadiness.IsReady) {
            $rollbackPlan = @($plans | Where-Object { $_.outcome -eq 'rollback' }) | Select-Object -First 1
            if ($null -eq $rollbackPlan) { throw 'Completion prerequisites changed; no rollback plan is present in the journal.' }
            Ensure-PlanHandoff $RootPath $rollbackPlan
            $rollbackText = Set-RawTaskStatus $latestBacklog.Text ([string]$payload.task_id) ([string]$rollbackPlan.task_status_target)
            if ((Get-TextSha256 $rollbackText $latestBacklog.Bom) -cne [string]$rollbackPlan.tasks_hash_after) {
                throw 'Recomputed completion rollback does not match its intent hash; refusing to overwrite tasks.json.'
            }
            $rollbackData = ConvertFrom-LoopJson -InputObject $rollbackText -ErrorAction Stop
            Assert-BacklogIntegrity $rollbackData $RootPath
            Write-AtomicUtf8File $latestBacklog.Path $rollbackText $latestBacklog.Bom $latestBacklog.Hash
            $selected = $rollbackPlan
        }
    } elseif ($payload.action -eq 'Reassess' -and $selected.outcome -eq 'commit') {
        $latestBacklog = Read-Backlog $RootPath
        if (-not (Test-HashEqual $latestBacklog.Hash ([string]$selected.tasks_hash_after))) {
            throw 'tasks.json changed after the Reassess status write; refusing to finalize state.'
        }
        $latestTask = Get-TaskById $latestBacklog.Data ([string]$payload.task_id)
        $latestReadiness = if ($null -eq $latestTask) { $null } else { Get-TaskReadiness $latestTask (Get-TaskMap $latestBacklog.Data) $RootPath }
        if ($null -eq $latestTask -or -not $latestReadiness.IsReady) {
            $rollbackPlan = @($plans | Where-Object { $_.outcome -eq 'rollback' }) | Select-Object -First 1
            if ($null -eq $rollbackPlan) { throw 'Reassess prerequisites changed; no cancellation plan is present in the journal.' }
            Ensure-PlanHandoff $RootPath $rollbackPlan
            $rollbackText = Set-RawTaskStatus $latestBacklog.Text ([string]$payload.task_id) ([string]$rollbackPlan.task_status_target)
            if ((Get-TextSha256 $rollbackText $latestBacklog.Bom) -cne [string]$rollbackPlan.tasks_hash_after) {
                throw 'Reassess cancellation does not match its intent hash; refusing to overwrite tasks.json.'
            }
            $rollbackData = ConvertFrom-LoopJson -InputObject $rollbackText -ErrorAction Stop
            Assert-BacklogIntegrity $rollbackData $RootPath
            if (-not (Test-HashEqual (Get-FileSha256 $latestBacklog.Path) $latestBacklog.Hash)) { throw 'Stale write refused; tasks.json changed before Reassess cancellation.' }
            if ($latestBacklog.Hash -cne [string]$rollbackPlan.tasks_hash_after) {
                Write-AtomicUtf8File $latestBacklog.Path $rollbackText $latestBacklog.Bom $latestBacklog.Hash
            }
            $selected = $rollbackPlan
        }
    }

    $stateText = Get-StateAfterText $stateInfo $selected
    if ((Get-TextSha256 $stateText $false) -cne [string]$selected.state_hash_after) {
        throw 'Recomputed state update does not match the persisted intent hash; refusing to write loop state.'
    }
    Write-AtomicUtf8File $stateInfo.Path $stateText $false $stateInfo.Hash
    Invoke-FaultPoint 'AfterStateFinalize'
    Remove-PendingIntent $IntentInfo
    $resultVerb = if ($Recovery) { 'RECOVERED' } else { 'COMMITTED' }
    Write-Output ("{0} {1} task={2} outcome={3}" -f $resultVerb, $payload.action, $payload.task_id, $selected.outcome)
}

function Start-IntentTransaction([string]$RootPath, $Backlog, $StateInfo, [string]$ActionName, [string]$TaskId, [string]$OwnerName, [object[]]$Plans) {
    $transactionId = [guid]::NewGuid().ToString('N')
    $payload = [pscustomobject]@{
        schema_version = 1
        transaction_id = $transactionId
        action = $ActionName
        task_id = $TaskId
        owner = $OwnerName
        created_utc = [datetime]::UtcNow.ToString('o')
        tasks_path = 'docs/backlog/tasks.json'
        state_path = 'docs/governance/loop/state.json'
        tasks_hash_before = $Backlog.Hash
        plans = @($Plans)
    }
    $intentInfo = Write-PendingIntent $payload $RootPath
    Invoke-FaultPoint 'AfterIntent'
    Invoke-PendingTransaction $RootPath $intentInfo
}

function New-OperationEvent([string]$ActionName, [string]$Id, [string]$Actor, [string]$OldStatus, [string]$NewStatus, $Lease, $Scope, [string]$EvidenceText) {
    $leaseId = if ($null -ne $Lease) { [string]$Lease.lease_id } else { '' }
    $paths = if ($null -ne $Scope) { @($Scope.paths) } elseif ($null -ne $Lease) { @($Lease.scopes.paths) } else { @() }
    $modules = if ($null -ne $Scope) { @($Scope.modules) } elseif ($null -ne $Lease) { @($Lease.scopes.modules) } else { @() }
    return [pscustomobject]@{
        event_id = [guid]::NewGuid().ToString('N')
        action = $ActionName
        task_id = $Id
        owner = $Actor
        timestamp_utc = [datetime]::UtcNow.ToString('o')
        previous_status = $OldStatus
        new_status = $NewStatus
        lease_id = $leaseId
        scope_paths = $paths
        scope_modules = $modules
        reviewer = if ($ActionName -eq 'Complete') { $script:Reviewer } else { '' }
        blocker = if ($ActionName -eq 'Block') { $script:Blocker } else { '' }
        blocker_owner = if ($ActionName -eq 'Block') { $script:BlockerOwner } else { '' }
        next_action = if ($ActionName -eq 'Block') { $script:NextAction } else { '' }
        evidence = $EvidenceText
    }
}

function Test-Owner([string]$Value, [string]$ParameterName) {
    if ([string]::IsNullOrWhiteSpace($Value)) { throw "-$ParameterName is required for $Action." }
    return $Value.Trim()
}

function Update-BacklogSnapshot([string]$RawMarkdown, $Tasks) {
    $updated = $RawMarkdown
    foreach ($task in $Tasks) {
        $pattern = '(?m)^(?<head>\|\s*' + [regex]::Escape([string]$task.id) + '\s*\|[^|\r\n]*\|)\s*[^|\r\n]*\s*(?<tail>\|[^|\r\n]*\|)\s*$'
        $matches = [regex]::Matches($updated, $pattern)
        if ($matches.Count -ne 1) { throw "BACKLOG.md must contain exactly one task row for $($task.id); found $($matches.Count)." }
        $match = $matches[0]
        $replacement = $match.Groups['head'].Value + ' ' + [string]$task.status + ' ' + $match.Groups['tail'].Value
        $updated = $updated.Substring(0, $match.Index) + $replacement + $updated.Substring($match.Index + $match.Length)
    }
    return $updated
}

function Show-Status([string]$RootPath, [string]$Mode, [string]$OnlyTaskId) {
    $backlog = Read-Backlog $RootPath
    $stateInfo = Read-LoopState $RootPath
    $map = Get-TaskMap $backlog.Data
    $pending = $null
    $pendingError = $null
    try { $pending = Read-PendingIntent $RootPath } catch { $pendingError = $_.Exception.Message }
    if ($OnlyTaskId) {
        $task = Get-TaskById $backlog.Data $OnlyTaskId
        if ($null -eq $task) { throw "Unknown task ID: $OnlyTaskId" }
        $readiness = Get-TaskReadiness $task $map $RootPath
        $lease = Get-TaskLease $stateInfo.State $OnlyTaskId
        $leaseText = if ($null -eq $lease) { 'none' } else { '{0} ({1}, expires {2})' -f $lease.owner, $lease.lease_id, $lease.expires_utc }
        $recoveryText = if ($pendingError) { 'journal-invalid; mutation blocked' } elseif ($null -ne $pending -and $pending.Payload.task_id -eq $OnlyTaskId) { 'pending-recovery; transaction not finalized' } else { 'none' }
        Write-Output ("{0} | gate={1} | saved={2} | calculated={3} | lease={4} | recovery={5}" -f $task.id, $readiness.Gate, $task.status, $(if ($readiness.IsReady) { 'ready' } else { $readiness.Reasons -join '; ' }), $leaseText, $recoveryText)
        if ($pendingError) { Write-Output ("RECOVERY REQUIRED: {0}" -f $pendingError) }
        return
    }

    $counts = @($backlog.Data.tasks | Group-Object status | Sort-Object Name | ForEach-Object { '{0}={1}' -f $_.Name, $_.Count })
    $available = 0
    if ($null -eq $pending -and -not $pendingError) {
        foreach ($task in $backlog.Data.tasks) {
            if ($task.status -notin @('ready', 'planned')) { continue }
            if ($null -ne (Get-TaskLease $stateInfo.State $task.id)) { continue }
            if ((Get-TaskReadiness $task $map $RootPath).IsReady) { $available++ }
        }
    }
    $pendingText = if ($pendingError) { 'INVALID' } elseif ($null -eq $pending) { 'none' } else { '{0}:{1}:{2}' -f $pending.Payload.transaction_id, $pending.Payload.action, $pending.Payload.task_id }
    Write-Output ("Task-loop {0}: tasks={1}; {2}; active_leases={3}; currently_ready={4}; pending_transaction={5}" -f $Mode, @($backlog.Data.tasks).Count, ($counts -join ', '), @($stateInfo.State.leases).Count, $available, $pendingText)
    if ($pendingError) { Write-Output ("RECOVERY REQUIRED: {0}" -f $pendingError) }
    foreach ($lease in $stateInfo.State.leases) {
        $expired = $false
        try { $expired = [datetime]::Parse([string]$lease.expires_utc).ToUniversalTime() -le [datetime]::UtcNow } catch { $expired = $true }
        Write-Output ("LEASE {0} owner={1} expires={2} expired={3} paths={4} modules={5}" -f $lease.task_id, $lease.owner, $lease.expires_utc, $expired, (@($lease.scopes.paths) -join ','), (@($lease.scopes.modules) -join ','))
    }
    if ($Mode -eq 'Ready' -and $null -eq $pending -and -not $pendingError) {
        foreach ($task in $backlog.Data.tasks) {
            if ($task.status -notin @('ready', 'planned') -or $null -ne (Get-TaskLease $stateInfo.State $task.id)) { continue }
            $readiness = Get-TaskReadiness $task $map $RootPath
            if ($readiness.IsReady) { Write-Output ("READY {0} | gate={1} | {2} | {3}" -f $task.id, $readiness.Gate, $task.title, (@($task.scope.paths) -join ', ')) }
        }
    }
}

function Invoke-Mutation([string]$RootPath) {
    $lockOwner = if ([string]::IsNullOrWhiteSpace($Owner)) { 'local-coordinator' } else { $Owner.Trim() }
    $lock = $null
    try {
        $lock = Acquire-CoordinatorLock $RootPath $lockOwner
        $pending = Read-PendingIntent $RootPath
        if ($Action -eq 'Recover') {
            if ($null -eq $pending) { throw 'No pending transaction journal exists.' }
            if ([string]::IsNullOrWhiteSpace($TaskId) -or $TaskId -cne [string]$pending.Payload.task_id) { throw "Recover requires -TaskId '$($pending.Payload.task_id)' from the journal." }
            if ([string]::IsNullOrWhiteSpace($Owner) -or $Owner.Trim() -cne [string]$pending.Payload.owner) { throw "Recover requires the recorded owner '$($pending.Payload.owner)'; no lease takeover is allowed." }
            Invoke-PendingTransaction $RootPath $pending -Recovery
            return
        }
        if ($null -ne $pending) { throw "Pending transaction '$($pending.Payload.transaction_id)' for $($pending.Payload.task_id) blocks mutations; use -Action Recover with its recorded owner." }

        $backlog = Read-Backlog $RootPath
        $stateInfo = Read-LoopState $RootPath
        $state = $stateInfo.State

        if ($Action -eq 'RefreshBacklog') {
            Assert-BacklogIntegrity $backlog.Data $RootPath
            $markdownPath = Join-Path $RootPath 'docs/backlog/BACKLOG.md'
            if (-not (Test-Path -LiteralPath $markdownPath -PathType Leaf)) { throw "Missing backlog snapshot: $markdownPath" }
            $markdown = Read-Utf8File $markdownPath
            $updated = Update-BacklogSnapshot $markdown.Text $backlog.Data.tasks
            if ($updated -cne $markdown.Text) { Write-AtomicUtf8File $markdownPath $updated $markdown.Bom $markdown.Hash }
            $event = [pscustomobject]@{ event_id = [guid]::NewGuid().ToString('N'); action = 'RefreshBacklog'; task_id = ''; owner = $lockOwner; timestamp_utc = [datetime]::UtcNow.ToString('o'); previous_status = ''; new_status = ''; lease_id = ''; scope_paths = @(); scope_modules = @(); evidence = 'Refreshed the generated status snapshot from docs/backlog/tasks.json.' }
            Add-StateEvent $state $event
            Save-LoopState $state $stateInfo.Path $stateInfo.Hash
            Write-Output 'PASS: BACKLOG.md task status snapshot refreshed from tasks.json.'
            return
        }

        if ([string]::IsNullOrWhiteSpace($TaskId)) { throw "-TaskId is required for $Action." }
        Assert-BacklogIntegrity $backlog.Data $RootPath
        $task = Get-TaskById $backlog.Data $TaskId
        if ($null -eq $task) { throw "Unknown task ID: $TaskId" }
        $lease = Get-TaskLease $state $TaskId
        $map = Get-TaskMap $backlog.Data
        $oldStatus = [string]$task.status
        $scope = Get-TaskScope $task $RootPath
        $actor = $lockOwner
        $evidenceText = if ([string]::IsNullOrWhiteSpace($Evidence)) { '' } else { $Evidence.Trim() }

        if ($Action -eq 'Claim') {
            $actor = Test-Owner $Owner 'Owner'
            if ($oldStatus -in @('blocked', 'in_progress', 'review', 'done')) { throw "Cannot claim $TaskId from saved status '$oldStatus'; use the documented recovery/release path." }
            $readiness = Get-TaskReadiness $task $map $RootPath
            if (-not $readiness.IsReady) { throw "Cannot claim ${TaskId}: $($readiness.Reasons -join '; ')" }
            if ($null -ne $lease) { throw "Task $TaskId already has a lease held by $($lease.owner); expiry does not permit takeover." }
            $conflict = Find-ScopeConflict $scope $state.leases $RootPath
            if ($null -ne $conflict) { throw "Cannot claim ${TaskId}: overlapping active lease; $conflict" }
            $expires = [datetime]::UtcNow.AddHours($LeaseHours).ToString('o')
            $newLease = [pscustomobject]@{
                lease_id = [guid]::NewGuid().ToString('N')
                task_id = $TaskId
                owner = $actor
                scopes = $scope
                claimed_utc = [datetime]::UtcNow.ToString('o')
                expires_utc = $expires
                tasks_hash_at_claim = $backlog.Hash
            }
            $event = New-OperationEvent 'Claim' $TaskId $actor $oldStatus 'in_progress' $newLease $scope 'Claimed under the coordinator lock; no task deliverable or business decision approval is asserted.'
            $handoff = New-OperationHandoff $RootPath $event $event.evidence
            $plan = New-TransactionPlan $RootPath $backlog $stateInfo $task 'in_progress' $event $handoff 'Add' $newLease '' 'commit'
            Start-IntentTransaction -RootPath $RootPath -Backlog $backlog -StateInfo $stateInfo -ActionName 'Claim' -TaskId $TaskId -OwnerName $actor -Plans @($plan)
            return
        }

        if ($Action -eq 'Reassess') {
            $actor = Test-Owner $Owner 'Owner'
            if ([string]::IsNullOrWhiteSpace($Reason) -or [string]::IsNullOrWhiteSpace($evidenceText)) { throw 'Reassess requires -Reason and -Evidence.' }
            if ($null -ne $lease) { throw "Cannot reassess $TaskId while any lease exists; release it first." }
            if ($oldStatus -eq 'ready') { Write-Output "NOOP Reassess task=$TaskId already ready; no audit transition added."; return }
            if ($oldStatus -ne 'blocked') { throw "Reassess requires blocked status; $TaskId is $oldStatus." }
            $readiness = Get-TaskReadiness $task $map $RootPath
            if (-not $readiness.IsReady) {
                Write-Output ("NOOP Reassess task={0} remains blocked: {1}" -f $TaskId, ($readiness.Reasons -join '; '))
                return
            }
            $auditEvidence = "Reassessment reason: $($Reason.Trim())`n`n$evidenceText"
            $event = New-OperationEvent 'Reassess' $TaskId $actor $oldStatus 'ready' $null $scope $auditEvidence
            $handoff = New-OperationHandoff $RootPath $event $auditEvidence
            $commitPlan = New-TransactionPlan $RootPath $backlog $stateInfo $task 'ready' $event $handoff 'None' $null '' 'commit'
            $cancelEvidence = "Reassess cancelled: prerequisite readiness changed during recovery. Original reason: $($Reason.Trim())`n`n$evidenceText"
            $cancelEvent = New-OperationEvent 'ReassessCancelled' $TaskId $actor 'pending_reassess' 'blocked' $null $scope $cancelEvidence
            $cancelHandoff = New-OperationHandoff $RootPath $cancelEvent $cancelEvidence
            $cancelPlan = New-TransactionPlan $RootPath $backlog $stateInfo $task 'blocked' $cancelEvent $cancelHandoff 'None' $null '' 'rollback'
            Start-IntentTransaction -RootPath $RootPath -StateInfo $stateInfo -Backlog $backlog -ActionName 'Reassess' -TaskId $TaskId -OwnerName $actor -Plans @($commitPlan, $cancelPlan)
            return
        }

        if ($Action -eq 'Reopen') {
            $actor = Test-Owner $Owner 'Owner'
            if ([string]::IsNullOrWhiteSpace($Reason) -or [string]::IsNullOrWhiteSpace($Blocker) -or [string]::IsNullOrWhiteSpace($BlockerOwner) -or [string]::IsNullOrWhiteSpace($NextAction) -or [string]::IsNullOrWhiteSpace($evidenceText)) {
                throw 'Reopen requires -Reason, -Blocker, -BlockerOwner, -NextAction, and -Evidence.'
            }
            if ($oldStatus -ne 'done') { throw "Reopen requires done status; $TaskId is $oldStatus." }
            if ($null -ne $lease) { throw "Cannot reopen $TaskId while any lease exists; release or resolve it first." }
            $conflict = Find-ScopeConflict $scope $state.leases $RootPath
            if ($null -ne $conflict) { throw "Cannot reopen ${TaskId}: overlapping active lease; $conflict" }
            $auditEvidence = "Reopen reason: $($Reason.Trim())`n`n$evidenceText"
            $event = New-OperationEvent 'Reopen' $TaskId $actor $oldStatus 'blocked' $null $scope $auditEvidence
            $event.blocker = $Blocker.Trim()
            $event.blocker_owner = $BlockerOwner.Trim()
            $event.next_action = $NextAction.Trim()
            $handoff = New-OperationHandoff $RootPath $event $auditEvidence
            $plan = New-TransactionPlan $RootPath $backlog $stateInfo $task 'blocked' $event $handoff 'None' $null '' 'commit'
            Start-IntentTransaction -RootPath $RootPath -Backlog $backlog -StateInfo $stateInfo -ActionName 'Reopen' -TaskId $TaskId -OwnerName $actor -Plans @($plan)
            return
        }

        if ($null -eq $lease) { throw "No lease exists for $TaskId; refusing $Action." }
        if ([string]::IsNullOrWhiteSpace($Owner) -or $Owner.Trim() -cne [string]$lease.owner) { throw "-$('Owner') must exactly match lease owner '$($lease.owner)' for $Action." }
        $actor = $Owner.Trim()

        if ($Action -eq 'Renew') {
            if ($oldStatus -eq 'done') { throw "Cannot renew completed task $TaskId." }
            $updatedLease = ConvertFrom-LoopJson -InputObject (ConvertTo-Json -InputObject $lease -Depth 30) -ErrorAction Stop
            $updatedLease.expires_utc = [datetime]::UtcNow.AddHours($LeaseHours).ToString('o')
            $renewEvidence = "Lease renewed for $LeaseHours hour(s)." + $(if ($evidenceText) { " $evidenceText" } else { '' })
            $event = New-OperationEvent 'Renew' $TaskId $actor $oldStatus $oldStatus $updatedLease $scope $renewEvidence
            $handoff = New-OperationHandoff $RootPath $event $renewEvidence
            $plan = New-TransactionPlan $RootPath $backlog $stateInfo $task $oldStatus $event $handoff 'Update' $updatedLease $updatedLease.lease_id 'commit'
            Start-IntentTransaction -RootPath $RootPath -Backlog $backlog -StateInfo $stateInfo -ActionName 'Renew' -TaskId $TaskId -OwnerName $actor -Plans @($plan)
            return
        }

        $newStatus = $oldStatus
        if ($Action -eq 'Review') {
            if ($oldStatus -ne 'in_progress') { throw "Review requires in_progress status; $TaskId is $oldStatus." }
            if ([string]::IsNullOrWhiteSpace($evidenceText)) { throw '-Evidence is required for Review.' }
            $newStatus = 'review'
        } elseif ($Action -eq 'Complete') {
            if ($oldStatus -ne 'review') { throw "Complete requires review status; $TaskId is $oldStatus." }
            if ([string]::IsNullOrWhiteSpace($evidenceText)) { throw '-Evidence is required for Complete.' }
            if ([string]::IsNullOrWhiteSpace($Reviewer)) { throw '-Reviewer is required for Complete.' }
            $reviewerValue = $Reviewer.Trim()
            if ($reviewerValue -ceq $actor) { throw 'Complete requires an independent reviewer distinct from the lease owner.' }
            $readiness = Get-TaskReadiness $task $map $RootPath
            if (-not $readiness.IsReady) { throw "Cannot complete ${TaskId}: prerequisites must still be satisfied ($($readiness.Reasons -join '; '))." }
            $newStatus = 'done'
        } elseif ($Action -eq 'Block') {
            if ($oldStatus -notin @('in_progress', 'review')) { throw "Block requires in_progress or review status; $TaskId is $oldStatus." }
            if ([string]::IsNullOrWhiteSpace($Blocker) -or [string]::IsNullOrWhiteSpace($BlockerOwner) -or [string]::IsNullOrWhiteSpace($NextAction) -or [string]::IsNullOrWhiteSpace($evidenceText)) {
                throw 'Block requires -Blocker, -BlockerOwner, -NextAction, and -Evidence.'
            }
            $newStatus = 'blocked'
        } elseif ($Action -eq 'Release') {
            if ($oldStatus -eq 'done') { throw "Cannot release completed task $TaskId." }
            if ([string]::IsNullOrWhiteSpace($Reason) -or [string]::IsNullOrWhiteSpace($evidenceText)) { throw 'Release requires -Reason and -Evidence.' }
            $readiness = Get-TaskReadiness $task $map $RootPath
            if ($readiness.IsReady) { $newStatus = 'ready' }
            elseif (@($task.decision_dependencies).Count -gt 0) { $newStatus = 'blocked' }
            else { $newStatus = 'planned' }
            $evidenceText = "Release reason: $($Reason.Trim())`n`n$evidenceText"
        } else {
            throw "Unsupported mutation action: $Action"
        }

        $event = New-OperationEvent $Action $TaskId $actor $oldStatus $newStatus $lease $scope $evidenceText
        if ($Action -eq 'Complete') { $event.reviewer = $Reviewer.Trim() }
        if ($Action -eq 'Block') {
            $event.blocker = $Blocker.Trim()
            $event.blocker_owner = $BlockerOwner.Trim()
            $event.next_action = $NextAction.Trim()
        }
        $handoff = New-OperationHandoff $RootPath $event $evidenceText
        $leaseChange = if ($Action -eq 'Release') { 'Remove' } else { 'None' }
        $leaseToStore = $null
        $leaseId = [string]$lease.lease_id
        if ($Action -eq 'Complete') { $leaseChange = 'Remove' }
        $commitPlan = New-TransactionPlan $RootPath $backlog $stateInfo $task $newStatus $event $handoff $leaseChange $leaseToStore $leaseId 'commit'
        $plans = @($commitPlan)

        if ($Action -eq 'Complete') {
            $rollbackEvidence = 'Complete was not finalized because a prerequisite became unresolved during recovery. The task is restored/remains in review, the lease is retained, and no task is marked done by recovery.'
            $rollbackEvent = New-OperationEvent 'CompleteCancelled' $TaskId $actor 'pending_complete' 'review' $lease $scope $rollbackEvidence
            $rollbackHandoff = New-OperationHandoff $RootPath $rollbackEvent $rollbackEvidence
            $rollbackPlan = New-TransactionPlan $RootPath $backlog $stateInfo $task 'review' $rollbackEvent $rollbackHandoff 'None' $null $leaseId 'rollback'
            $plans += $rollbackPlan
        }
        Start-IntentTransaction -RootPath $RootPath -Backlog $backlog -StateInfo $stateInfo -ActionName $Action -TaskId $TaskId -OwnerName $actor -Plans $plans
    } finally {
        Release-CoordinatorLock $lock
    }
}

$Root = [IO.Path]::GetFullPath($Root)
if (-not (Test-Path -LiteralPath $Root -PathType Container)) { throw "Repository root not found: $Root" }
if ($Action -in @('Status', 'Ready')) {
    Show-Status $Root $Action $TaskId
} else {
    Invoke-Mutation $Root
}
