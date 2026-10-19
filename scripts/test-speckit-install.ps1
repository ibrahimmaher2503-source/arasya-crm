[CmdletBinding()]
param(
    [string]$Root
)

$ErrorActionPreference = 'Stop'
if ([string]::IsNullOrWhiteSpace($Root)) { $Root = Split-Path $PSScriptRoot -Parent }
$Root = [System.IO.Path]::GetFullPath($Root)
$failures = [System.Collections.Generic.List[string]]::new()

function Require-Condition {
    param(
        [bool]$Condition,
        [string]$Message
    )
    if (-not $Condition) { $failures.Add($Message) }
}

$skillNames = @(
    'speckit-constitution',
    'speckit-specify',
    'speckit-baseline',
    'speckit-clarify',
    'speckit-plan',
    'speckit-tasks',
    'speckit-analyze',
    'speckit-converge',
    'speckit-implement',
    'speckit-checklist',
    'speckit-taskstoissues'
)

$skillFiles = [System.Collections.Generic.List[string]]::new()
foreach ($skillName in $skillNames) {
    $skillFile = Join-Path $Root ".agents/skills/$skillName/SKILL.md"
    Require-Condition (Test-Path -LiteralPath $skillFile -PathType Leaf) "Missing Codex skill entry: $skillFile"
    if (-not (Test-Path -LiteralPath $skillFile -PathType Leaf)) { continue }
    $skillFiles.Add($skillFile)
    $content = [System.IO.File]::ReadAllText($skillFile, [System.Text.Encoding]::UTF8)
    Require-Condition ($content -match [regex]::Escape($skillName)) "Skill name missing from its entry: $skillFile"
    Require-Condition ($content -match 'Project Workflow Guardrails') "Project guardrail block missing: $skillFile"
    Require-Condition ($content -match '\.specify/scripts/powershell/') "No PowerShell helper reference found: $skillFile"
    Require-Condition ($content -notmatch '\.specify/scripts/bash/|create-new-feature\.sh|mkdir -p') "Unconverted Bash helper command remains: $skillFile"

    $rulesPath = Join-Path (Split-Path $skillFile -Parent) '../../../docs/agents/SPECKIT_INTEGRATION.md'
    Require-Condition (Test-Path -LiteralPath $rulesPath -PathType Leaf) "Broken project-rule reference from $skillFile"

    foreach ($match in [regex]::Matches($content, '\.specify/scripts/powershell/([A-Za-z0-9._-]+\.ps1)')) {
        $referencedScript = Join-Path $Root ".specify/scripts/powershell/$($match.Groups[1].Value)"
        Require-Condition (Test-Path -LiteralPath $referencedScript -PathType Leaf) "Missing referenced PowerShell helper: $referencedScript"
    }
}

$requiredTemplates = @(
    'checklist-template.md',
    'constitution-template.md',
    'plan-template.md',
    'spec-template.md',
    'tasks-template.md'
)
foreach ($template in $requiredTemplates) {
    $templatePath = Join-Path $Root ".specify/templates/$template"
    Require-Condition (Test-Path -LiteralPath $templatePath -PathType Leaf) "Missing Spec Kit template: $templatePath"
}

$requiredScripts = @(
    'check-prerequisites.ps1',
    'common.ps1',
    'create-new-feature.ps1',
    'resolve-template.ps1',
    'setup-plan.ps1',
    'setup-tasks.ps1'
)
$scriptDirectory = Join-Path $Root '.specify/scripts/powershell'
foreach ($scriptName in $requiredScripts) {
    $scriptPath = Join-Path $scriptDirectory $scriptName
    Require-Condition (Test-Path -LiteralPath $scriptPath -PathType Leaf) "Missing PowerShell helper: $scriptPath"
}

$allScripts = @(Get-ChildItem -LiteralPath $scriptDirectory -Filter '*.ps1' -File -ErrorAction SilentlyContinue)
$syntaxErrors = [System.Collections.Generic.List[string]]::new()
foreach ($scriptFile in $allScripts) {
    $tokens = $null
    $parseErrors = $null
    [void][System.Management.Automation.Language.Parser]::ParseFile($scriptFile.FullName, [ref]$tokens, [ref]$parseErrors)
    foreach ($parseError in $parseErrors) {
        $syntaxErrors.Add("$($scriptFile.FullName):$($parseError.Extent.StartLineNumber): $($parseError.Message)")
    }
}
foreach ($syntaxError in $syntaxErrors) { $failures.Add("PowerShell parse error: $syntaxError") }

