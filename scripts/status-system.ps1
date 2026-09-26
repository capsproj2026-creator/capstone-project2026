#Requires -Version 5.1
<#
.SYNOPSIS
  Show ISCVMS service status (ports + HTTP probes). Does not start or stop anything.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\status-system.ps1
#>
$ErrorActionPreference = 'Continue'
. (Join-Path $PSScriptRoot 'iscvms-common.ps1')
$Root = Get-IscvmsRoot

Write-Host ''
Write-Host 'ISCVMS System Status' -ForegroundColor Cyan
Write-Host ("Root: {0}" -f $Root)
Write-Host ''

$ports = @(
    @{ Name = 'LAN front'; Port = 8000; Url = 'http://127.0.0.1:8000/' },
    @{ Name = 'Laravel loopback'; Port = 8001; Url = 'http://127.0.0.1:8001/up' },
    @{ Name = 'Reverb'; Port = 8080; Url = $null },
    @{ Name = 'AI parking'; Port = 8090; Url = 'http://127.0.0.1:8090/health' }
)

foreach ($p in $ports) {
    $listening = @(Get-ListenerPids -Port $p.Port).Count -gt 0
    $tcp = Test-TcpPort -HostName '127.0.0.1' -Port $p.Port
    $state = if ($listening -or $tcp) { 'Listening' } else { 'Closed' }
    $detail = "port $($p.Port)"
    if ($p.Url) {
        $http = Test-HttpOk -Url $p.Url
        if ($http.Ok) {
            $state = 'Online'
            if ($p.Port -eq 8090 -and $http.Body) {
                try {
                    $j = $http.Body | ConvertFrom-Json
                    $cams = @($j.cameras)
                    $detail = "port 8090 - cameras=$($cams -join ',') - any_online=$($j.any_online)"
                } catch {
                    $detail = "HTTP $($http.Status)"
                }
            } else {
                $detail = "HTTP $($http.Status) - $($p.Url)"
            }
        } elseif ($listening -or $tcp) {
            $state = 'Degraded'
            $detail = "port open but HTTP failed: $($http.Error)"
        }
    }
    Write-StatusRow -Name $p.Name -State $state -Detail $detail
}

# MongoDB
$mongoListening = Test-TcpPort -HostName '127.0.0.1' -Port 27017
$mongoState = if ($mongoListening) { 'Listening' } else { 'Closed/Remote' }
$mongoDetail = '127.0.0.1:27017 (Atlas uses cloud URI — Closed here is OK if MONGODB_URI is Atlas)'
if (Test-Path (Join-Path $Root 'artisan')) {
    Push-Location $Root
    try {
        $out = & php artisan capstone:db-status 2>&1 | Out-String
        if ($LASTEXITCODE -eq 0 -or $out -match 'ok|connected|online|reachable') {
            $mongoState = 'OK'
            $mongoDetail = ($out -split "`n" | Select-Object -First 3) -join ' '
        } elseif ($out) {
            $mongoDetail = ($out -split "`n" | Select-Object -First 2) -join ' '
        }
    } catch {
        $mongoDetail = $_.Exception.Message
    }
    Pop-Location
}
Write-StatusRow -Name 'MongoDB' -State $mongoState -Detail $mongoDetail

# Storage writable
$storageApp = Join-Path $Root 'storage\app'
$writable = $false
try {
    $probe = Join-Path $storageApp ('.turnover-write-probe-' + [guid]::NewGuid().ToString('N'))
    'ok' | Set-Content -LiteralPath $probe -ErrorAction Stop
    Remove-Item -LiteralPath $probe -Force -ErrorAction SilentlyContinue
    $writable = $true
} catch { }
Write-StatusRow -Name 'Storage writable' -State $(if ($writable) { 'OK' } else { 'Fail' }) -Detail $storageApp

Write-Host ''
Write-Host 'Access URLs' -ForegroundColor Cyan
Write-Host '  Local (fast):  http://127.0.0.1:8001'
Write-Host '  LAN / ESP32:   http://127.0.0.1:8000  (or this PC LAN IP:8000)'
Write-Host '  AI health:     http://127.0.0.1:8090/health'
Write-Host ''
Write-Host 'Commands: .\scripts\start-system.ps1 | .\scripts\stop-system.ps1 | .\scripts\restart-system.ps1'
Write-Host ''
