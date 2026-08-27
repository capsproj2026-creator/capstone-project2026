#Requires -Version 5.1
<#
.SYNOPSIS
  Check AI camera RTSP reachability from this PC (ping + port 554 + optional RTSP probe).

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\test-cameras.ps1
  powershell -ExecutionPolicy Bypass -File .\scripts\test-cameras.ps1 -RtspProbe
#>
param(
    [switch]$RtspProbe
)

$ErrorActionPreference = 'Continue'
$Root = Split-Path -Parent $PSScriptRoot
$EnvFile = Join-Path $Root '.env'

function Import-DotEnv([string]$Path) {
    Get-Content $Path | ForEach-Object {
        $line = $_.Trim()
        if (-not $line -or $line.StartsWith('#') -or -not $line.Contains('=')) { return }
        $parts = $line.Split('=', 2)
        $key = $parts[0].Trim()
        $value = $parts[1].Trim().Trim('"').Trim("'")
        if ($key) { Set-Item -Path "Env:$key" -Value $value }
    }
}

if (-not (Test-Path $EnvFile)) {
    Write-Error "Missing .env at $EnvFile"
}

Import-DotEnv $EnvFile

$wifi = (Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
    Where-Object { $_.InterfaceAlias -match 'Wi-Fi|Wireless' -and $_.IPAddress -notlike '169.*' } |
    Select-Object -First 1 -ExpandProperty IPAddress)

Write-Host '========================================' -ForegroundColor Cyan
Write-Host ' AI camera connectivity check' -ForegroundColor Cyan
Write-Host '========================================' -ForegroundColor Cyan
Write-Host ("PC Wi-Fi IP: {0}" -f ($(if ($wifi) { $wifi } else { '(none detected)' }))) -ForegroundColor DarkGray
Write-Host ''

$anyFail = $false

for ($n = 1; $n -le 3; $n++) {
    $enabled = [Environment]::GetEnvironmentVariable("AI_CAMERA_${n}_ENABLED")
    if ($enabled -and $enabled.ToLower() -in @('0', 'false', 'no', 'off')) { continue }

    $ip = [Environment]::GetEnvironmentVariable("AI_CAMERA_${n}_IP")
    if (-not $ip) { continue }

    $id = [Environment]::GetEnvironmentVariable("AI_CAMERA_${n}_ID")
    if (-not $id) { $id = "CAM-AI-$n" }
    $user = [Environment]::GetEnvironmentVariable("AI_CAMERA_${n}_USER")
    $pass = [Environment]::GetEnvironmentVariable("AI_CAMERA_${n}_PASS")

    Write-Host ("--- Camera {0} ({1}) @ {2} ---" -f $n, $id, $ip) -ForegroundColor Yellow

    $ping = Test-Connection -ComputerName $ip -Count 1 -Quiet -ErrorAction SilentlyContinue
    Write-Host ("  Ping:        {0}" -f ($(if ($ping) { 'OK' } else { 'FAIL (camera may block ICMP or wrong network)' })))

    $rtsp = Test-NetConnection -ComputerName $ip -Port 554 -WarningAction SilentlyContinue
    $rtspOk = [bool]$rtsp.TcpTestSucceeded
    Write-Host ("  RTSP :554:   {0}" -f ($(if ($rtspOk) { 'OK' } else { 'FAIL - Live Cameras will show reconnecting' }))) `
        -ForegroundColor $(if ($rtspOk) { 'Green' } else { 'Red' })

    if (-not $user -or -not $pass) {
        Write-Host '  Credentials: MISSING USER/PASS in .env' -ForegroundColor Red
        $anyFail = $true
    } else {
        Write-Host ("  Credentials: user={0} pass=set" -f $user) -ForegroundColor DarkGray
    }

    if (-not $rtspOk) {
        $anyFail = $true
        Write-Host '  Fix: PC and camera must be on the same LAN. Update AI_CAMERA_N_IP from Tapo app (device info).' -ForegroundColor DarkYellow
    }

    if ($RtspProbe -and $user -and $pass) {
        $py = Join-Path $Root 'hardware\ai_parking\.venv\Scripts\python.exe'
        if (-not (Test-Path $py)) { $py = 'python' }
        $probe = Join-Path $Root 'hardware\ai_parking\test_rtsp.py'
        Write-Host '  RTSP probe:' -ForegroundColor DarkGray
        & $py $probe --from-env $n
        if ($LASTEXITCODE -ne 0) { $anyFail = $true }
    }

    Write-Host ''
}

if ($anyFail) {
    Write-Host 'One or more cameras are unreachable from this PC.' -ForegroundColor Red
    Write-Host 'After fixing .env, restart the AI parking window (start.ps1).' -ForegroundColor DarkYellow
    exit 1
}

Write-Host 'All enabled cameras look reachable on the network.' -ForegroundColor Green
exit 0
