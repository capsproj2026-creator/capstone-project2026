#Requires -Version 5.1
<#
.SYNOPSIS
  One-shot: sync Entry/Exit sketches, detect PC IP, fix rfid_gate_config.h, verify Laravel.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\setup-esp32-gate.ps1

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\setup-esp32-gate.ps1 -Hotspot
#>
param(
    [switch]$Hotspot,
    [switch]$Mercusys
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$Scripts = $PSScriptRoot

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host " ESP32 Gate - full connect setup" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# --- Detect networks ---
$ips = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
    Where-Object { $_.IPAddress -notlike "127.*" -and $_.IPAddress -notlike "169.254.*" -and $_.IPAddress -notlike "192.168.230.*" }

$hotspotIp = ($ips | Where-Object { $_.IPAddress -like "192.168.43.*" } | Select-Object -First 1).IPAddress
$mercusysIp = ($ips | Where-Object { $_.IPAddress -like "192.168.1.*" } | Select-Object -First 1).IPAddress

Write-Host "PC addresses detected:" -ForegroundColor Yellow
Write-Host "  Hotspot (192.168.43.x): $(if ($hotspotIp) { $hotspotIp } else { '(PC not on phone hotspot)' })" -ForegroundColor White
Write-Host "  Mercusys (192.168.1.x): $(if ($mercusysIp) { $mercusysIp } else { '(PC not on Mercusys)' })" -ForegroundColor White
Write-Host ""

$configPath = Join-Path $Root "hardware\esp32_rfid_gate\rfid_gate_config.h"
$config = Get-Content $configPath -Raw

if ($Mercusys -and $mercusysIp) {
    $useIp = $mercusysIp
    $ssid = "MERCUSYS_08BA"
    $pass = "70886207"
    Write-Host "Mode: Mercusys router (requires AP isolation OFF on router)" -ForegroundColor Cyan
} elseif ($Hotspot -or $hotspotIp) {
    if (-not $hotspotIp) {
        Write-Host "ERROR: PC is not connected to phone hotspot." -ForegroundColor Red
        Write-Host "Connect PC to hotspot 'Michael' first, then run this script again." -ForegroundColor Yellow
        exit 1
    }
    $useIp = $hotspotIp
    $ssid = "Michael"
    $pass = "toldanes"
    Write-Host "Mode: Phone hotspot (recommended for demo)" -ForegroundColor Cyan
} elseif ($mercusysIp) {
    $useIp = $mercusysIp
    $ssid = "MERCUSYS_08BA"
    $pass = "70886207"
    Write-Host "Mode: Mercusys (PC on router Wi-Fi)" -ForegroundColor Cyan
    Write-Host "WARNING: If ESP32 TCP probe fails, use phone hotspot instead." -ForegroundColor Yellow
} else {
    Write-Host "ERROR: No usable LAN IP. Connect PC to Wi-Fi or hotspot." -ForegroundColor Red
    exit 1
}

# Update config
$config = $config -replace '(#define\s+WIFI_SSID\s+")[^"]+(")', "`${1}$ssid`${2}"
$config = $config -replace '(#define\s+WIFI_PASSWORD\s+")[^"]+(")', "`${1}$pass`${2}"
$config = $config -replace '(#define\s+API_HOST\s+")[^"]+(")', "`${1}$useIp`${2}"
$apiBase = "http://${useIp}:8000"
$config = $config -replace '(#define\s+API_BASE\s+")[^"]+(")', "`${1}$apiBase`${2}"
Set-Content -Path $configPath -Value $config -NoNewline

Write-Host ""
Write-Host "Updated rfid_gate_config.h:" -ForegroundColor Green
Write-Host "  WIFI_SSID  = $ssid" -ForegroundColor White
Write-Host "  API_HOST   = $useIp" -ForegroundColor White
Write-Host "  API_BASE   = $apiBase" -ForegroundColor White

# Sync to Arduino IDE folders
& powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $Scripts "sync-arduino-sketches.ps1") -Quiet

# Laravel check
$listening = netstat -ano | Select-String "0\.0\.0\.0:8000\s+.*LISTENING"
if (-not $listening) {
    Write-Host ""
    Write-Host "Laravel is NOT running. Start it:" -ForegroundColor Red
    Write-Host "  .\start.ps1" -ForegroundColor Yellow
} else {
    try {
        $r = Invoke-WebRequest -Method POST -Uri "$apiBase/api/rfid/heartbeat" `
            -Headers @{"X-RFID-TOKEN" = "capstone-rfid-dev-token-change-me"; "Content-Type" = "application/json"} `
            -Body '{"gate_id":"GATE-IN-1"}' -UseBasicParsing -TimeoutSec 8
        Write-Host ""
        Write-Host "Laravel heartbeat: HTTP $($r.StatusCode) OK" -ForegroundColor Green
    } catch {
        Write-Host ""
        Write-Host "Laravel running but heartbeat failed: $($_.Exception.Message)" -ForegroundColor Red
        Write-Host "Run allow-laravel-firewall.bat as Admin" -ForegroundColor Yellow
    }
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host " NEXT: Arduino IDE (2.3.10)" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host @"
1. Upload Entry.ino from:
   OneDrive\Documents\Arduino\Entry
   (servo wired to GPIO 14 on THIS board only)

2. Upload Exit.ino from:
   OneDrive\Documents\Arduino\Exit
   (RFID only - no servo)

3. Serial Monitor 115200 - expect:
   WiFi OK IP: 192.168.x.x
   TCP probe $useIp`:8000 = OK
   API online - heartbeats OK

4. Register RFID card in Admin -> RFID (UID e.g. 5AB48FF8)

5. Guard -> Live Gate Monitor (keep Reverb window open from start.ps1)

SERVO wiring (Entry board only):
  Signal -> GPIO 14 | VCC -> 5V supply | GND -> common GND
"@ -ForegroundColor White
