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
    Get-Content $Path | ForEach-Object {
        $line = $_.Trim()
        if (-not $line -or $line.StartsWith("#") -or -not $line.Contains("=")) { return }
        $parts = $line.Split("=", 2)
        $key = $parts[0].Trim()
        $value = $parts[1].Trim().Trim('"').Trim("'")
        if ($key -and -not [string]::IsNullOrEmpty([Environment]::GetEnvironmentVariable($key))) { return }
        [Environment]::SetEnvironmentVariable($key, $value, "Process")
    }
}

Import-DotEnv $EnvFile

if (-not $env:AI_LARAVEL_API_BASE -and $env:APP_URL) {
    $env:AI_LARAVEL_API_BASE = $env:APP_URL
}

if (-not $env:AI_PARKING_API_TOKEN) {
    Write-Warning "AI_PARKING_API_TOKEN is empty - Laravel will reject occupancy posts."
}

$model = Join-Path $AiDir "models\yolov9c.pt"
if (-not (Test-Path $model)) {
    Write-Host "Model not found - running download_model.py..." -ForegroundColor Yellow
    Set-Location $AiDir
    python download_model.py
}

Write-Host "Starting YOLOv9 AI parking service..." -ForegroundColor Cyan
Write-Host ('  Laravel API: ' + $env:AI_LARAVEL_API_BASE)
$streamPort = if ($env:AI_STREAM_PORT) { $env:AI_STREAM_PORT } else { "8090" }
Write-Host ('  MJPEG stream: http://127.0.0.1:' + $streamPort + '/stream.mjpg')
Write-Host ('  Camera IP: ' + $env:AI_CAMERA_IP)
Write-Host ""

Set-Location $AiDir
python -u ai_parking_service.py
