#Requires -Version 5.1
<#
.SYNOPSIS
  Quick check: can LAN devices reach Laravel (same test ESP32 needs).
#>
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
    $lan = $candidates | Where-Object { $_.IPAddress -like "192.168.1.*" -or $_.IPAddress -like "192.168.0.*" } | Select-Object -First 1
    if ($lan) { return $lan.IPAddress }
    return ($candidates | Select-Object -First 1).IPAddress
}

$ip = Get-WifiLanIPv4

if (-not $ip) {
    Write-Host "No 192.168.x.x address found. Connect PC to Wi-Fi." -ForegroundColor Red
    exit 1
}

$url = "http://${ip}:8000"
Write-Host "LAN IP: $ip" -ForegroundColor Cyan
Write-Host "Testing $url ..." -ForegroundColor Cyan

$listening = netstat -ano | Select-String "0\.0\.0\.0:8000\s+.*LISTENING"
if (-not $listening) {
    Write-Host "FAIL: Nothing listening on port 8000. Run .\start.ps1" -ForegroundColor Red
    exit 1
}
Write-Host "OK: Laravel listening on 0.0.0.0:8000" -ForegroundColor Green

try {
    $r = Invoke-WebRequest -Method POST -Uri "$url/api/rfid/heartbeat" `
        -Headers @{"X-RFID-TOKEN" = "capstone-rfid-dev-token-change-me"; "Content-Type" = "application/json"} `
        -Body '{"gate_id":"GATE-IN-1"}' -UseBasicParsing -TimeoutSec 5
    Write-Host "OK: Heartbeat HTTP $($r.StatusCode)" -ForegroundColor Green
} catch {
    Write-Host "FAIL: Heartbeat from LAN IP failed" -ForegroundColor Red
    Write-Host $_.Exception.Message
    exit 1
}

Write-Host ""
Write-Host "On your PHONE (same Wi-Fi as ESP32), open:" -ForegroundColor Yellow
Write-Host "  $url" -ForegroundColor White
Write-Host ""
Write-Host "If phone works but ESP32 still fails -> re-flash ESP32 with latest firmware." -ForegroundColor DarkGray
Write-Host "If phone also fails -> run allow-laravel-firewall.bat as Admin, or disable AP isolation on router." -ForegroundColor DarkGray
