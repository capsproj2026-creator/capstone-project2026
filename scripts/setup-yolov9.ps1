#Requires -Version 5.1
<#
.SYNOPSIS
  Install Python deps and download YOLOv9c weights for the AI parking CCTV service.
#>
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$AiDir = Join-Path $Root "hardware\ai_parking"

Set-Location $AiDir
Write-Host "Installing Python requirements..." -ForegroundColor Cyan
python -m pip install -r requirements.txt

Write-Host "Downloading YOLOv9c model..." -ForegroundColor Cyan
python download_model.py

Write-Host ""
Write-Host "Next steps:" -ForegroundColor Green
Write-Host "  1. Copy .env.example to .env and set AI_PARKING_API_TOKEN + AI_CAMERA_IP"
Write-Host "  2. php artisan db:seed --class=AiTestLotSeeder"
Write-Host "  3. powershell -ExecutionPolicy Bypass -File .\scripts\start-ai-parking.ps1"
