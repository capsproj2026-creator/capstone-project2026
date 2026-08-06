#Requires -Version 5.1
param(
    [Parameter(Mandatory = $true)]
    [string]$Title,

    [Parameter(Mandatory = $true)]
    [string]$WorkingDirectory,

    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$CommandArgs
)

$ErrorActionPreference = "Continue"

$extraPaths = @(
    "$env:ProgramFiles\nodejs",
    "${env:ProgramFiles(x86)}\nodejs",
    "$env:LOCALAPPDATA\Programs\nodejs",
    "$env:APPDATA\npm",
    "$env:ProgramFiles\PHP",
    "${env:ProgramFiles(x86)}\PHP"
)
foreach ($dir in $extraPaths) {
    if ((Test-Path -LiteralPath $dir) -and ($env:Path -notlike "*$dir*")) {
        $env:Path = "$dir;$env:Path"
    }
}

Set-Location -LiteralPath $WorkingDirectory
Write-Host ("=== $Title ===") -ForegroundColor Cyan

if (-not $CommandArgs -or $CommandArgs.Count -eq 0) {
    Write-Error "No command provided."
    exit 1
}

$exe = $CommandArgs[0]
$params = @()
if ($CommandArgs.Count -gt 1) {
    $params = $CommandArgs[1..($CommandArgs.Count - 1)]
}

# Prefer .cmd shims on Windows so npm/php work without execution-policy issues.
if ($exe -eq "npm" -and (Test-Path -LiteralPath "$env:ProgramFiles\nodejs\npm.cmd")) {
    $exe = "$env:ProgramFiles\nodejs\npm.cmd"
}

Write-Host ("> " + ($CommandArgs -join " ")) -ForegroundColor DarkGray
& $exe @params
if ($null -ne $LASTEXITCODE -and $LASTEXITCODE -ne 0) {
    Write-Host ("Command exited with code $LASTEXITCODE") -ForegroundColor Red
}
