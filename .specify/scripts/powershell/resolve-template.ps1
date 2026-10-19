[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$TemplateName,
    [switch]$Json,
    [switch]$Help
)

$ErrorActionPreference = 'Stop'

if ($Help) {
    Write-Output 'Usage: resolve-template.ps1 -TemplateName <name> [-Json]'
    exit 0
}

. "$PSScriptRoot/common.ps1"
$repoRoot = Get-RepoRoot
$templateContent = Resolve-TemplateContent -TemplateName $TemplateName -RepoRoot $repoRoot
if ($null -eq $templateContent) {
    [Console]::Error.WriteLine("ERROR: Template '$TemplateName' could not be resolved")
    exit 1
}

if ($Json) {
    [PSCustomObject]@{
        TEMPLATE_NAME = $TemplateName
        TEMPLATE_CONTENT = $templateContent
    } | ConvertTo-Json -Compress
} else {
    Write-Output $templateContent
}
