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
    [switch]$SkipVite
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$startAi = -not $SkipAi

if (-not (Test-Path (Join-Path $Root ".env"))) {
    Write-Error "Missing .env - copy .env.example and configure values first."
}

function Start-ProjectWindow([string]$Title, [string]$Command) {
    $cmd = "Write-Host ('=== ' + '$Title' + ' ===') -ForegroundColor Cyan; $Command"
    Start-Process powershell -WorkingDirectory $Root -ArgumentList @(
        "-NoExit",
        "-Command",
        $cmd
    ) | Out-Null
}

Write-Host "Starting Smart Campus VMS from $Root" -ForegroundColor Green

Start-ProjectWindow "Laravel" "php artisan serve --host=0.0.0.0 --port=8000"
Start-Sleep -Milliseconds 800
Start-ProjectWindow "Reverb" "php artisan reverb:start"
Start-Sleep -Milliseconds 400

if (-not $SkipVite) {
    Start-ProjectWindow "Vite" "npm run dev"
}

if ($startAi) {
    Start-Sleep -Milliseconds 400
    $aiScript = Join-Path $PSScriptRoot "start-ai-parking.ps1"
    Start-ProjectWindow "YOLOv9 AI Parking" "powershell -ExecutionPolicy Bypass -File `"$aiScript`""
}

Write-Host ""
Write-Host "Opened:" -ForegroundColor Green
Write-Host "  1) php artisan serve --host=0.0.0.0 --port=8000"
Write-Host "  2) php artisan reverb:start"
if (-not $SkipVite) { Write-Host "  3) npm run dev" }
if ($startAi) { Write-Host "  4) YOLOv9 AI parking (scripts\start-ai-parking.ps1)" }
Write-Host ""
Write-Host "App:  http://127.0.0.1:8000" -ForegroundColor Yellow
Write-Host "Tips: -SkipAi to skip cameras | -SkipVite if you already ran npm run build" -ForegroundColor DarkGray
if ($WithAi -and $SkipAi) {
    Write-Host "Note: -SkipAi overrides -WithAi." -ForegroundColor DarkYellow
}
