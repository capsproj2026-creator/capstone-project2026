# Calibrate CAM-2 (Prototype 2) parking slots on the LIVE YOLO main RTSP frame.
# Draw EACH bay (P2-1 .. P2-5) along the yellow lines - not one big row.
#
#   powershell -ExecutionPolicy Bypass -File .\scripts\calibrate-cam2.ps1
#   powershell -ExecutionPolicy Bypass -File .\scripts\calibrate-cam2.ps1 -Fresh

param(
    [switch]$Fresh
)

$Root = Split-Path -Parent $PSScriptRoot
$AiDir = Join-Path $Root "hardware\ai_parking"
Set-Location $AiDir

$pyArgs = @(
    "calibrate_zones.py",
    "--zones", "zones_prototype2.json",
    "--live",
    "--camera", "2",
    "--slots", "5"
)
if ($Fresh) { $pyArgs += "--fresh" }

$env:PYTHONUNBUFFERED = "1"
Write-Host "CAM-2 live calibration - click yellow-line corners for each slot (P2-1..P2-5)." -ForegroundColor Cyan
Write-Host "Keys: click=add  U=undo  C=commit  N/P=next/prev  F=refresh  S=save  Q=quit" -ForegroundColor DarkGray
python @pyArgs
