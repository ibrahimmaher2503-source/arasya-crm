# Shared, read-only decision approval evidence checks for task-loop and docs validation.
function Get-DecisionApprovalCheck {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)][string]$Root,
        [Parameter(Mandatory = $true)][string]$Reference
    )

    $result = [pscustomobject]@{ Approved = $false; Message = 'Decision reference is not approved.' }
    $Root = [IO.Path]::GetFullPath($Root)
    $recordPath = Join-Path $Root ("docs/governance/decision-approvals/{0}.md" -f $Reference)
    if (-not (Test-Path -LiteralPath $recordPath -PathType Leaf)) {
        $result.Message = "Missing approval evidence record: docs/governance/decision-approvals/$Reference.md"
        return $result
    }

    try {
        $record = [IO.File]::ReadAllText($recordPath, [Text.Encoding]::UTF8)
    } catch {
        $result.Message = "Cannot read approval evidence record for ${Reference}: $($_.Exception.Message)"
        return $result
    }

    $expectedDecision = $null
    $sourceStatus = $null
    if ($Reference -match '^OPEN-\d{2}$') {
        $expectedDecision = 'Approved'
        $sourcePath = Join-Path $Root 'docs/governance/OPEN_DECISIONS.md'
        if (-not (Test-Path -LiteralPath $sourcePath -PathType Leaf)) {
            $result.Message = 'Missing docs/governance/OPEN_DECISIONS.md.'
            return $result
        }
        try { $sourceText = [IO.File]::ReadAllText($sourcePath, [Text.Encoding]::UTF8) } catch {
            $result.Message = "Cannot read OPEN decision register: $($_.Exception.Message)"
            return $result
        }
        foreach ($line in ($sourceText -split "`r?`n")) {
            $trimmed = $line.Trim()
            if (-not $trimmed.StartsWith('|')) { continue }
            $cells = @($trimmed.Trim([char[]]@('|')).Split('|') | ForEach-Object { $_.Trim() })
            if ($cells.Count -ge 6 -and $cells[0] -eq $Reference) {
                $sourceStatus = $cells[$cells.Count - 1]
                break
            }
        }
        if ($sourceStatus -cne 'Approved') {
            $result.Message = "OPEN decision $Reference is not marked Approved in OPEN_DECISIONS.md."
            return $result
        }
    } elseif ($Reference -match '^ADR-\d{3}$') {
        $expectedDecision = 'Accepted'
        $indexPath = Join-Path $Root 'docs/architecture/ADR-INDEX.md'
        $adrPath = Join-Path $Root ("docs/architecture/adr/{0}.md" -f $Reference)
        if (-not (Test-Path -LiteralPath $indexPath -PathType Leaf) -or -not (Test-Path -LiteralPath $adrPath -PathType Leaf)) {
            $result.Message = "Missing ADR index or docs/architecture/adr/$Reference.md."
            return $result
        }
        try {
            $indexText = [IO.File]::ReadAllText($indexPath, [Text.Encoding]::UTF8)
            $adrText = [IO.File]::ReadAllText($adrPath, [Text.Encoding]::UTF8)
        } catch {
            $result.Message = "Cannot read ADR approval sources for ${Reference}: $($_.Exception.Message)"
            return $result
        }
        foreach ($line in ($indexText -split "`r?`n")) {
            $trimmed = $line.Trim()
            if ($trimmed -match ('^\|\s*\[?' + [regex]::Escape($Reference) + '(?:\b|:)')) {
                $cells = @($trimmed.Trim([char[]]@('|')).Split('|') | ForEach-Object { $_.Trim() })
                if ($cells.Count -ge 2) { $sourceStatus = $cells[1] }
                break
            }
        }
        $adrStatus = $null
        if ($adrText -match '(?im)^\s*Status:\s*([^\r\n]+)') { $adrStatus = $Matches[1].Trim() }
        if ($sourceStatus -cne 'Accepted' -or $adrStatus -cne 'Accepted') {
            $result.Message = "ADR $Reference must be Accepted in both ADR-INDEX.md and its ADR file."
            return $result
        }
    } else {
        $result.Message = "Invalid decision reference: $Reference"
        return $result
    }

    $requiredFields = @('Decision', 'Owner', 'Approved on', 'Approved option', 'Evidence', 'Affected tasks', 'Tests', 'Review')
    $values = @{}
    foreach ($field in $requiredFields) {
        $pattern = '(?im)^\s*' + [regex]::Escape($field) + ':\s*(.*?)\s*$'
        $matches = [regex]::Matches($record, $pattern)
        if ($matches.Count -ne 1) {
            $result.Message = "Approval record for ${Reference} must contain exactly one '${field}:' field."
            return $result
        }
        $values[$field] = $matches[0].Groups[1].Value.Trim()
        if ([string]::IsNullOrWhiteSpace($values[$field]) -or $values[$field] -match '^(?i:TBD|OPEN|unknown|not available|غير متوفر)$') {
            $result.Message = "Approval record for ${Reference} has an empty or placeholder '${field}:' field."
            return $result
        }
    }
    if ($values['Decision'] -cne $expectedDecision) {
        $result.Message = "Approval record for $Reference must say 'Decision: $expectedDecision'."
        return $result
    }
    $approvedDate = [datetime]::MinValue
    if (-not [datetime]::TryParseExact($values['Approved on'], 'yyyy-MM-dd', [Globalization.CultureInfo]::InvariantCulture, [Globalization.DateTimeStyles]::None, [ref]$approvedDate)) {
        $result.Message = "Approval record for $Reference must use Approved on: yyyy-MM-dd."
        return $result
    }

    $evidenceRelative = $values['Evidence'].Replace('/', [IO.Path]::DirectorySeparatorChar).Replace('\', [IO.Path]::DirectorySeparatorChar)
    if ([IO.Path]::IsPathRooted($evidenceRelative)) {
        $result.Message = "Approval evidence for $Reference must be a repository-relative file path."
        return $result
    }
    $evidencePath = [IO.Path]::GetFullPath((Join-Path $Root $evidenceRelative))
    $rootPrefix = $Root.TrimEnd([char[]]@('\', '/')) + [IO.Path]::DirectorySeparatorChar
    if (-not $evidencePath.StartsWith($rootPrefix, [StringComparison]::OrdinalIgnoreCase) -or
        -not (Test-Path -LiteralPath $evidencePath -PathType Leaf) -or
        $evidencePath.Equals([IO.Path]::GetFullPath($recordPath), [StringComparison]::OrdinalIgnoreCase)) {
        $result.Message = "Approval evidence for $Reference must point to an existing, separate file inside the repository."
        return $result
    }

    # These two ADRs authorize local architecture implementation, never release acceptance.
    if ($Reference -in @('ADR-002', 'ADR-003')) {
        $stageFields = [ordered]@{ 'Acceptance stage' = 'Architecture'; 'Implementation scope' = 'Bounded local only' }
        foreach ($field in $stageFields.Keys) {
            $entries = [regex]::Matches($record, ('(?m)^' + [regex]::Escape($field) + ': ([^\r\n]+)\r?$'))
            if ($entries.Count -ne 1 -or $entries[0].Groups[1].Value -cne $stageFields[$field]) {
                $result.Message = "$Reference requires $field`: $($stageFields[$field])."
                return $result
            }
        }
        $roles = if ($Reference -eq 'ADR-002') { @('Engineering', 'Finance', 'Operations') } else { @('Engineering', 'IT', 'Security', 'Legal', 'Operations') }
        foreach ($role in $roles) {
            $entries = [regex]::Matches($record, ('(?m)^Architecture ' + $role + ' approval: ([^\r\n]+)\r?$'))
            $date = [datetime]::MinValue
            if ($entries.Count -ne 1 -or $entries[0].Groups[1].Value -cnotmatch '^Approved; ([^;]+); (\d{4}-\d{2}-\d{2})$' -or
                $Matches[1].Trim() -match '^(?i:pending|unknown|TBD)$' -or
                -not [datetime]::TryParseExact($Matches[2], 'yyyy-MM-dd', [Globalization.CultureInfo]::InvariantCulture, [Globalization.DateTimeStyles]::None, [ref]$date)) {
                $result.Message = "$Reference requires a named, dated Architecture $role approval."
                return $result
            }
        }
    }
    $result.Approved = $true
    $result.Message = "$Reference has an explicit approval record and evidence file."
    return $result
}
