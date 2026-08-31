#Requires -Version 5.1
<#
.SYNOPSIS
  Download pretrained YOLO license-plate detector for AI parking OCR.

.PARAMETER Variant
  Plate model variant: v1n (fast), v1s (default), v1m (accurate)

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\setup-plate-model.ps1
  powershell -ExecutionPolicy Bypass -File .\scripts\setup-plate-model.ps1 -Variant v1m
#>
param(
    [ValidateSet("v1n", "v1s", "v1m")]
    [string]$Variant = "v1s",
    [switch]$Force
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
        if ($key -eq "AI_PARKING_PLATE_MODEL_VARIANT" -and $value) {
            $Variant = $value.Trim().ToLower()
        }
    }
}

Set-Location $AiDir
Write-Host "Downloading plate YOLO model ($Variant)..." -ForegroundColor Cyan

$args = @("download_plate_model.py", "--variant", $Variant)
if ($Force) { $args += "--force" }
python @args

Write-Host ""
Write-Host "Supported plate models:" -ForegroundColor DarkGray
python download_plate_model.py --list

Write-Host ""
Write-Host "Plate YOLO ready." -ForegroundColor Green
Write-Host "  Model: hardware\ai_parking\models\plate.pt"
Write-Host "  Optional .env:"
Write-Host "    AI_PARKING_PLATE_MODEL=models/plate.pt"
Write-Host "    AI_PARKING_PLATE_YOLO_CONF=0.22"
Write-Host ""
Write-Host "Restart AI parking to load the plate detector:" -ForegroundColor Green
Write-Host "  powershell -ExecutionPolicy Bypass -File .\scripts\start-ai-parking.ps1"
