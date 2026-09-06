#Requires -Version 5.1
<#
.SYNOPSIS
  Alias for auto-sync-github.ps1 (pull + safe auto-commit + push).

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\auto-pull-github.ps1
#>
param([int]$IntervalSeconds = 90)

$syncer = Join-Path $PSScriptRoot 'auto-sync-github.ps1'
& powershell -NoProfile -ExecutionPolicy Bypass -File $syncer -IntervalSeconds $IntervalSeconds
