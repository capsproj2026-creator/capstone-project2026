#Requires -Version 5.1
<#
.SYNOPSIS
  Install Python deps and download pretrained YOLOv9 weights for AI parking.

.PARAMETER Model
  YOLOv9 variant: yolov9t, yolov9s, yolov9m, yolov9c (default), yolov9e
#>
param(
    [ValidateSet("yolov9t", "yolov9s", "yolov9m", "yolov9c", "yolov9e")]
    [string]$Model = "yolov9c"
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$AiDir = Join-Path $Root "hardware\ai_parking"
$EnvFile = Join-Path $Root ".env"

if (Test-Path $EnvFile) {
    Get-Content $EnvFile | ForEach-Object {
        $line = $_.Trim()
        if (-not $line -or $line.StartsWith("#") -or -not $line.Contains("=")) { return }
        $parts = $line.Split("=", 2)
        $key = $parts[0].Trim()
        $value = $parts[1].Trim().Trim('"').Trim("'")
        if ($key -eq "AI_PARKING_YOLO_MODEL" -and $value) { $Model = $value.Trim().ToLower() -replace '\.pt$','' }
    }
}

Set-Location $AiDir
Write-Host "Installing Python requirements..." -ForegroundColor Cyan
python -m pip install -q -r requirements.txt

Write-Host "Downloading pretrained YOLOv9 ($Model)..." -ForegroundColor Cyan
python download_model.py --model $Model

Write-Host ""
Write-Host "Supported models:" -ForegroundColor DarkGray
python download_model.py --list

Write-Host ""
Write-Host "YOLOv9 ready." -ForegroundColor Green
Write-Host "  Model: hardware\ai_parking\models\$Model.pt"
Write-Host "  Set AI_PARKING_YOLO_MODEL=$Model in .env (optional if already yolov9c)"
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Green
Write-Host "  1. Configure AI_CAMERA_* and AI_PARKING_API_TOKEN in .env"
Write-Host "  2. php artisan db:seed --class=AiTestLotSeeder"
Write-Host "  3. powershell -ExecutionPolicy Bypass -File .\scripts\start-ai-parking.ps1"
