#Requires -Version 5.1
<#
.SYNOPSIS
  Shortcut - same as scripts\start-ai-parking.ps1 (starts everything, including ngrok for Google Form webhook).
#>
param(
    [switch]$SkipWebStack,
    [switch]$SkipNgrok
)

$Root = $PSScriptRoot
$argsList = @()
if ($SkipWebStack) { $argsList += "-SkipWebStack" }
if ($SkipNgrok) { $argsList += "-SkipNgrok" }

& powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $Root "scripts\start-ai-parking.ps1") @argsList
