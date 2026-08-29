#Requires -Version 5.1
<#
.SYNOPSIS
  Start background GitHub auto-sync (pull + push every 90 seconds).
#>
param(
    [int]$IntervalSeconds = 90
)

$Root = $PSScriptRoot
& powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $Root "scripts\auto-sync-github.ps1") -IntervalSeconds $IntervalSeconds
