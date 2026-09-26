#Requires -Version 5.1
<#
.SYNOPSIS
  Shared helpers for ISCVMS status / stop / start port checks.
#>

$script:IscvmsRoot = Split-Path -Parent $PSScriptRoot
$script:PidDir = Join-Path $script:IscvmsRoot "storage\framework\turnover-pids"

function Get-IscvmsRoot {
    return $script:IscvmsRoot
}

function Ensure-PidDir {
    if (-not (Test-Path -LiteralPath $script:PidDir)) {
        New-Item -ItemType Directory -Force -Path $script:PidDir | Out-Null
    }
}

function Get-ListenerPids([int]$Port) {
    $pids = @()
    $lines = netstat -ano 2>$null | Select-String ":$Port\s+.*LISTENING"
    foreach ($line in $lines) {
        $parts = ($line.ToString() -split '\s+') | Where-Object { $_ -ne '' }
        if ($parts.Count -ge 5) {
            $procId = $parts[-1]
            if ($procId -match '^\d+$' -and [int]$procId -gt 0) {
                $pids += [int]$procId
            }
        }
    }
    return ($pids | Select-Object -Unique)
}

function Test-TcpPort([string]$HostName, [int]$Port, [int]$TimeoutMs = 800) {
    try {
        $client = New-Object System.Net.Sockets.TcpClient
        $iar = $client.BeginConnect($HostName, $Port, $null, $null)
        $ok = $iar.AsyncWaitHandle.WaitOne($TimeoutMs, $false)
        if (-not $ok) {
            $client.Close()
            return $false
        }
        $client.EndConnect($iar)
        $client.Close()
        return $true
    } catch {
        return $false
    }
}

function Test-HttpOk([string]$Url, [int]$TimeoutSec = 3) {
    try {
        $resp = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec $TimeoutSec -ErrorAction Stop
        return @{ Ok = ($resp.StatusCode -ge 200 -and $resp.StatusCode -lt 500); Status = [int]$resp.StatusCode; Body = $resp.Content }
    } catch {
        $status = $null
        if ($_.Exception.Response -and $_.Exception.Response.StatusCode) {
            $status = [int]$_.Exception.Response.StatusCode.value__
        }
        return @{ Ok = $false; Status = $status; Body = $null; Error = $_.Exception.Message }
    }
}

function Get-ProcessCommandLine([int]$ProcessId) {
    try {
        $p = Get-CimInstance Win32_Process -Filter "ProcessId=$ProcessId" -ErrorAction SilentlyContinue
        if ($p) { return [string]$p.CommandLine }
    } catch { }
    return ''
}

function Test-IscvmsOwnedProcess([int]$ProcessId) {
    $cmd = Get-ProcessCommandLine -ProcessId $ProcessId
    if ([string]::IsNullOrWhiteSpace($cmd)) { return $false }
    $markers = @(
        'artisan serve',
        'reverb:start',
        'schedule:work',
        'lan_front_router.php',
        'ai_parking_service.py',
        'vite',
        'capstone-project2026'
    )
    foreach ($m in $markers) {
        if ($cmd -like "*$m*") { return $true }
    }
    return $false
}

function Stop-IscvmsPort([int]$Port, [switch]$ForceOwnedOnly) {
    $pids = Get-ListenerPids -Port $Port
    foreach ($procId in $pids) {
        $owned = Test-IscvmsOwnedProcess -ProcessId $procId
        if ($ForceOwnedOnly -and -not $owned) {
            Write-Host "  Skip PID $procId on :$Port (not an ISCVMS command line)" -ForegroundColor DarkYellow
            continue
        }
        if (-not $owned -and -not $ForceOwnedOnly) {
            # Prefer owned; if command line empty (permissions), still stop known stack ports carefully
            $cmd = Get-ProcessCommandLine -ProcessId $procId
            $name = (Get-Process -Id $procId -ErrorAction SilentlyContinue).ProcessName
            if ($name -notin @('php', 'php-cgi', 'python', 'python3', 'node')) {
                Write-Host "  Skip PID $procId ($name) on :$Port" -ForegroundColor DarkYellow
                continue
            }
            if ($cmd -and ($cmd -notmatch 'artisan|reverb|schedule|lan_front|ai_parking|vite|capstone')) {
                Write-Host "  Skip PID $procId on :$Port (unrelated $name)" -ForegroundColor DarkYellow
                continue
            }
        }
        try {
            Write-Host "  Stopping PID $procId on :$Port" -ForegroundColor Cyan
            Stop-Process -Id $procId -Force -ErrorAction Stop
        } catch {
            $errMsg = $_.Exception.Message
            Write-Host "  Failed to stop PID ${procId}: $errMsg" -ForegroundColor Red
        }
    }
}

function Write-StatusRow([string]$Name, [string]$State, [string]$Detail = '') {
    $color = switch -Regex ($State) {
        'Online|OK|Listening|Reachable' { 'Green' }
        'Offline|Down|Fail|Closed' { 'Red' }
        default { 'Yellow' }
    }
    $line = "{0,-22} {1,-12} {2}" -f $Name, $State, $Detail
    Write-Host $line -ForegroundColor $color
}
