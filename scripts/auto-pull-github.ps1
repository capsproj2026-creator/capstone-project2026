#Requires -Version 5.1
<#
.SYNOPSIS
  Background watcher: auto-pull AND auto-push to GitHub.

  Prefer scripts\auto-sync-github.ps1 (same behavior). This wrapper remains for older docs.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\auto-pull-github.ps1
#>
param([int]$IntervalSeconds = 90)

$syncer = Join-Path $PSScriptRoot "auto-sync-github.ps1"
& powershell -ExecutionPolicy Bypass -File $syncer -IntervalSeconds $IntervalSeconds
