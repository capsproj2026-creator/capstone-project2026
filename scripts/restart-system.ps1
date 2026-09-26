#Requires -Version 5.1
<#
.SYNOPSIS
  Restart ISCVMS: stop known stack ports, then start the web (+ optional AI) stack.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\restart-system.ps1

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\restart-system.ps1 -SkipAi
#>
param(
    [switch]$SkipAi,
    [switch]$SeparateWindows
)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot

Write-Host 'Restarting ISCVMS...' -ForegroundColor Cyan
& powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'stop-system.ps1') @(
    $(if ($SkipAi) { '-SkipAi' } else { $null })
) | Out-Host

Start-Sleep -Seconds 2

$startArgs = @()
if ($SkipAi) { $startArgs += '-SkipAi' }
if ($SeparateWindows) { $startArgs += '-SeparateWindows' }

& powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'start-system.ps1') @startArgs
