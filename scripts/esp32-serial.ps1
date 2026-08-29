#Requires -Version 5.1
<#
.SYNOPSIS
  Window A — live ESP32 Serial Monitor in this terminal (115200 baud).

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\esp32-serial.ps1

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\esp32-serial.ps1 -Port COM3
#>
param(
    [string]$Port = "",
    [int]$Baud = 115200
)

$ErrorActionPreference = "Stop"

function Get-Esp32Ports {
    $names = @()
    try {
        $names += [System.IO.Ports.SerialPort]::GetPortNames()
    } catch {}
    try {
        $pnp = Get-CimInstance Win32_PnPEntity -ErrorAction SilentlyContinue |
            Where-Object { $_.Name -match '(COM\d+)' } |
            ForEach-Object { $_.Name }
        foreach ($n in $pnp) { $names += $n }
    } catch {}
    $ports = @()
    foreach ($n in $names) {
        if ($n -match '(COM\d+)') { $ports += $Matches[1] }
    }
    return @($ports | Select-Object -Unique)
}

Write-Host "========================================" -ForegroundColor Green
Write-Host " ESP32 Serial Monitor (Window A)" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host "Baud $Baud  |  Ctrl+C to stop"
Write-Host "Close Arduino Serial Monitor first (port can be used by only one app)."
Write-Host ""

$ports = Get-Esp32Ports
if (-not $Port) {
    if ($ports.Count -eq 1) {
        $Port = $ports[0]
    } elseif ($ports.Count -gt 1) {
        Write-Host "COM ports found:" -ForegroundColor Cyan
        $ports | ForEach-Object { Write-Host "  $_" }
        $Port = Read-Host "Type the ESP32 port (example COM3)"
    }
}

if (-not $Port) {
    Write-Host "No ESP32 COM port found." -ForegroundColor Red
    Write-Host "  1. Plug the EXIT ESP32 in by USB"
    Write-Host "  2. In Arduino IDE: Tools -> Port (note COM number)"
    Write-Host "  3. Re-run: powershell -ExecutionPolicy Bypass -File .\scripts\esp32-serial.ps1 -Port COM3"
    exit 1
}

Write-Host "Opening $Port ..." -ForegroundColor Cyan
$serial = New-Object System.IO.Ports.SerialPort $Port, $Baud, 'None', 8, 'One'
$serial.ReadTimeout = 200
$serial.DtrEnable = $true
$serial.RtsEnable = $false
try {
    $serial.Open()
} catch {
    Write-Host "Could not open $Port : $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "Close Arduino Serial Monitor / Upload window, then try again." -ForegroundColor Yellow
    exit 1
}

Write-Host "Connected. Waiting for ESP32 logs (reset the board if nothing appears)..." -ForegroundColor Green
Write-Host ""

try {
    while ($true) {
        try {
            $chunk = $serial.ReadExisting()
            if ($chunk) { [Console]::Write($chunk) }
        } catch [TimeoutException] {
        } catch {
            if ($_.Exception.InnerException -is [TimeoutException]) { }
            else { throw }
        }
        Start-Sleep -Milliseconds 40
    }
} finally {
    if ($serial.IsOpen) { $serial.Close() }
    $serial.Dispose()
}
