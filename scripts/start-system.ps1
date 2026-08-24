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
    [switch]$WithGitSync,
    [switch]$SkipNgrok
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

function Get-DotEnvValue {
    param(
        [object[]]$Lines,
        [string]$Name
    )
    if (-not $Lines) { return "" }
    $pattern = '^\s*' + [regex]::Escape($Name) + '\s*='
    $line = @($Lines | Where-Object { $_ -match $pattern } | Select-Object -First 1)
    if ($line.Count -eq 0 -or [string]::IsNullOrWhiteSpace([string]$line[0])) {
        return ""
    }
    $value = ([string]$line[0] -replace ("^\s*" + [regex]::Escape($Name) + "\s*="), "")
    return $value.Trim().Trim('"').Trim("'")
}

function Find-NgrokCommand {
    $cmd = Get-Command ngrok -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }

    $candidates = @(
        "$env:LOCALAPPDATA\Microsoft\WinGet\Links\ngrok.exe",
        "$env:ProgramFiles\ngrok\ngrok.exe",
        "${env:ProgramFiles(x86)}\ngrok\ngrok.exe",
        "$env:USERPROFILE\scoop\apps\ngrok\current\ngrok.exe",
        "$env:USERPROFILE\scoop\shims\ngrok.exe"
    )
    $wingetNgrok = Get-ChildItem -Path "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Filter "Ngrok*" -Directory -ErrorAction SilentlyContinue |
        ForEach-Object { Get-ChildItem -Path $_.FullName -Recurse -Filter "ngrok.exe" -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty FullName }
    if ($wingetNgrok) { $candidates += $wingetNgrok }

    foreach ($path in $candidates) {
        if ($path -and (Test-Path -LiteralPath $path)) { return $path }
    }

    return $null
}

