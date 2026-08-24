#Requires -Version 5.1
<#
.SYNOPSIS
  Start EVERYTHING: website (Laravel + MongoDB) + Reverb + Vite + YOLOv9 AI parking.

.DESCRIPTION
  One command for the full Smart Campus VMS demo.
  Opens Laravel/Reverb/Vite in separate windows if they are not running yet,
  then runs AI parking in this window.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\start-ai-parking.ps1
#>
param(
    [switch]$SkipWebStack,
    [switch]$SkipNgrok
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$EnvFile = Join-Path $Root ".env"
$AiDir = Join-Path $Root "hardware\ai_parking"
$ScriptsDir = $PSScriptRoot

if (-not (Test-Path $EnvFile)) {
    Write-Error "Missing .env at $EnvFile - copy .env.example and configure values first."
}

function Import-DotEnv([string]$Path) {
    Get-Content $Path | ForEach-Object {
        $line = $_.Trim()
        if (-not $line -or $line.StartsWith("#") -or -not $line.Contains("=")) { return }
        $parts = $line.Split("=", 2)
        $key = $parts[0].Trim()
        $value = $parts[1].Trim().Trim('"').Trim("'")
        if (-not $key) { return }
        [Environment]::SetEnvironmentVariable($key, $value, "Process")
    }
}

function Test-HttpOk([string]$Url) {
    try {
        $r = Invoke-WebRequest -Uri $Url -TimeoutSec 4 -UseBasicParsing
        return ($r.StatusCode -ge 200 -and $r.StatusCode -lt 500)
    } catch {
        return $false
    }
}

function Stop-PortListeners([int]$Port) {
    $lines = netstat -ano | Select-String ":$Port\s+.*LISTENING"
    $procIds = @()
    foreach ($line in $lines) {
        $procId = ($line.ToString() -split '\s+')[-1]
        if ($procId -match '^\d+$') { $procIds += [int]$procId }
    }
    foreach ($procId in ($procIds | Select-Object -Unique)) {
        Write-Host ("Stopping previous AI process on port {0} (PID {1})" -f $Port, $procId) -ForegroundColor Yellow
        Stop-Process -Id $procId -Force -ErrorAction SilentlyContinue
    }
    if ($procIds.Count -gt 0) { Start-Sleep -Seconds 2 }
}

Import-DotEnv $EnvFile

if (-not $env:AI_LARAVEL_API_BASE -and $env:APP_URL) {
    $env:AI_LARAVEL_API_BASE = $env:APP_URL
}

$laravelUrl = if ($env:AI_LARAVEL_API_BASE) { $env:AI_LARAVEL_API_BASE } else { "http://127.0.0.1:8000" }

Write-Host "========================================" -ForegroundColor Green
Write-Host " Smart Campus VMS - start all" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""

Set-Location $Root
Write-Host "Checking MongoDB (capstone)..." -ForegroundColor Cyan
php artisan config:clear | Out-Null
php scripts/mongo_ping.php 2>$null
if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "MongoDB is not connected." -ForegroundColor Red
    Write-Host "  Local .env:" -ForegroundColor Yellow
    Write-Host "    MONGODB_MODE=local" -ForegroundColor Yellow
    Write-Host "    MONGODB_URI=mongodb://127.0.0.1:27017" -ForegroundColor Yellow
    Write-Host "  Atlas:  .\scripts\setup-atlas-mongo.ps1" -ForegroundColor Yellow
    exit 1
}
Write-Host "  MongoDB: OK" -ForegroundColor Green

if (-not $SkipWebStack) {
    if (-not (Test-HttpOk $laravelUrl)) {
        Write-Host ""
        Write-Host "Starting website stack (Laravel + Reverb + Vite)..." -ForegroundColor Cyan
        $sysArgs = @("-SkipAi")
        if ($SkipNgrok) { $sysArgs += "-SkipNgrok" }
        if (Test-Path (Join-Path $Root "public\build\manifest.json")) {
            $sysArgs += "-SkipVite"
        }
        & powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $ScriptsDir "start-system.ps1") @sysArgs
        Write-Host "Waiting for Laravel..." -ForegroundColor DarkGray
        $ready = $false
        for ($i = 0; $i -lt 15; $i++) {
            Start-Sleep -Seconds 1
            if (Test-HttpOk $laravelUrl) { $ready = $true; break }
        }
        if (-not $ready) {
            Write-Host "Laravel did not respond at $laravelUrl - check the Laravel window for errors." -ForegroundColor Red
            exit 1
        }
    }
    Write-Host "  Laravel: OK ($laravelUrl)" -ForegroundColor Green
} else {
    if (-not (Test-HttpOk $laravelUrl)) {
        Write-Host "Laravel is not running at $laravelUrl (use without -SkipWebStack to auto-start)" -ForegroundColor Red
        exit 1
    }
}

Write-Host ""

if (-not $env:AI_PARKING_API_TOKEN) {
    Write-Warning "AI_PARKING_API_TOKEN is empty - Laravel will reject occupancy posts."
}

if (-not $env:AI_CAMERA_2_USER -or -not $env:AI_CAMERA_2_PASS) {
    if ($env:AI_CAMERA_2_IP) {
        Write-Warning "AI_CAMERA_2_IP is set but USER/PASS are empty - Tapo RTSP will fail (401)."
    }
}

$modelName = if ($env:AI_PARKING_YOLO_MODEL) {
    $env:AI_PARKING_YOLO_MODEL.Trim().ToLower() -replace '\.pt$',''
} else {
    "yolov9c"
}
$model = Join-Path $AiDir "models\$modelName.pt"
if (-not (Test-Path $model)) {
    Write-Host ("Downloading YOLO model ({0})..." -f $modelName) -ForegroundColor Yellow
    Set-Location $AiDir
    python download_model.py --model $modelName
}

$streamPort = if ($env:AI_STREAM_PORT) { [int]$env:AI_STREAM_PORT } else { 8090 }
Stop-PortListeners -Port $streamPort

Write-Host "Starting YOLOv9 AI parking (this window)..." -ForegroundColor Cyan
Write-Host ("  Website:  " + $laravelUrl) -ForegroundColor Yellow
Write-Host ("  Database: MongoDB capstone") -ForegroundColor Yellow
Write-Host ("  AI stream: http://127.0.0.1:" + $streamPort + "/stream.mjpg") -ForegroundColor Yellow
Write-Host ("  Model:    " + $modelName) -ForegroundColor DarkGray
Write-Host ""
Write-Host "  Admin: admin@my.cspc.edu.ph / admin123" -ForegroundColor Cyan
Write-Host "  Guard: guard@my.cspc.edu.ph / password123" -ForegroundColor Cyan
Write-Host ""
Write-Host "Keep ALL PowerShell windows open while using the site." -ForegroundColor DarkGray
Write-Host ""

Set-Location $AiDir
python -u ai_parking_service.py
