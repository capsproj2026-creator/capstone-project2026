# Calibrate CAM-1 (Prototype) parking slots on the LIVE YOLO main RTSP frame.
# Draw EACH bay (PT-1 .. PT-5) along the yellow lines - not one big row.
#
#   powershell -ExecutionPolicy Bypass -File .\scripts\calibrate-cam1.ps1
#   powershell -ExecutionPolicy Bypass -File .\scripts\calibrate-cam1.ps1 -Fresh

param(
    [switch]$Fresh
)

$Root = Split-Path -Parent $PSScriptRoot
$AiDir = Join-Path $Root "hardware\ai_parking"
Set-Location $AiDir

$pyArgs = @(
    "calibrate_zones.py",
    "--zones", "zones_prototype.json",
    "--live",
    "--camera", "1",
    "--slots", "5"
)
if ($Fresh) { $pyArgs += "--fresh" }

$env:PYTHONUNBUFFERED = "1"
Write-Host "CAM-1 live calibration - click yellow-line corners for each slot (PT-1..PT-5)." -ForegroundColor Cyan
Write-Host "Keys: click=add  U=undo  C=commit  N/P=next/prev  F=refresh  S=save  Q=quit" -ForegroundColor DarkGray
python @pyArgs
