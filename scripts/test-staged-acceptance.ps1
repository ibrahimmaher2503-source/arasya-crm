$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'decision-evidence.ps1')
$fixture = Join-Path ([IO.Path]::GetTempPath()) ('arasya-staged-' + [guid]::NewGuid().ToString('N'))
$count = 0
try {
    foreach ($dir in @('docs/architecture/adr', 'docs/governance/decision-approvals', 'docs/governance/evidence')) { [void][IO.Directory]::CreateDirectory((Join-Path $fixture $dir)) }
    Set-Content (Join-Path $fixture 'docs/governance/evidence/baseline.md') 'Synthetic evidence only.'
    foreach ($adr in @('ADR-002', 'ADR-003')) {
        Set-Content (Join-Path $fixture 'docs/architecture/ADR-INDEX.md') "| [$adr`: fixture] | Accepted | owners |"
        Set-Content (Join-Path $fixture "docs/architecture/adr/$adr.md") 'Status: Accepted'
        $record = "Decision: Accepted`nOwner: fixture owners`nApproved on: 2026-10-17`nApproved option: local baseline`nEvidence: docs/governance/evidence/baseline.md`nAffected tasks: A0-ARCH-902`nTests: synthetic fixture`nReview: fixture review`nAcceptance stage: Architecture`nImplementation scope: Bounded local only"
        $roles = if ($adr -eq 'ADR-002') { @('Engineering','Finance','Operations') } else { @('Engineering','IT','Security','Legal','Operations') }
        $path = Join-Path $fixture "docs/governance/decision-approvals/$adr.md"
        Set-Content $path $record
        if ((Get-DecisionApprovalCheck $fixture $adr).Approved) { throw "$adr accepted without role approvals" }; $count++
        $signed = $record
        foreach ($role in $roles) { $signed += "`nArchitecture $role approval: Approved; fixture role authority; 2026-10-17" }
        Set-Content $path $signed
        if (-not (Get-DecisionApprovalCheck $fixture $adr).Approved) { throw "$adr rejected complete architecture approvals" }; $count++
        foreach ($role in $roles) {
            Set-Content $path ($signed.Replace("Architecture $role approval: Approved; fixture role authority; 2026-10-17", "Architecture $role approval: Pending"))
            if ((Get-DecisionApprovalCheck $fixture $adr).Approved) { throw "$adr accepted pending $role" }; $count++
        }
        Set-Content $path ($signed.Replace('Acceptance stage: Architecture','Acceptance stage: Production'))
        if ((Get-DecisionApprovalCheck $fixture $adr).Approved) { throw "$adr accepted wrong stage" }; $count++
        Set-Content $path ($signed.Replace('Implementation scope: Bounded local only','Implementation scope: Production'))
        if ((Get-DecisionApprovalCheck $fixture $adr).Approved) { throw "$adr accepted production scope" }; $count++
    }
    Write-Output "PASS: staged acceptance assertions=$count"
} finally {
    if ([IO.Path]::GetFullPath($fixture).StartsWith([IO.Path]::GetTempPath()) -and (Split-Path $fixture -Leaf) -match '^arasya-staged-[a-f0-9]{32}$') { Remove-Item -LiteralPath $fixture -Recurse -Force }
}
