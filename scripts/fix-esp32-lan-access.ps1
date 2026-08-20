#Requires -Version 5.1
<#
.SYNOPSIS
  Diagnose and fix ESP32 -> PC connection (TCP probe FAIL / HTTP -1).

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\fix-esp32-lan-access.ps1
#>
$ErrorActionPreference = "Continue"
$Root = Split-Path -Parent $PSScriptRoot

function Get-WifiLanIPv4 {
    $candidates = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object {
            $_.IPAddress -notlike "127.*" -and
            $_.IPAddress -notlike "169.254.*" -and
            $_.IPAddress -notlike "192.168.230.*"
        }
    $wifi = $candidates | Where-Object { $_.InterfaceAlias -match "Wi-?Fi" } | Select-Object -First 1
    if ($wifi) { return $wifi.IPAddress }
    return ($candidates | Where-Object { $_.IPAddress -like "192.168.*" } | Select-Object -First 1).IPAddress
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host " ESP32 LAN connection fix" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$ip = Get-WifiLanIPv4
$listening = netstat -ano | Select-String "0\.0\.0\.0:8000\s+.*LISTENING"

Write-Host "1. PC Wi-Fi IP:     $(if ($ip) { $ip } else { '(not found)' })" -ForegroundColor $(if ($ip) { "Green" } else { "Red" })
Write-Host "2. Laravel port 8000: $(if ($listening) { 'LISTENING' } else { 'NOT running - run .\start.ps1' })" -ForegroundColor $(if ($listening) { "Green" } else { "Red" })

if ($ip) {
    try {
        $r = Invoke-WebRequest -Method POST -Uri "http://${ip}:8000/api/rfid/heartbeat" `
            -Headers @{"X-RFID-TOKEN" = "capstone-rfid-dev-token-change-me"; "Content-Type" = "application/json"} `
            -Body '{"gate_id":"GATE-IN-1"}' -UseBasicParsing -TimeoutSec 8
        Write-Host "3. PC self-test:    HTTP $($r.StatusCode) OK" -ForegroundColor Green
    } catch {
        Write-Host "3. PC self-test:    FAIL ($($_.Exception.Message))" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "If ESP32 Serial shows:" -ForegroundColor Yellow
Write-Host "  TCP probe 192.168.1.104:8000 = FAIL" -ForegroundColor White
Write-Host "then the ROUTER blocks Wi-Fi devices from reaching your PC." -ForegroundColor Yellow
Write-Host "Windows firewall is usually NOT the cause when TCP probe fails." -ForegroundColor DarkGray
Write-Host ""

Write-Host "========================================" -ForegroundColor Green
Write-Host " FIX A - Mercusys router (try first)" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host @"
1. Phone browser: http://192.168.1.1  (or http://mwlogin.net)
2. Login to Mercusys admin
3. Wireless -> Advanced (or More settings)
4. Turn OFF:
   - AP Isolation
   - Guest Network Isolation
   - Wireless Client Isolation
5. Save and reboot router
6. ESP32 and PC must use the SAME Wi-Fi (not Guest)
7. Phone test (same Wi-Fi): http://${ip}:8000
"@ -ForegroundColor White

Write-Host "========================================" -ForegroundColor Green
Write-Host " FIX B - Phone hotspot (works every demo)" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host @"
1. Turn ON phone mobile hotspot
2. Connect PC to the hotspot
3. Connect ESP32 to the hotspot (edit WIFI_SSID in rfid_gate_config.h)
4. Run: powershell -ExecutionPolicy Bypass -File .\scripts\esp32-api-url.ps1 -UpdateConfig
5. Run: .\sync-arduino.bat
6. Re-upload Entry.ino in Arduino IDE
7. Run: .\start.ps1 on PC
"@ -ForegroundColor White

Write-Host "========================================" -ForegroundColor Green
Write-Host " FIX C - Windows firewall (still worth doing)" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host "Double-click: allow-laravel-firewall.bat (Admin)" -ForegroundColor White
Write-Host ""

$fwScript = Join-Path $PSScriptRoot "allow-laravel-firewall.ps1"
if (Test-Path $fwScript) {
    $isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
        [Security.Principal.WindowsBuiltInRole]::Administrator)
    if ($isAdmin) {
        & powershell -NoProfile -ExecutionPolicy Bypass -File $fwScript
    } else {
        Write-Host "Run allow-laravel-firewall.bat as Administrator to apply firewall rules." -ForegroundColor DarkGray
    }
}

Write-Host ""
Write-Host "After Fix A or B, ESP32 Serial should show:" -ForegroundColor Cyan
Write-Host "  TCP probe ... = OK" -ForegroundColor Green
Write-Host "  API online - heartbeats OK" -ForegroundColor Green