$integrationDoc = Join-Path $Root 'docs/agents/SPECKIT_INTEGRATION.md'
$skillsInstructions = Join-Path $Root '.agents/skills/AGENTS.md'
$backlog = Join-Path $Root 'docs/backlog/tasks.json'
Require-Condition (Test-Path -LiteralPath $integrationDoc -PathType Leaf) "Missing integration documentation: $integrationDoc"
Require-Condition (Test-Path -LiteralPath $skillsInstructions -PathType Leaf) "Missing scoped AGENTS.md: $skillsInstructions"
Require-Condition (Test-Path -LiteralPath $backlog -PathType Leaf) "Missing authoritative backlog: $backlog"
if (Test-Path -LiteralPath $integrationDoc -PathType Leaf) {
    $governance = [System.IO.File]::ReadAllText($integrationDoc, [System.Text.Encoding]::UTF8)
    Require-Condition ($governance.Contains('docs/backlog/tasks.json')) 'Integration note does not name the authoritative backlog'
    Require-Condition ($governance.Contains('specs/<feature>/tasks.md')) 'Integration note does not mark feature tasks.md as supplemental'
    Require-Condition ($governance.Contains('OPEN') -and $governance.Contains('ADR')) 'Integration note omits decision approval guardrails'
    Require-Condition ($governance.Contains('Issues') -and $governance.Contains('فروع Git')) 'Integration note omits external publication and branch guardrails'
}

$lockPath = Join-Path $Root 'skills-lock.json'
Require-Condition (Test-Path -LiteralPath $lockPath -PathType Leaf) "Missing project skills lock file: $lockPath"
if (Test-Path -LiteralPath $lockPath -PathType Leaf) {
    try {
        $lock = [System.IO.File]::ReadAllText($lockPath, [System.Text.Encoding]::UTF8) | ConvertFrom-Json
        foreach ($skillName in $skillNames) {
            Require-Condition ($null -ne $lock.skills.PSObject.Properties[$skillName]) "Skill missing from skills-lock.json: $skillName"
        }
        Require-Condition ($null -eq $lock.skills.PSObject.Properties['claude-command-converter']) 'Unrequested claude-command-converter utility is present in skills-lock.json'
    } catch {
        $failures.Add("Invalid skills-lock.json: $_")
    }
}

$integrationPath = Join-Path $Root '.specify/integration.json'
Require-Condition (Test-Path -LiteralPath (Join-Path $Root '.specify/memory/constitution.md') -PathType Leaf) 'Missing active project constitution'
Require-Condition (Test-Path -LiteralPath $integrationPath -PathType Leaf) "Missing Spec Kit integration state: $integrationPath"
if (Test-Path -LiteralPath $integrationPath -PathType Leaf) {
    try {
        $integration = [System.IO.File]::ReadAllText($integrationPath, [System.Text.Encoding]::UTF8) | ConvertFrom-Json
        Require-Condition ($integration.integration -eq 'codex') 'Spec Kit integration state is not Codex'
        Require-Condition ($integration.integration_settings.codex.script -eq 'ps') 'Spec Kit integration is not configured for PowerShell'
    } catch {
        $failures.Add("Invalid .specify/integration.json: $_")
    }
}

# Exercise only read-only helpers; do not create feature artifacts or mutate backlog state.
Push-Location -LiteralPath $Root
try {
    $resolver = Join-Path $Root '.specify/scripts/powershell/resolve-template.ps1'
    if (Test-Path -LiteralPath $resolver -PathType Leaf) {
        try {
            $resolved = (& $resolver -TemplateName 'spec-template' -Json) | ConvertFrom-Json
            Require-Condition ($resolved.TEMPLATE_CONTENT -match '# Feature Specification:') 'PowerShell template resolver did not return the spec template'
            foreach ($templateName in @('spec-template', 'plan-template', 'tasks-template')) {
                $overridePath = Join-Path $Root ".specify/templates/overrides/$templateName.md"
                Require-Condition (Test-Path -LiteralPath $overridePath -PathType Leaf) "Missing project workflow template: $templateName"
                if (Test-Path -LiteralPath $overridePath -PathType Leaf) {
                    $actual = (& $resolver -TemplateName $templateName -Json) | ConvertFrom-Json
                    $expected = [IO.File]::ReadAllText($overridePath, [Text.Encoding]::UTF8)
                    Require-Condition ($actual.TEMPLATE_CONTENT.Trim() -ceq $expected.Trim()) "Project override was not selected: $templateName"
                }
            }
        } catch {
            $failures.Add("Read-only template resolver check failed: $_")
        }
    }

    $prerequisites = Join-Path $Root '.specify/scripts/powershell/check-prerequisites.ps1'
    if (Test-Path -LiteralPath $prerequisites -PathType Leaf) {
        try {
            $paths = (& $prerequisites -Json -PathsOnly) | ConvertFrom-Json
            Require-Condition ([System.IO.Path]::GetFullPath($paths.REPO_ROOT) -eq $Root) 'Paths-only helper returned an unexpected repository root'
            Require-Condition (-not [string]::IsNullOrWhiteSpace([string]$paths.FEATURE_SPEC)) 'Paths-only helper omitted FEATURE_SPEC'
        } catch {
            $failures.Add("Read-only paths helper check failed: $_")
        }
    }
} finally {
    Pop-Location
}

if ($failures.Count -gt 0) {
    foreach ($failure in $failures) { Write-Output "FAIL: $failure" }
    Write-Output "FAILED: $($failures.Count) assertion(s)"
    exit 1
}

Write-Output "PASS: skills=$($skillFiles.Count); powershell_scripts=$($allScripts.Count); templates=$($requiredTemplates.Count); Codex paths, local references, governance, syntax, and read-only helper checks valid"
exit 0
