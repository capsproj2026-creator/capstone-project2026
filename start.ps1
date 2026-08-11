#Requires -Version 5.1
<#
.SYNOPSIS
  Shortcut - same as scripts\start-ai-parking.ps1 (starts everything).
#>
param(
    [switch]$SkipWebStack
)

$Root = $PSScriptRoot
$argsList = @()
if ($SkipWebStack) { $argsList += "-SkipWebStack" }

& powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $Root "scripts\start-ai-parking.ps1") @argsList
