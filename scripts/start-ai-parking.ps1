#Requires -Version 5.1
<#
.SYNOPSIS
  Start the YOLOv9 AI parking service using variables from the Laravel .env file.
#>
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$EnvFile = Join-Path $Root ".env"
$AiDir = Join-Path $Root "hardware\ai_parking"

if (-not (Test-Path $EnvFile)) {
    Write-Error "Missing .env at $EnvFile - copy .env.example and configure AI_PARKING_* values first."
}

function Import-DotEnv([string]$Path) {
    # .env is the source of truth — always apply (do not keep stale empty USER/PASS from the parent shell).
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

function Stop-PortListeners([int]$Port) {
    $lines = netstat -ano | Select-String ":$Port\s+.*LISTENING"
    $procIds = @()
    foreach ($line in $lines) {
        $procId = ($line.ToString() -split '\s+')[-1]
        if ($procId -match '^\d+$') { $procIds += [int]$procId }
    }
    $procIds = $procIds | Select-Object -Unique
    foreach ($procId in $procIds) {
        Write-Host ("Stopping previous process on port {0} (PID {1})" -f $Port, $procId) -ForegroundColor Yellow
        Stop-Process -Id $procId -Force -ErrorAction SilentlyContinue
    }
    if ($procIds.Count -gt 0) { Start-Sleep -Seconds 2 }
}

Import-DotEnv $EnvFile

if (-not $env:AI_LARAVEL_API_BASE -and $env:APP_URL) {
    $env:AI_LARAVEL_API_BASE = $env:APP_URL
}

if (-not $env:AI_PARKING_API_TOKEN) {
    Write-Warning "AI_PARKING_API_TOKEN is empty - Laravel will reject occupancy posts."
}

if (-not $env:AI_CAMERA_2_USER -or -not $env:AI_CAMERA_2_PASS) {
    if ($env:AI_CAMERA_2_IP) {
        Write-Warning "AI_CAMERA_2_IP is set but USER/PASS are empty - Tapo RTSP will fail (401)."
    }
}

$model = Join-Path $AiDir "models\yolov9c.pt"
if (-not (Test-Path $model)) {
    Write-Host "Model not found - running download_model.py..." -ForegroundColor Yellow
    Set-Location $AiDir
    python download_model.py
}

$streamPort = if ($env:AI_STREAM_PORT) { [int]$env:AI_STREAM_PORT } else { 8090 }
Stop-PortListeners -Port $streamPort

Write-Host "Starting YOLOv9 AI parking service..." -ForegroundColor Cyan
Write-Host ('  Laravel API: ' + $env:AI_LARAVEL_API_BASE)
Write-Host ('  MJPEG stream: http://127.0.0.1:' + $streamPort + '/stream.mjpg')
Write-Host ('  CAM-AI-1: ' + $env:AI_CAMERA_1_IP)
Write-Host ('  CAM-AI-2: ' + $env:AI_CAMERA_2_IP + ' user=' + $env:AI_CAMERA_2_USER)
Write-Host ""

Set-Location $AiDir
python -u ai_parking_service.py
