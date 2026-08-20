#Requires -RunAsAdministrator
#Requires -Version 5.1
<#
.SYNOPSIS
  Allow ESP32 / LAN devices to reach Laravel on port 8000.
#>
$ErrorActionPreference = "Stop"

Write-Host "Smart Campus VMS — LAN access for ESP32" -ForegroundColor Cyan
Write-Host ""

# Wi-Fi as Private network (Public profile blocks more traffic by default).
Get-NetConnectionProfile -ErrorAction SilentlyContinue | ForEach-Object {
    if ($_.NetworkCategory -ne "Private") {
        Write-Host ("Setting '{0}' network to Private (was {1})" -f $_.InterfaceAlias, $_.NetworkCategory) -ForegroundColor Yellow
        Set-NetConnectionProfile -InterfaceIndex $_.InterfaceIndex -NetworkCategory Private
    }
}

$ruleName = "Laravel Dev Server 8000"
netsh advfirewall firewall delete rule name="$ruleName" 2>$null | Out-Null
netsh advfirewall firewall add rule name="$ruleName" dir=in action=allow protocol=TCP localport=8000 profile=any enable=yes
Write-Host "Firewall: allow inbound TCP 8000 (all profiles)" -ForegroundColor Green

$php = (Get-Command php -ErrorAction SilentlyContinue).Source
if ($php) {
    $phpRule = "PHP Laravel artisan serve"
    netsh advfirewall firewall delete rule name="$phpRule" 2>$null | Out-Null
    netsh advfirewall firewall add rule name="$phpRule" dir=in action=allow program="$php" enable=yes profile=any
    Write-Host "Firewall: allow php.exe inbound ($php)" -ForegroundColor Green
}

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

Write-Host ""
if ($ip) {
    Write-Host "PC LAN IP: $ip" -ForegroundColor Yellow
    Write-Host "ESP32 rfid_gate_config.h:" -ForegroundColor Yellow
    Write-Host "  #define API_HOST `"$ip`"" -ForegroundColor White
    Write-Host "  #define API_PORT 8000" -ForegroundColor White
    Write-Host ""
    Write-Host "Phone test (same Wi-Fi as ESP32):" -ForegroundColor Cyan
    Write-Host "  http://${ip}:8000" -ForegroundColor White
    Write-Host "  If phone CANNOT open this page, the router blocks device-to-device (AP isolation)." -ForegroundColor DarkGray
} else {
    Write-Host "Could not detect 192.168.x.x IP — run ipconfig" -ForegroundColor Red
}

Write-Host ""
Write-Host "Done. Keep Laravel running: .\start.ps1" -ForegroundColor Green
