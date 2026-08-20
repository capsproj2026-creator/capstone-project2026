#Requires -Version 5.1
<#
.SYNOPSIS
  Show this PC's Wi-Fi LAN IP for ESP32 API_BASE and optionally patch rfid_gate_config.h.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\esp32-api-url.ps1

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\esp32-api-url.ps1 -UpdateConfig
#>
param(
    [switch]$UpdateConfig
)

$Root = Split-Path -Parent $PSScriptRoot
$ConfigPath = Join-Path $Root "hardware\esp32_rfid_gate\rfid_gate_config.h"

function Get-LanIPv4 {
    $addrs = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object {
            $_.IPAddress -notlike "127.*" -and
            $_.IPAddress -notlike "169.254.*" -and
            $_.IPAddress -notlike "192.168.230.*" -and
            $_.PrefixOrigin -ne "WellKnown"
        } |
        Sort-Object InterfaceMetric

    # Hotspot (192.168.43.x) or home Wi-Fi (192.168.1.x) — skip Hyper-V virtual adapters.
    foreach ($a in $addrs) {
        if ($a.IPAddress -like "192.168.43.*" -or $a.IPAddress -like "192.168.1.*" -or $a.IPAddress -like "192.168.0.*") {
            return $a.IPAddress
        }
    }
    foreach ($a in $addrs) {
        if ($a.IPAddress -like "192.168.*" -or $a.IPAddress -like "10.*" -or $a.IPAddress -like "172.*") {
            return $a.IPAddress
        }
    }
    return ($addrs | Select-Object -First 1).IPAddress
}

$ip = Get-LanIPv4
if (-not $ip) {
    Write-Host "Could not detect LAN IPv4. Run ipconfig and set API_BASE manually." -ForegroundColor Red
    exit 1
}

$apiBase = "http://${ip}:8000"
Write-Host ""
Write-Host "ESP32 config (API_HOST is what firmware uses):" -ForegroundColor Green
Write-Host "  #define API_HOST `"$ip`"" -ForegroundColor Yellow
Write-Host "  #define API_BASE `"$apiBase`"" -ForegroundColor Yellow
Write-Host ""
Write-Host "Put this in hardware\esp32_rfid_gate\rfid_gate_config.h, then re-flash the ESP32." -ForegroundColor DarkGray
Write-Host "Laravel must run with: php artisan serve --host=0.0.0.0 --port=8000" -ForegroundColor DarkGray
Write-Host "(start-system.ps1 / start.ps1 already does this)" -ForegroundColor DarkGray
Write-Host ""

try {
    $r = Invoke-WebRequest -Method POST -Uri "$apiBase/api/rfid/heartbeat" `
        -Headers @{"X-RFID-TOKEN" = "capstone-rfid-dev-token-change-me"; "Content-Type" = "application/json"} `
        -Body '{"gate_id":"GATE-IN-1"}' -TimeoutSec 4 -UseBasicParsing
    Write-Host "Laravel reachable at $apiBase (HTTP $($r.StatusCode))" -ForegroundColor Green
} catch {
    Write-Host "Laravel NOT reachable at $apiBase - start .\start.ps1 and keep the Laravel window open." -ForegroundColor Red
}

if (-not (Test-Path $ConfigPath)) {
    Write-Host "Config not found: $ConfigPath" -ForegroundColor Yellow
    exit 0
}

$config = Get-Content $ConfigPath -Raw
if ($config -match '#define\s+API_HOST\s+"(.+)"') {
    $currentHost = $Matches[1]
    Write-Host "Current API_HOST: $currentHost" -ForegroundColor Cyan
    if ($currentHost -ne $ip) {
        Write-Host "  MISMATCH - ESP32 cannot reach PC (wrong network IP). Run with -UpdateConfig" -ForegroundColor Red
    } else {
        Write-Host "  Matches detected IP." -ForegroundColor Green
    }
}

if ($UpdateConfig) {
    if ($config -notmatch '#define\s+API_HOST\s+".+"') {
        Write-Error "API_HOST line not found in $ConfigPath"
    }
    $updated = $config -replace '(#define\s+API_HOST\s+").+(")', "`${1}$ip`${2}"
    $updated = $updated -replace '(#define\s+API_BASE\s+").+(")', "`${1}$apiBase`${2}"
    Set-Content -Path $ConfigPath -Value $updated -NoNewline
    Write-Host "Updated API_HOST and API_BASE -> $ip" -ForegroundColor Green
    Write-Host "Run sync-arduino.bat then re-flash Entry.ino in Arduino IDE." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "If ESP32 still fails after re-flash:" -ForegroundColor DarkGray
Write-Host "  1. Double-click allow-laravel-firewall.bat in the project root (Admin, once)" -ForegroundColor DarkGray
Write-Host "  2. Or run PowerShell as Admin:" -ForegroundColor DarkGray
Write-Host "     netsh advfirewall firewall add rule name=Laravel8000 dir=in action=allow protocol=TCP localport=8000" -ForegroundColor DarkGray
