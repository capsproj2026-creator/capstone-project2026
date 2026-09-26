#Requires -Version 5.1
<#
.SYNOPSIS
  Stop ISCVMS stack processes on known ports (safe: skips unrelated processes).

.PARAMETER IncludeAi
  Also stop AI parking on 8090 (default: true).

.PARAMETER SkipAi
  Leave AI parking running.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\stop-system.ps1
#>
param(
    [switch]$IncludeAi = $true,
    [switch]$SkipAi
)

$ErrorActionPreference = 'Continue'
. (Join-Path $PSScriptRoot 'iscvms-common.ps1')

$stopAi = $IncludeAi -and -not $SkipAi

Write-Host ''
Write-Host 'Stopping ISCVMS services...' -ForegroundColor Cyan

$ports = @(8000, 8001, 8080)
if ($stopAi) { $ports += 8090 }

foreach ($port in $ports) {
    Write-Host "Port $port"
    Stop-IscvmsPort -Port $port
}

# Also stop schedule:work if still alive without a dedicated listen port
Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -and ($_.CommandLine -like '*schedule:work*') -and ($_.CommandLine -like '*capstone*' -or $_.CommandLine -like '*artisan*') } |
    ForEach-Object {
        Write-Host "  Stopping schedule:work PID $($_.ProcessId)" -ForegroundColor Cyan
        Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
    }

Start-Sleep -Seconds 1
Write-Host ''
Write-Host 'Done. Verify with: .\scripts\status-system.ps1' -ForegroundColor Green
Write-Host ''
