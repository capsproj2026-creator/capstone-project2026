#Requires -Version 5.1
<#
.SYNOPSIS
  Background watcher: auto-pull from GitHub every 2 minutes.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\auto-pull-github.ps1
#>
param([int]$IntervalSeconds = 120)

$Root = Split-Path -Parent $PSScriptRoot
$syncScript = Join-Path $PSScriptRoot "sync-project.ps1"

Write-Host "=== GitHub auto-pull watcher ===" -ForegroundColor Green
Write-Host "Repo:  $Root" -ForegroundColor DarkGray
Write-Host "Every: $IntervalSeconds seconds" -ForegroundColor DarkGray
Write-Host "Press Ctrl+C to stop." -ForegroundColor DarkGray
Write-Host ""

while ($true) {
    $stamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Write-Host "[$stamp] Checking GitHub..." -ForegroundColor Cyan
    try {
        & powershell -ExecutionPolicy Bypass -File $syncScript -SkipDb -Quiet
    }
    catch {
        Write-Warning $_.Exception.Message
    }
    Start-Sleep -Seconds $IntervalSeconds
}
