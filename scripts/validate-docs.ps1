param([string]$Root = (Split-Path $PSScriptRoot -Parent))
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'decision-evidence.ps1')
$errors = [Collections.Generic.List[string]]::new()
function Fail([string]$Message) { $errors.Add($Message) }
function Text([string]$Path) { [IO.File]::ReadAllText($Path, [Text.Encoding]::UTF8) }
$Root = [IO.Path]::GetFullPath($Root)
$gatePolicyPath = Join-Path $Root 'docs/governance/gate-policy.json'
try { $gatePolicy = (Text $gatePolicyPath) | ConvertFrom-Json } catch { Fail "Governance gate policy unavailable: $($_.Exception.Message)"; $gatePolicy = $null }
function Test-DevelopmentAuthorization([string]$TaskId, [string]$Decision) {
    if ($null -eq $gatePolicy -or $gatePolicy.model -ne 'Founder-Led') { return $false }
    $entry = @($gatePolicy.development_authorizations | Where-Object { [string]$_.task_id -ceq $TaskId -and @($_.decisions) -contains $Decision }) | Select-Object -First 1
    if ($null -eq $entry -or [string]$entry.authority -cne [string]$gatePolicy.founder_authority) { return $false }
    $evidence = [string]$entry.evidence
    if ([string]::IsNullOrWhiteSpace($evidence) -or [IO.Path]::IsPathRooted($evidence)) { return $false }
    $candidate = [IO.Path]::GetFullPath((Join-Path $Root ($evidence.Replace('/', [IO.Path]::DirectorySeparatorChar))))
    $prefix = $Root.TrimEnd([char[]]@('\', '/')) + [IO.Path]::DirectorySeparatorChar
    return $candidate.StartsWith($prefix, [StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $candidate -PathType Leaf)
}
$taskPath = Join-Path $Root 'docs/backlog/tasks.json'
try { $data = (Text $taskPath) | ConvertFrom-Json } catch { Write-Error "JSON parsing failed: $_"; exit 1 }
if (-not $data.tasks -or @($data.tasks).Count -eq 0) { Write-Error 'No tasks'; exit 1 }
$required = @('id','title','increment','epic','objective','prd_refs','depends_on','blocks','decision_dependencies','scope','deliverables','acceptance_criteria','verification','parallel_group','status','size','agent_notes')
$nonempty = @('id','title','increment','epic','objective','status','size','agent_notes')
$arrays = @('prd_refs','depends_on','blocks','decision_dependencies','deliverables','acceptance_criteria','verification')
$mandatoryArrays = @('prd_refs','deliverables','acceptance_criteria','verification')
$byId = @{}
foreach ($t in $data.tasks) {
    foreach ($f in $required) { if ($t.PSObject.Properties.Name -notcontains $f) { Fail "$($t.id): missing $f" } }
    foreach ($f in $nonempty) { if ($t.$f -isnot [string] -or [string]::IsNullOrWhiteSpace([string]$t.$f)) { Fail "$($t.id): empty/non-string $f" } }
    if ([string]$t.id -notmatch '^A[0-5]-[A-Z]+-\d{2,3}$') { Fail "Invalid task ID: $($t.id)" }
    if ($t.increment -notin @('A0','A1','A2','A3','A4','A5')) { Fail "$($t.id): increment enum" }
    if ($t.status -notin @('blocked','ready','planned','in_progress','review','done')) { Fail "$($t.id): status enum" }
    if ($t.size -notin @('XS','S')) { Fail "$($t.id): size enum" }
    if ($t.parallel_group -ne $null -and ($t.parallel_group -isnot [string] -or [string]::IsNullOrWhiteSpace($t.parallel_group))) { Fail "$($t.id): invalid parallel_group" }
    foreach ($f in $arrays) {
        if ($t.$f -isnot [array]) { Fail "$($t.id): $f must be array" }
        foreach ($v in $t.$f) { if ($v -isnot [string] -or [string]::IsNullOrWhiteSpace($v)) { Fail "$($t.id): empty/non-string element in $f" } }
        if (@($t.$f | Select-Object -Unique).Count -ne @($t.$f).Count) { Fail "$($t.id): duplicate $f element" }
    }
    foreach ($f in $mandatoryArrays) { if (@($t.$f).Count -eq 0) { Fail "$($t.id): empty $f" } }
    if ($t.PSObject.Properties.Name -contains 'execution_prerequisites') {
        if ($t.execution_prerequisites -isnot [array] -or @($t.execution_prerequisites).Count -eq 0) { Fail "$($t.id): invalid execution_prerequisites" }
        foreach ($v in $t.execution_prerequisites) { if ($v -isnot [string] -or [string]::IsNullOrWhiteSpace($v)) { Fail "$($t.id): invalid execution prerequisite" } }
    }
    foreach ($f in @('paths','modules')) {
        if ($t.scope.$f -isnot [array] -or @($t.scope.$f).Count -eq 0) { Fail "$($t.id): empty scope.$f" }
        foreach ($v in $t.scope.$f) { if ($v -isnot [string] -or [string]::IsNullOrWhiteSpace($v)) { Fail "$($t.id): invalid scope.$f" } }
    }
    if (-not [string]::IsNullOrWhiteSpace([string]$t.id)) {
        if ($byId.ContainsKey($t.id)) { Fail "Duplicate ID: $($t.id)" } else { $byId[$t.id] = $t }
    }
    if ($t.status -eq 'ready') {
        foreach ($d in $t.decision_dependencies) {
            $approval = Get-DecisionApprovalCheck -Root $Root -Reference $d
            if (-not $approval.Approved -and -not (Test-DevelopmentAuthorization $t.id ([string]$d))) { Fail "$($t.id): ready with unresolved decision $d ($($approval.Message))" }
        }
    }
}
foreach ($t in $data.tasks) {
    foreach ($d in $t.depends_on) {
        if ($d -eq $t.id) { Fail "$($t.id): self-dependency" }
        if (-not $byId.ContainsKey($d)) { Fail "$($t.id): missing dependency $d"; continue }
        if ($byId[$d].blocks -notcontains $t.id) { Fail "$($t.id): missing reverse block on $d" }
        if ($t.status -eq 'ready' -and $byId[$d].status -ne 'done') { Fail "$($t.id): ready before $d done" }
    }
    foreach ($b in $t.blocks) {
        if ($b -eq $t.id) { Fail "$($t.id): self-block" }
        if (-not $byId.ContainsKey($b)) { Fail "$($t.id): missing block ID $b"; continue }
        if ($byId[$b].depends_on -notcontains $t.id) { Fail "$($t.id): non-symmetric block $b" }
    }
}
$color = @{}
function Visit([string]$Id, [string]$Chain) {
    if ($color[$Id] -eq 1) { Fail "Dependency cycle: $Chain -> $Id"; return }
    if ($color[$Id] -eq 2) { return }
    $color[$Id] = 1
    foreach ($d in $byId[$Id].depends_on) { if ($byId.ContainsKey($d)) { Visit $d "$Chain -> $Id" } }
    $color[$Id] = 2
}
foreach ($id in $byId.Keys) { Visit $id '' }
$pattern = '\b(?:SEC|CORE|DOC|CRM|TASK|KPI|SALE|PRJ|INV|DEL|QA|MFG|FIN|EMP|PAY|COM|DASH|AI|ARCH|NFR|AT|OPEN)-\d{2}\b'
$prd = Text (Join-Path $Root 'docs/product/PRD.md')
$trace = Text (Join-Path $Root 'docs/product/REQUIREMENTS_TRACEABILITY.md')
$refs = @([regex]::Matches($prd,$pattern) | ForEach-Object { $_.Value } | Sort-Object -Unique)
$traceRefs = @([regex]::Matches($trace,$pattern) | ForEach-Object { $_.Value } | Sort-Object -Unique)
$taskRefs = @($data.tasks | ForEach-Object { $_.prd_refs } | Sort-Object -Unique)
foreach ($r in $refs) {
    if ($traceRefs -notcontains $r) { Fail "Traceability missing $r" }
    if ($taskRefs -notcontains $r) { Fail "No task prd_refs for $r (OPEN/AT need decision/acceptance task)" }
}
foreach ($r in $taskRefs) { if ($refs -notcontains $r) { Fail "Unknown task PRD ref: $r" } }
foreach ($t in $data.tasks) {
    foreach ($d in $t.decision_dependencies) {
        if ($d -match '^OPEN-\d{2}$') { if ($refs -notcontains $d) { Fail "$($t.id): unknown decision $d" } }
        elseif ($d -match '^ADR-\d{3}$') { if (-not (Test-Path -LiteralPath (Join-Path $Root "docs/architecture/adr/$d.md") -PathType Leaf)) { Fail "$($t.id): missing ADR $d" } }
        else { Fail "$($t.id): invalid decision reference $d" }
    }
}
# Best-effort local inline/reference Markdown links. Skip fenced code (including Mermaid), URLs and anchors.
# Only prune generated/dependency directories at the repository root; hidden first-party paths remain in scope.
$linkCount = 0
$excludedRootDirectories = @('.git', '.tools', 'node_modules', 'vendor')
$markdownFiles = [Collections.Generic.List[IO.FileInfo]]::new()
$directories = [Collections.Generic.Stack[string]]::new()
$directories.Push($Root)
while ($directories.Count -gt 0) {
    $directory = $directories.Pop()
    foreach ($item in Get-ChildItem -LiteralPath $directory -Force) {
        if ($item.PSIsContainer) {
            if ($directory.Equals($Root, [StringComparison]::OrdinalIgnoreCase) -and $excludedRootDirectories -contains $item.Name) { continue }
            $directories.Push($item.FullName)
        } elseif ($item.Extension -ieq '.md') {
            $markdownFiles.Add($item)
        }
    }
}
foreach ($file in $markdownFiles) {
    $content = Text $file.FullName
    $content = [regex]::Replace($content, '(?ms)^\s*(```|~~~)[^\r\n]*\r?\n.*?^\s*\1\s*$', '')
    $destinations = [Collections.Generic.List[string]]::new()
    foreach ($m in [regex]::Matches($content, '\[[^\]\r\n]*\]\(\s*(?:<([^>]+)>|([^\s\)]+))(?:\s+"[^"]*")?\s*\)')) {
        if ($m.Groups[1].Success) { $destinations.Add($m.Groups[1].Value) } else { $destinations.Add($m.Groups[2].Value) }
    }
    foreach ($m in [regex]::Matches($content, '(?m)^\s*\[[^\]]+\]:\s*<?([^\s>]+)>?')) { $destinations.Add($m.Groups[1].Value) }
    foreach ($dest in $destinations) {
        if ($dest -match '^(?:[a-zA-Z][a-zA-Z0-9+.-]*:|//|#)') { continue }
        $local = [Uri]::UnescapeDataString(($dest -split '[#?]',2)[0])
        if ([string]::IsNullOrWhiteSpace($local)) { continue }
        $linkCount++
        $target = Join-Path $file.DirectoryName $local
        if (-not (Test-Path -LiteralPath $target)) { Fail "Broken Markdown link: $($file.FullName) -> $dest" }
    }
}
if ($errors.Count -gt 0) { foreach ($e in $errors) { Write-Output "FAIL: $e" }; Write-Output "FAILED: $($errors.Count) error(s)"; exit 1 }
Write-Output "PASS: tasks=$($data.tasks.Count); PRD_IDs=$($refs.Count); local_links=$linkCount; JSON/enums/fields/DAG/reverse_edges/coverage/links valid"
exit 0
