#Requires -Version 5.1
<#
.SYNOPSIS
  Install tzapu WiFiManager into the Arduino IDE libraries folder (required for Gate-Setup portal).

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\install-esp32-wifimanager.ps1
#>
param(
    [string]$SketchbookLibraries = ""
)

$ErrorActionPreference = "Stop"

if (-not $SketchbookLibraries) {
    $candidates = @(
        (Join-Path $env:USERPROFILE "OneDrive\Documents\Arduino\libraries"),
        (Join-Path $env:USERPROFILE "Documents\Arduino\libraries")
    )
    foreach ($c in $candidates) {
        $parent = Split-Path $c -Parent
        if (Test-Path $parent) {
            $SketchbookLibraries = $c
            break
        }
    }
}

if (-not $SketchbookLibraries) {
    $SketchbookLibraries = Join-Path $env:USERPROFILE "OneDrive\Documents\Arduino\libraries"
}

New-Item -ItemType Directory -Force -Path $SketchbookLibraries | Out-Null
$wm = Join-Path $SketchbookLibraries "WiFiManager"
$header = Join-Path $wm "WiFiManager.h"

Write-Host ""
Write-Host "Arduino libraries: $SketchbookLibraries" -ForegroundColor Cyan

if ((Test-Path $header) -and (Test-Path (Join-Path $wm "WiFiManager.cpp"))) {
    $ver = ""
    $props = Join-Path $wm "library.properties"
    if (Test-Path $props) {
        $line = Select-String -Path $props -Pattern '^version=' | Select-Object -First 1
        if ($line) { $ver = $line.Line -replace '^version=', '' }
    }
    if ($ver) {
        Write-Host "WiFiManager already installed (v$ver)." -ForegroundColor Green
    } else {
        Write-Host "WiFiManager already installed." -ForegroundColor Green
    }
} else {
    if (Test-Path $wm) {
        Remove-Item -Recurse -Force $wm
    }
    Write-Host "Downloading WiFiManager (tzapu)..." -ForegroundColor Yellow
    git clone --depth 1 https://github.com/tzapu/WiFiManager.git $wm
    if (-not (Test-Path $header)) {
        throw "Install failed - WiFiManager.h missing at $header"
    }
    Write-Host "WiFiManager installed." -ForegroundColor Green
}

Write-Host ""
Write-Host "Next:" -ForegroundColor Cyan
Write-Host "  1. Close and reopen Arduino IDE (so it rescans libraries)" -ForegroundColor White
Write-Host "  2. Run .\sync-arduino.bat" -ForegroundColor White
Write-Host "  3. Upload Entry.ino - Serial should say: Gate Wi-Fi / API portal (WiFiManager)" -ForegroundColor White
Write-Host "  4. Phone join AP Gate-Setup / password capstone123  (or http://192.168.4.1)" -ForegroundColor White
Write-Host "  5. Enter 2.4 GHz Wi-Fi + Laravel PC IP (ipconfig) + token" -ForegroundColor White
Write-Host ""
