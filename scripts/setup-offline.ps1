# Prepare local MongoDB as the offline fallback for dual online/offline mode.
# Keeps Atlas URI intact. Arduino RFID + CCTV stay on LAN / localhost.
param(
    [switch]$SkipInstall,
    [switch]$SkipSeed,
    [switch]$ForceOfflineOnly
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
if (-not (Test-Path (Join-Path $Root ".env"))) {
    throw ".env not found at $Root"
}
Set-Location $Root

Write-Host "=== Dual online/offline Mongo setup ===" -ForegroundColor Cyan
Write-Host "Root: $Root"

function Test-MongoPort {
    try {
        return [bool](Test-NetConnection -ComputerName 127.0.0.1 -Port 27017 -WarningAction SilentlyContinue -InformationLevel Quiet)
    } catch {
        return $false
    }
}

function Set-EnvKeys([hashtable]$map) {
    $envPath = Join-Path $Root ".env"
    $lines = Get-Content $envPath
    $seen = @{}
    $out = foreach ($line in $lines) {
        $matched = $false
        foreach ($key in $map.Keys) {
            if ($line -match ("^\s*" + [regex]::Escape($key) + "\s*=")) {
                $seen[$key] = $true
                $matched = $true
                Write-Output ($key + "=" + $map[$key])
                break
            }
        }
        if (-not $matched) { Write-Output $line }
    }
    foreach ($key in $map.Keys) {
        if (-not $seen.ContainsKey($key)) {
            $out = @(($key + "=" + $map[$key])) + $out
        }
    }
    Set-Content -Path $envPath -Value $out
}

if ($ForceOfflineOnly) {
    Set-EnvKeys @{
        "APP_OFFLINE" = "true"
        "APP_OFFLINE_AUTO_VERIFY_EMAIL" = "true"
        "MONGODB_MODE" = "local"
        "MONGODB_LOCAL_URI" = "mongodb://127.0.0.1:27017"
        "MAIL_MAILER" = "log"
    }
    Write-Host "  .env: forced offline-only (local Mongo)" -ForegroundColor Yellow
} else {
    Set-EnvKeys @{
        "APP_OFFLINE" = "auto"
        "APP_OFFLINE_AUTO_VERIFY_EMAIL" = "true"
        "MONGODB_MODE" = "auto"
        "MONGODB_AUTO_PREFER" = "atlas"
        "MONGODB_LOCAL_URI" = "mongodb://127.0.0.1:27017"
        "MAIL_MAILER" = "smtp"
    }
    Write-Host "  .env: dual mode (Atlas online + local offline fallback)" -ForegroundColor Green
}

if (-not $SkipInstall -and -not (Test-MongoPort)) {
    Write-Host "MongoDB not on 127.0.0.1:27017 - installing via winget..." -ForegroundColor Yellow
    if (Get-Command winget -ErrorAction SilentlyContinue) {
        winget install -e --id MongoDB.Server --accept-package-agreements --accept-source-agreements
    } else {
        Write-Host "  winget missing. Install MongoDB Community manually." -ForegroundColor Yellow
    }
}

$svc = Get-Service -Name "MongoDB" -ErrorAction SilentlyContinue
if (-not $svc) {
    $svc = Get-Service | Where-Object { $_.Name -match "mongo" -or $_.DisplayName -match "MongoDB" } | Select-Object -First 1
}
if ($svc -and $svc.Status -ne "Running") {
    Write-Host ("Starting service " + $svc.Name + "...") -ForegroundColor Cyan
    try { Start-Service -Name $svc.Name } catch {
        Write-Host ("  Could not start service (try Admin): " + $_.Exception.Message) -ForegroundColor Yellow
    }
} elseif ($svc) {
    Write-Host ("  MongoDB service running (" + $svc.Name + ")") -ForegroundColor Green
}

$deadline = (Get-Date).AddSeconds(60)
while (-not (Test-MongoPort) -and (Get-Date) -lt $deadline) {
    Start-Sleep -Seconds 2
}

if (-not (Test-MongoPort)) {
    Write-Host "Local MongoDB still not on 127.0.0.1:27017." -ForegroundColor Red
    Write-Host "Online Atlas can still work. For offline demos, install/start MongoDB then re-run." -ForegroundColor Yellow
    exit 1
}

Write-Host "  Local MongoDB: 127.0.0.1:27017 OK" -ForegroundColor Green

& php artisan config:clear | Out-Null

# Temporarily force local for seed so offline DB gets roles/admin even when Atlas is up.
$env:MONGODB_MODE = "local"
$env:MONGODB_URI = "mongodb://127.0.0.1:27017"
& php scripts/mongo_ping.php
if ($LASTEXITCODE -ne 0) {
    Write-Host "local mongo_ping failed" -ForegroundColor Red
    exit 1
}

if (-not $SkipSeed) {
    Write-Host "Seeding local capstone database..." -ForegroundColor Cyan
    & php artisan db:seed --force
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Seed failed - check errors above." -ForegroundColor Red
        exit 1
    }
    Write-Host "  Seed: done" -ForegroundColor Green
}

Remove-Item Env:MONGODB_MODE -ErrorAction SilentlyContinue
Remove-Item Env:MONGODB_URI -ErrorAction SilentlyContinue
& php artisan config:clear | Out-Null

Write-Host ""
Write-Host "Dual mode ready." -ForegroundColor Green
Write-Host "  Online:  uses Atlas when internet works (email verify + Google)" -ForegroundColor Yellow
Write-Host "  Offline: falls back to local Mongo (registration auto-verifies)" -ForegroundColor Yellow
Write-Host "  Arduino/CCTV: stay on LAN / localhost" -ForegroundColor Yellow
Write-Host "Start: .\scripts\start-system.ps1" -ForegroundColor Cyan
