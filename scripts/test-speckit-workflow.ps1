[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$assertions = 0
$failures = [System.Collections.Generic.List[string]]::new()
$tempBase = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath())
$tempRoot = Join-Path $tempBase ("speckit-workflow-tests-" + [guid]::NewGuid().ToString('N'))

function Assert-Condition {
    param([bool]$Condition, [string]$Message)
    $script:assertions++
    if ($Condition) {
        Write-Output "PASS: $Message"
    } else {
        $script:failures.Add($Message)
        Write-Output "FAIL: $Message"
    }
}

function ConvertTo-CommandLineArgument {
    param([string]$Value)

    if ($Value.Length -gt 0 -and $Value -notmatch '[\s"]') { return $Value }

    $builder = [System.Text.StringBuilder]::new()
    [void]$builder.Append('"')
    $backslashes = 0
    foreach ($character in $Value.ToCharArray()) {
        if ($character -eq '\') {
            $backslashes++
            continue
        }
        $count = if ($character -eq '"') { (2 * $backslashes) + 1 } else { $backslashes }
        for ($index = 0; $index -lt $count; $index++) { [void]$builder.Append('\') }
        [void]$builder.Append($character)
        $backslashes = 0
    }
    for ($index = 0; $index -lt (2 * $backslashes); $index++) { [void]$builder.Append('\') }
    [void]$builder.Append('"')
    return $builder.ToString()
}

function Invoke-PowerShellFile {
    param(
        [string]$Executable,
        [string]$FilePath,
        [string[]]$Arguments = @(),
        [string]$WorkingDirectory,
        [ValidateRange(1, 2147483647)][int]$TimeoutMilliseconds = 30000
    )

    $processInfo = New-Object System.Diagnostics.ProcessStartInfo
    $processInfo.FileName = $Executable
    $processInfo.WorkingDirectory = $WorkingDirectory
    $processInfo.UseShellExecute = $false
    $processInfo.CreateNoWindow = $true
    $processInfo.RedirectStandardOutput = $true
    $processInfo.RedirectStandardError = $true
    $commandArguments = @('-NoProfile', '-File', $FilePath) + $Arguments
    $processInfo.Arguments = (($commandArguments | ForEach-Object { ConvertTo-CommandLineArgument ([string]$_) }) -join ' ')
    $process = New-Object System.Diagnostics.Process
    try {
        $process.StartInfo = $processInfo
        if (-not $process.Start()) { throw "Could not start $Executable" }
        $stdoutTask = $process.StandardOutput.ReadToEndAsync()
        $stderrTask = $process.StandardError.ReadToEndAsync()
        $timedOut = -not $process.WaitForExit($TimeoutMilliseconds)
        if ($timedOut) {
            try {
                $process.Kill()
            } catch {
                if (-not $process.HasExited) { throw }
            }
            if (-not $process.WaitForExit(5000)) {
                throw "Timed out after $TimeoutMilliseconds ms; child process did not exit after termination"
            }
        }
        $stdout = $stdoutTask.Result
        $stderr = $stderrTask.Result
        $capturedOutput = @()
        if (-not [string]::IsNullOrWhiteSpace($stdout)) { $capturedOutput += $stdout }
        if (-not [string]::IsNullOrWhiteSpace($stderr)) { $capturedOutput += $stderr }
        return [PSCustomObject]@{
            ExitCode = $process.ExitCode
            TimedOut = $timedOut
            ProcessExited = $process.HasExited
            Output = ($capturedOutput -join [Environment]::NewLine).Trim()
        }
    } finally {
        $process.Dispose()
    }
}

function Get-FileHashes {
    param([string]$Directory)
    $hashes = @{}
    foreach ($file in Get-ChildItem -LiteralPath $Directory -File -Recurse) {
        $relativePath = $file.FullName.Substring($Directory.Length).TrimStart('\', '/')
        $hashes[$relativePath] = (Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash
    }
    return $hashes
}

try {
    $repoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
    $checker = Join-Path $repoRoot 'scripts/test-speckit-install.ps1'
    $resolverSource = Join-Path $repoRoot '.specify/scripts/powershell/resolve-template.ps1'
    $commonSource = Join-Path $repoRoot '.specify/scripts/powershell/common.ps1'

    Assert-Condition (Test-Path -LiteralPath $checker -PathType Leaf) 'install checker exists in the project checkout'
    Assert-Condition (Test-Path -LiteralPath $resolverSource -PathType Leaf) 'template resolver exists in the project checkout'
    Assert-Condition (Test-Path -LiteralPath $commonSource -PathType Leaf) 'shared resolver functions exist in the project checkout'

    New-Item -ItemType Directory -Path $tempRoot | Out-Null
    $unrelatedDirectory = Join-Path $tempRoot 'unrelated-working-directory'
    New-Item -ItemType Directory -Path $unrelatedDirectory | Out-Null

    $powerShell = Get-Command 'powershell.exe' -ErrorAction SilentlyContinue
    Assert-Condition ($null -ne $powerShell) 'Windows PowerShell 5.1 is available for the checker regression'
    $hosts = [System.Collections.Generic.List[object]]::new()
    if ($powerShell) {
        $hosts.Add([PSCustomObject]@{ Name = 'Windows PowerShell 5.1'; Path = $powerShell.Source })
    }
    $pwsh = Get-Command 'pwsh.exe' -ErrorAction SilentlyContinue
    if ($pwsh) {
        $hosts.Add([PSCustomObject]@{ Name = 'pwsh'; Path = $pwsh.Source })
    } else {
        Write-Output 'SKIP: pwsh is not installed; the Windows PowerShell 5.1 check still runs'
    }

    $sleepScript = Join-Path $tempRoot 'sleep.ps1'
    [System.IO.File]::WriteAllText($sleepScript, 'Start-Sleep -Seconds 5', [System.Text.Encoding]::ASCII)
    foreach ($hostEntry in $hosts) {
        $timeoutResult = $null
        $timeoutError = $null
        try {
            $timeoutResult = Invoke-PowerShellFile -Executable $hostEntry.Path -FilePath $sleepScript -WorkingDirectory $tempRoot -TimeoutMilliseconds 200
        } catch {
            $timeoutError = $_.Exception.Message
        }
        $timedOutAndExited = $null -ne $timeoutResult -and $timeoutResult.TimedOut -and $timeoutResult.ProcessExited
        Assert-Condition $timedOutAndExited "$($hostEntry.Name) child timeout is reported and its process is exited"
        if ($timeoutError) { Write-Output "  $timeoutError" }
    }

    foreach ($hostEntry in $hosts) {
        $result = Invoke-PowerShellFile -Executable $hostEntry.Path -FilePath $checker -WorkingDirectory $unrelatedDirectory
        Assert-Condition ($result.ExitCode -eq 0) "$($hostEntry.Name) runs the install checker without -Root from an unrelated directory (exit $($result.ExitCode))"
        if ($result.ExitCode -ne 0 -and $result.Output) {
            Write-Output "  $($result.Output -replace "`r?`n", "`n  ")"
        }
    }

    $fixtureRoot = Join-Path $tempRoot 'resolver-fixture'
    $fixturePowerShell = Join-Path $fixtureRoot '.specify/scripts/powershell'
    $fixtureTemplates = Join-Path $fixtureRoot '.specify/templates'
    $fixtureOverrides = Join-Path $fixtureTemplates 'overrides'
    New-Item -ItemType Directory -Path $fixturePowerShell -Force | Out-Null
    New-Item -ItemType Directory -Path $fixtureOverrides -Force | Out-Null
    Copy-Item -LiteralPath $resolverSource -Destination (Join-Path $fixturePowerShell 'resolve-template.ps1')
    Copy-Item -LiteralPath $commonSource -Destination (Join-Path $fixturePowerShell 'common.ps1')
    $expectedContent = 'local override ' + [char]0x2192 + ' caf' + [char]0xE9
    $utf8WithoutBom = [System.Text.UTF8Encoding]::new($false)
    [System.IO.File]::WriteAllText((Join-Path $fixtureTemplates 'probe.md'), 'core template marker', $utf8WithoutBom)
    [System.IO.File]::WriteAllText((Join-Path $fixtureOverrides 'probe.md'), $expectedContent, $utf8WithoutBom)
    $jsonWrapper = Join-Path $fixtureRoot 'invoke-resolver-json.ps1'
    $jsonWrapperSource = @'
param([string]$ResolverPath, [string]$TemplateName)
$ErrorActionPreference = 'Stop'
$json = & $ResolverPath -TemplateName $TemplateName -Json
[Console]::Out.Write([Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes([string]$json)))
'@
    [System.IO.File]::WriteAllText($jsonWrapper, $jsonWrapperSource, [System.Text.Encoding]::ASCII)

    $beforeHashes = Get-FileHashes -Directory $fixtureRoot
    $fixtureResolver = Join-Path $fixturePowerShell 'resolve-template.ps1'
    foreach ($hostEntry in $hosts) {
        $resolverArguments = @('-ResolverPath', $fixtureResolver, '-TemplateName', 'probe')
        $validResult = Invoke-PowerShellFile -Executable $hostEntry.Path -FilePath $jsonWrapper -Arguments $resolverArguments -WorkingDirectory $fixtureRoot
        $validJson = $null
        $validJsonParsed = $false
        if ($validResult.ExitCode -eq 0) {
            try {
                $jsonText = [System.Text.Encoding]::UTF8.GetString([Convert]::FromBase64String($validResult.Output))
                $validJson = $jsonText | ConvertFrom-Json -ErrorAction Stop
                $validJsonParsed = $true
            } catch {
                $validJsonParsed = $false
            }
        }
        Assert-Condition ($validResult.ExitCode -eq 0 -and $validJsonParsed) "$($hostEntry.Name) resolver -Json returns valid JSON for a local project template"
        $contentIsString = $validJsonParsed -and ($validJson.TEMPLATE_CONTENT -is [string])
        Assert-Condition $contentIsString "$($hostEntry.Name) resolver JSON returns TEMPLATE_CONTENT as a string"
        $overrideWins = $contentIsString -and $validJson.TEMPLATE_CONTENT -ceq $expectedContent
        Assert-Condition $overrideWins "$($hostEntry.Name) resolves the exact UTF-8 project override ahead of the core template"
        if (-not $overrideWins -and $validJsonParsed) {
            Write-Output "  Resolver returned content: '$($validJson.TEMPLATE_CONTENT)'"
        }
        Assert-Condition ($validJsonParsed -and $validJson.TEMPLATE_NAME -ceq 'probe') "$($hostEntry.Name) resolver JSON identifies the requested template"

        $missingArguments = @('-ResolverPath', $fixtureResolver, '-TemplateName', 'missing')
        $missingResult = Invoke-PowerShellFile -Executable $hostEntry.Path -FilePath $jsonWrapper -Arguments $missingArguments -WorkingDirectory $fixtureRoot
        Assert-Condition ($missingResult.ExitCode -ne 0) "$($hostEntry.Name) resolver exits unsuccessfully when the requested template is missing"
    }

    $afterHashes = Get-FileHashes -Directory $fixtureRoot
    $sameHashes = ($beforeHashes.Count -eq $afterHashes.Count)
    if ($sameHashes) {
        foreach ($relativePath in $beforeHashes.Keys) {
            if ($afterHashes[$relativePath] -ne $beforeHashes[$relativePath]) {
                $sameHashes = $false
                break
            }
        }
    }
    Assert-Condition $sameHashes 'resolver success and missing-template calls leave fixture files unchanged'
} catch {
    $failures.Add("Unexpected harness error: $_")
    Write-Output "FAIL: Unexpected harness error: $_"
} finally {
    $resolvedTemp = [System.IO.Path]::GetFullPath($tempRoot)
    $tempPrefix = $tempBase.TrimEnd('\', '/') + [System.IO.Path]::DirectorySeparatorChar
    $isOwnedTemp = $resolvedTemp.StartsWith($tempPrefix, [System.StringComparison]::OrdinalIgnoreCase) -and
        [System.IO.Path]::GetFileName($resolvedTemp).StartsWith('speckit-workflow-tests-', [System.StringComparison]::Ordinal)
    if ($isOwnedTemp -and (Test-Path -LiteralPath $resolvedTemp -PathType Container)) {
        Remove-Item -LiteralPath $resolvedTemp -Recurse -Force
    }
}

if ($failures.Count -gt 0) {
    Write-Output "FAILED: $($failures.Count) of $assertions assertion(s) failed"
    exit 1
}

Write-Output "PASSED: $assertions assertion(s)"
exit 0
