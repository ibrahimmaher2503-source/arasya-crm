$ErrorActionPreference = 'Stop'

$tempRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd([char[]]@([IO.Path]::DirectorySeparatorChar, [IO.Path]::AltDirectorySeparatorChar))
$fixtureName = 'validate-docs-fixture-' + [guid]::NewGuid().ToString('N')
$fixtureRoot = [IO.Path]::GetFullPath((Join-Path $tempRoot $fixtureName))
$tempPrefix = $tempRoot + [IO.Path]::DirectorySeparatorChar
if (-not $fixtureRoot.StartsWith($tempPrefix, [StringComparison]::OrdinalIgnoreCase) -or (Test-Path -LiteralPath $fixtureRoot)) {
    throw 'Refusing an unexpected or pre-existing documentation fixture path.'
}

$utf8 = [Text.UTF8Encoding]::new($false)
New-Item -ItemType Directory -Path $fixtureRoot | Out-Null
try {
    $directories = @(
        'docs/agents',
        'docs/backlog',
        'docs/design-system/references',
        'docs/governance',
        'docs/product',
        'scripts',
        'app/Modules/Shared/Application',
        'resources/css',
        'resources/views/components/ui',
        '.agents/skills',
        'test-results/fixture-run'
    )
    foreach ($directory in $directories) {
        New-Item -ItemType Directory -Path (Join-Path $fixtureRoot $directory) -Force | Out-Null
    }

    foreach ($scriptName in @('validate-docs.ps1', 'decision-evidence.ps1')) {
        Copy-Item -LiteralPath (Join-Path $PSScriptRoot $scriptName) -Destination (Join-Path $fixtureRoot "scripts/$scriptName")
    }

    $task = [ordered]@{
        id = 'A0-ARCH-903'
        title = 'Documentation fixture link validation'
        increment = 'A0'
        epic = 'A0'
        objective = 'Exercise the documentation validator with isolated links.'
        prd_refs = @('DOC-01')
        depends_on = @()
        blocks = @()
        decision_dependencies = @()
        scope = [ordered]@{ paths = @('scripts/validate-docs.ps1'); modules = @('A0') }
        deliverables = @('Synthetic documentation fixture')
        acceptance_criteria = @('Valid local links pass; missing local links fail.')
        verification = @('scripts/test-validate-docs-fixture.ps1')
        parallel_group = $null
        status = 'planned'
        size = 'S'
        agent_notes = 'Synthetic test data only; no business or acceptance evidence.'
    }
    $tasksJson = [ordered]@{ tasks = @($task) } | ConvertTo-Json -Depth 8
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/backlog/tasks.json'), $tasksJson, $utf8)
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/governance/gate-policy.json'), '{"model":"Founder-Led","founder_authority":"Fixture","development_authorizations":[]}', $utf8)
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/product/PRD.md'), "# Fixture PRD`n`nDOC-01 describes synthetic link validation.`n", $utf8)
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/product/REQUIREMENTS_TRACEABILITY.md'), "# Fixture traceability`n`nDOC-01 is covered by the fixture test.`n", $utf8)
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/design-system/README.md'), @'
# Synthetic first-party references

[Application source](../../app/Modules/Shared/Application/ModuleCatalog.php)
[Design tokens](../../resources/css/design-system.css)
[UI components](../../resources/views/components/ui)
[Hidden skill instructions](../../.agents/skills/AGENTS.md)
[Synthetic reference image](references/access.png)
[Synthetic test-result image](../../test-results/fixture-run/desktop.png)
'@, $utf8)
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'docs/agents/SPECKIT_INTEGRATION.md'), '[Local skill policy](../../.agents/skills/AGENTS.md)', $utf8)
    [IO.File]::WriteAllText((Join-Path $fixtureRoot '.agents/skills/AGENTS.md'), 'Synthetic hidden first-party target.', $utf8)
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'app/Modules/Shared/Application/ModuleCatalog.php'), '<?php // synthetic source target', $utf8)
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'resources/css/design-system.css'), '/* synthetic design token target */', $utf8)
    [IO.File]::WriteAllText((Join-Path $fixtureRoot 'resources/views/components/ui/README.md'), 'Synthetic component target.', $utf8)
    [IO.File]::WriteAllBytes((Join-Path $fixtureRoot 'docs/design-system/references/access.png'), [byte[]]@())
    [IO.File]::WriteAllBytes((Join-Path $fixtureRoot 'test-results/fixture-run/desktop.png'), [byte[]]@())

    foreach ($name in @('.git', '.tools', 'node_modules', 'vendor')) {
        $excludedDirectory = Join-Path $fixtureRoot $name
        New-Item -ItemType Directory -Path $excludedDirectory | Out-Null
        [IO.File]::WriteAllText((Join-Path $excludedDirectory 'excluded-bad-link.md'), '[bad](missing-excluded-target.md)', $utf8)
    }

    $hiddenFixture = Join-Path $fixtureRoot '.first-party-fixture'
    New-Item -ItemType Directory -Path $hiddenFixture | Out-Null
    [IO.File]::WriteAllText((Join-Path $hiddenFixture 'valid.md'), '[valid](target.md)', $utf8)
    [IO.File]::WriteAllText((Join-Path $hiddenFixture 'target.md'), 'Valid hidden first-party target.', $utf8)

    $validator = Join-Path $fixtureRoot 'scripts/validate-docs.ps1'
    $powerShellName = if ($PSVersionTable.PSEdition -eq 'Core') { 'pwsh.exe' } else { 'powershell.exe' }
    $powerShell = (Get-Command $powerShellName -ErrorAction Stop).Source
    $arguments = @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $validator, '-Root', $fixtureRoot)
    $output = & $powerShell @arguments 2>&1
    $exitCode = $LASTEXITCODE
    $outputText = $output -join "`n"
    if ($exitCode -ne 0 -or $outputText -notmatch 'PASS: tasks=') {
        throw "Valid external, resource, hidden-skill, image, and hidden-Markdown fixture links should pass. Exit=$exitCode`n$outputText"
    }
    Write-Output 'PASS: valid app, resources, hidden-skill, image, synthetic test-result, and hidden first-party links pass; root-generated directories are skipped.'

    $brokenPath = Join-Path $hiddenFixture 'broken.md'
    [IO.File]::WriteAllText($brokenPath, '[broken](missing-first-party-target.md)', $utf8)
    $output = & $powerShell @arguments 2>&1
    $exitCode = $LASTEXITCODE
    $outputText = $output -join "`n"
    if ($exitCode -eq 0 -or $outputText -notmatch 'Broken Markdown link: .*\.first-party-fixture[\\/]broken\.md -> missing-first-party-target\.md') {
        throw "A genuinely missing target under a hidden first-party directory must fail validation. Exit=$exitCode`n$outputText"
    }
    Write-Output 'PASS: a genuinely missing target under a hidden first-party directory fails validation.'

    [IO.File]::WriteAllText((Join-Path $hiddenFixture 'missing-first-party-target.md'), 'Fixture target added.', $utf8)
    $output = & $powerShell @arguments 2>&1
    $exitCode = $LASTEXITCODE
    $outputText = $output -join "`n"
    if ($exitCode -ne 0 -or $outputText -notmatch 'PASS: tasks=') {
        throw "The repaired synthetic fixture should pass validation. Exit=$exitCode`n$outputText"
    }
    Write-Output 'PASS: adding the missing target restores validation.'
} finally {
    $expectedFixture = [IO.Path]::GetFullPath((Join-Path $tempRoot $fixtureName))
    if ($fixtureRoot -ceq $expectedFixture -and $fixtureRoot.StartsWith($tempPrefix, [StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $fixtureRoot)) {
        Remove-Item -LiteralPath $fixtureRoot -Recurse -Force
    }
}