function Get-NgrokPublicUrl {
    try {
        $response = Invoke-RestMethod -Uri "http://127.0.0.1:4040/api/tunnels" -TimeoutSec 4
        $tunnel = @($response.tunnels | Where-Object { $_.proto -eq "https" } | Select-Object -First 1)
        if ($tunnel.Count -gt 0 -and $tunnel[0].public_url) {
            return [string]$tunnel[0].public_url
        }
    } catch {
        return $null
    }
    return $null
}

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
$mongoMode = (Get-DotEnvValue -Lines $envLines -Name "MONGODB_MODE").ToLower()
$atlasUser = Get-DotEnvValue -Lines $envLines -Name "MONGODB_ATLAS_USER"
$atlasHost = Get-DotEnvValue -Lines $envLines -Name "MONGODB_ATLAS_HOST"
$mongoLine = @($envLines | Where-Object { $_ -match '^\s*MONGODB_URI=' -and $_ -notmatch '^\s*#' } | Select-Object -First 1)
$mongoLine = if ($mongoLine.Count -gt 0) { [string]$mongoLine[0] } else { "" }
$hasAtlasParts = ($atlasUser -and $atlasHost)
$hasDirectUri = ($mongoLine -and $mongoLine -notmatch 'USERNAME:PASSWORD@' -and $mongoLine -match '=\s*\S')
$hasLocalUri = ($mongoLine -and $mongoLine -match '127\.0\.0\.1:27017')
if ($mongoMode -eq 'atlas' -and -not $hasAtlasParts -and -not $hasDirectUri) {
    $preflight += "MongoDB Atlas (run: .\scripts\setup-atlas-mongo.ps1 or fill MONGODB_ATLAS_* in .env)"
} elseif ($mongoMode -ne 'atlas' -and -not $hasDirectUri -and -not $hasLocalUri) {
    $preflight += "MONGODB_URI (set mongodb://127.0.0.1:27017 or run .\scripts\setup-atlas-mongo.ps1)"
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

Set-Location $Root
Write-Host "Checking MongoDB (capstone database)..." -ForegroundColor Cyan
# PHP may write harmless warnings to stderr; don't let Stop mode treat them as fatal.
$prevEap = $ErrorActionPreference
$ErrorActionPreference = "Continue"
try {
    php artisan config:clear 2>$null | Out-Null
    php scripts/mongo_ping.php 2>$null | Out-Null
    $mongoOk = ($LASTEXITCODE -eq 0)
} finally {
    $ErrorActionPreference = $prevEap
}
if (-not $mongoOk) {
    Write-Host ""
    Write-Host "MongoDB is not reachable." -ForegroundColor Red
    Write-Host "  Local: start the MongoDB Windows service, then in .env set:" -ForegroundColor Yellow
    Write-Host "    MONGODB_MODE=local" -ForegroundColor Yellow
    Write-Host "    MONGODB_URI=mongodb://127.0.0.1:27017" -ForegroundColor Yellow
    Write-Host "  Cloud: powershell -ExecutionPolicy Bypass -File .\scripts\setup-atlas-mongo.ps1" -ForegroundColor Yellow
    Write-Host "  Test:  php scripts/mongo_ping.php" -ForegroundColor Yellow
    exit 1
}
Write-Host "  MongoDB: connected" -ForegroundColor Green

$campusIdPython = Join-Path $Root ".venv-campus-id-ocr\Scripts\python.exe"
if (-not (Test-Path -LiteralPath $campusIdPython)) {
    Write-Host "Setting up campus ID OCR virtualenv (first run only)..." -ForegroundColor Yellow
    $ocrSetup = Join-Path $PSScriptRoot "setup-campus-id-ocr.ps1"
    if (Test-Path -LiteralPath $ocrSetup) {
        & powershell -NoProfile -ExecutionPolicy Bypass -File $ocrSetup
    } else {
        Write-Host "  Warning: campus ID auto-scan setup script missing." -ForegroundColor DarkYellow
    }
}

Write-Host "Starting Smart Campus VMS from $Root" -ForegroundColor Green

$arduinoSync = Join-Path $PSScriptRoot "sync-arduino-sketches.ps1"
if (Test-Path $arduinoSync) {
    & powershell -NoProfile -ExecutionPolicy Bypass -File $arduinoSync -Quiet 2>$null
    Write-Host "  Arduino Entry/Exit sketches synced to OneDrive" -ForegroundColor DarkGray
}

Start-ProjectWindow "Laravel" @("php", "artisan", "serve", "--host=0.0.0.0", "--port=8000")
Start-Sleep -Milliseconds 800

$ngrokPublicUrl = $null
if (-not $SkipNgrok) {
    $ngrokExe = Find-NgrokCommand
    if ($ngrokExe) {
        Start-ProjectWindow "ngrok" @($ngrokExe, "http", "8000")
        Start-Sleep -Seconds 2
        for ($i = 0; $i -lt 8; $i++) {
            $ngrokPublicUrl = Get-NgrokPublicUrl
            if ($ngrokPublicUrl) { break }
            Start-Sleep -Milliseconds 750
        }
        if ($ngrokPublicUrl) {
            Write-Host ("  ngrok tunnel: " + $ngrokPublicUrl) -ForegroundColor Green
        } else {
            Write-Host "  ngrok: started (open http://127.0.0.1:4040 for the public URL)" -ForegroundColor DarkYellow
        }
    } else {
        Write-Host "  ngrok: not found (install from https://ngrok.com or winget install ngrok)" -ForegroundColor DarkYellow
        Write-Host "         Google Form webhook needs ngrok when running locally." -ForegroundColor DarkYellow
    }
}

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
Write-Host "========================================" -ForegroundColor Green
Write-Host " Smart Campus VMS is starting" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "  Website:  http://127.0.0.1:8000" -ForegroundColor Yellow
if ($ngrokPublicUrl) {
    Write-Host ("  Public:   " + $ngrokPublicUrl) -ForegroundColor Yellow
    Write-Host ("  Webhook:  " + $ngrokPublicUrl + "/api/visitor/pre-register/google") -ForegroundColor Yellow
    $googleFormUrl = Get-DotEnvValue -Lines $envLines -Name "VISITOR_PRE_REGISTER_GOOGLE_FORM_URL"
    if ($googleFormUrl) {
        Write-Host "  Google Form webhook: set Apps Script WEBHOOK_URL to the line above." -ForegroundColor DarkGray
        Write-Host "  Optional: set APP_URL to the Public URL, then php artisan config:clear" -ForegroundColor DarkGray
    }
}
Write-Host "  ESP32 firewall: double-click allow-laravel-firewall.bat once if gate shows connection refused" -ForegroundColor DarkGray
$esp32Script = Join-Path $PSScriptRoot "esp32-api-url.ps1"
if (Test-Path $esp32Script) {
    & powershell -NoProfile -ExecutionPolicy Bypass -File $esp32Script 2>$null
}
Write-Host "  Database: MongoDB (capstone)" -ForegroundColor Yellow
if ($startAi) { Write-Host "  AI feed:  http://127.0.0.1:8090/stream.mjpg" -ForegroundColor Yellow }
Write-Host ""
Write-Host "  Admin:  admin@my.cspc.edu.ph / admin123" -ForegroundColor Cyan
Write-Host "  Guard:  guard@my.cspc.edu.ph / password123" -ForegroundColor Cyan
Write-Host ""
Write-Host "Keep the PowerShell windows open while using the site." -ForegroundColor DarkGray
Write-Host "One command next time:  .\start.ps1" -ForegroundColor DarkGray
Write-Host "  -SkipAi    skip AI cameras" -ForegroundColor DarkGray
Write-Host "  -SkipVite  skip npm dev (use if npm run build already done)" -ForegroundColor DarkGray
Write-Host "  -SkipNgrok skip ngrok tunnel (Google Form webhook)" -ForegroundColor DarkGray
