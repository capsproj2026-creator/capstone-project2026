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
$wingetPhp = Get-ChildItem -Path "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Filter "PHP.*" -Directory -ErrorAction SilentlyContinue |
    ForEach-Object { $_.FullName }
$extraPaths += $wingetPhp
foreach ($dir in $extraPaths) {
    if ($dir -and (Test-Path -LiteralPath $dir) -and ($env:Path -notlike "*$dir*")) {
        $env:Path = "$dir;$env:Path"
    }
}

$phpCmd = Get-Command php -ErrorAction SilentlyContinue
if ($phpCmd) {
    $env:Path = "$(Split-Path -Parent $phpCmd.Source);$env:Path"
}

Set-Location -LiteralPath $WorkingDirectory
Write-Host ("=== $Title ===") -ForegroundColor Cyan

$envFile = Join-Path $WorkingDirectory ".env"
$cmdLine = $CommandArgs -join " "
if ((Test-Path $envFile) -and ($cmdLine -match "artisan serve")) {
    $isWindows = ($null -ne $IsWindows -and $IsWindows) -or ($env:OS -match "Windows")
    if ($isWindows) {
        Write-Host "Note: php artisan serve is single-threaded on Windows. Keep AI POST interval >= 5s." -ForegroundColor DarkYellow
    } else {
        foreach ($line in Get-Content $envFile) {
            if ($line -match '^\s*PHP_CLI_SERVER_WORKERS\s*=\s*(\d+)') {
                $env:PHP_CLI_SERVER_WORKERS = $Matches[1]
                Write-Host "PHP_CLI_SERVER_WORKERS=$($Matches[1])" -ForegroundColor DarkGray
                break
            }
        }
    }
}

if (-not $CommandArgs -or $CommandArgs.Count -eq 0) {
    Write-Error "No command provided."
    exit 1
}

$exe = $CommandArgs[0]
$params = @()
if ($CommandArgs.Count -gt 1) {
    $params = $CommandArgs[1..($CommandArgs.Count - 1)]
}

if ($exe -eq "npm" -and (Test-Path -LiteralPath "$env:ProgramFiles\nodejs\npm.cmd")) {
    $exe = "$env:ProgramFiles\nodejs\npm.cmd"
}
if ($exe -eq "php") {
    $php = Get-Command php -ErrorAction SilentlyContinue
    if ($php) { $exe = $php.Source }
    else {
        Write-Host "php not found on PATH. Install PHP 8.2+ or add it to PATH." -ForegroundColor Red
        exit 1
    }
}

Write-Host ("> " + ($CommandArgs -join " ")) -ForegroundColor DarkGray
& $exe @params
if ($null -ne $LASTEXITCODE -and $LASTEXITCODE -ne 0) {
    Write-Host ("Command exited with code " + $LASTEXITCODE) -ForegroundColor Red
}
