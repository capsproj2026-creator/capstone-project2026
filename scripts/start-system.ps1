#Requires -Version 5.1
<#
.SYNOPSIS
  Start Smart Campus VMS demo stack: Laravel (LAN), Reverb, Vite, and YOLOv9 AI parking.

.DESCRIPTION
  Opens PowerShell windows for each service so you can leave this script after launch.
  YOLOv9 AI parking starts by default. Use -SkipAi to leave it out.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\start-system.ps1

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\start-system.ps1 -SkipAi

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\start-system.ps1 -SkipVite
#>
param(
    [switch]$WithAi,
    [switch]$SkipAi,
    [switch]$SkipVite,
    [switch]$WithGitSync
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$startAi = -not $SkipAi

if (-not (Test-Path (Join-Path $Root ".env"))) {
    Write-Error "Missing .env - copy .env.example and configure values first."
}

function Initialize-DevPath {
    $extra = @(
        "$env:ProgramFiles\nodejs",
        "${env:ProgramFiles(x86)}\nodejs",
        "$env:LOCALAPPDATA\Programs\nodejs",
        "$env:APPDATA\npm",
        "$env:ProgramFiles\PHP",
        "${env:ProgramFiles(x86)}\PHP"
    )
    # WinGet PHP installs under LocalAppData\Microsoft\WinGet\Packages\PHP.*
    $wingetPhp = Get-ChildItem -Path "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Filter "PHP.*" -Directory -ErrorAction SilentlyContinue |
        ForEach-Object { $_.FullName }
    $extra += $wingetPhp
    foreach ($dir in $extra) {
        if ($dir -and (Test-Path -LiteralPath $dir) -and ($env:Path -notlike "*$dir*")) {
            $env:Path = "$dir;$env:Path"
        }
    }
}

function Quote-Arg([string]$Value) {
    if ($null -eq $Value) { return '""' }
    if ($Value -match '[\s"]') {
        return '"' + ($Value -replace '"', '\"') + '"'
    }
    return $Value
}

function Start-ProjectWindow([string]$Title, [string[]]$CommandArgs) {
    Initialize-DevPath
    $launcher = Join-Path $PSScriptRoot "launch-window.ps1"
    # Quote every arg — paths like "...main (3)..." break Start-Process otherwise.
    $allArgs = @(
        "-NoExit",
        "-NoProfile",
        "-ExecutionPolicy", "Bypass",
        "-File", $launcher,
        "-Title", $Title,
        "-WorkingDirectory", $Root
    ) + $CommandArgs
    $argLine = ($allArgs | ForEach-Object { Quote-Arg $_ }) -join " "
    Start-Process powershell -WorkingDirectory $Root -ArgumentList $argLine | Out-Null
}

Initialize-DevPath

# Preflight checks so Windows users get clear errors instead of empty service windows.
$preflight = @()
if (-not (Get-Command php -ErrorAction SilentlyContinue)) { $preflight += "php (PHP 8.2+)" }
if (-not (Test-Path (Join-Path $Root "vendor\autoload.php"))) { $preflight += "vendor (run: composer install)" }
if (-not $SkipVite) {
    if (-not (Get-Command npm -ErrorAction SilentlyContinue)) { $preflight += "npm (Node.js)" }
    if (-not (Test-Path (Join-Path $Root "node_modules"))) { $preflight += "node_modules (run: npm install)" }
}
$envLines = Get-Content (Join-Path $Root ".env") -ErrorAction SilentlyContinue
$appKeyLine = $envLines | Where-Object { $_ -match '^\s*APP_KEY=' } | Select-Object -First 1
if (-not $appKeyLine -or $appKeyLine -match '^\s*APP_KEY=\s*$') {
    $preflight += "APP_KEY (run: php artisan key:generate)"
}
$mongoLine = $envLines | Where-Object { $_ -match '^\s*MONGODB_URI=' } | Select-Object -First 1
if (-not $mongoLine -or $mongoLine -match 'USERNAME:PASSWORD@') {
    $preflight += "MONGODB_URI (set local mongodb://127.0.0.1:27017 or a real Atlas URI)"
}
if ($preflight.Count -gt 0) {
    Write-Host "Cannot start - fix these first:" -ForegroundColor Red
    foreach ($issue in $preflight) { Write-Host ("  - " + $issue) -ForegroundColor Yellow }
    Write-Host ""
    Write-Host "First-time setup:" -ForegroundColor Cyan
    Write-Host "  composer install"
    Write-Host "  npm install"
    Write-Host "  copy .env.example .env"
    Write-Host "  php artisan key:generate"
    Write-Host "  php artisan storage:link"
    Write-Host "  php artisan db:seed"
    Write-Host "  npm run build"
    exit 1
}

Write-Host "Starting Smart Campus VMS from $Root" -ForegroundColor Green

Start-ProjectWindow "Laravel" @("php", "artisan", "serve", "--host=0.0.0.0", "--port=8000")
Start-Sleep -Milliseconds 800
Start-ProjectWindow "Reverb" @("php", "artisan", "reverb:start")
Start-Sleep -Milliseconds 400

if (-not $SkipVite) {
    Start-ProjectWindow "Vite" @("npm", "run", "dev")
}

if ($startAi) {
    Start-Sleep -Milliseconds 400
    $aiScript = Join-Path $PSScriptRoot "start-ai-parking.ps1"
    Start-ProjectWindow "YOLOv9 AI Parking" @(
        "powershell",
        "-ExecutionPolicy", "Bypass",
        "-File", $aiScript
    )
}

if ($WithGitSync) {
    Start-Sleep -Milliseconds 400
    $gitSync = Join-Path $PSScriptRoot "auto-sync-github.ps1"
    Start-ProjectWindow "GitHub Auto Sync" @(
        "powershell",
        "-ExecutionPolicy", "Bypass",
        "-File", $gitSync
    )
}

Write-Host ""
Write-Host "Opened:" -ForegroundColor Green
Write-Host "  1) php artisan serve --host=0.0.0.0 --port=8000"
Write-Host "  2) php artisan reverb:start"
if (-not $SkipVite) { Write-Host "  3) npm run dev" }
if ($startAi) { Write-Host "  4) YOLOv9 AI parking (scripts\start-ai-parking.ps1)" }
if ($WithGitSync) { Write-Host "  5) GitHub auto-sync (pull + push)" }
Write-Host ""
Write-Host "App:  http://127.0.0.1:8000" -ForegroundColor Yellow
Write-Host "Tips: -SkipAi to skip cameras | -SkipVite if you already ran npm run build | -WithGitSync for auto pull/push" -ForegroundColor DarkGray
if ($WithAi -and $SkipAi) {
    Write-Host "Note: -SkipAi overrides -WithAi." -ForegroundColor DarkYellow
}
