#Requires -Version 5.1
<#
.SYNOPSIS
  Install a current ngrok agent and allow Windows Defender to run it.

  Run this once in an elevated PowerShell (right-click -> Run as administrator):
    powershell -ExecutionPolicy Bypass -File .\scripts\install-ngrok.ps1
#>
$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$toolsDir = Join-Path $projectRoot 'tools\ngrok'
$zipPath = Join-Path $env:TEMP 'ngrok-v3-stable-windows-amd64.zip'
$downloadUrl = 'https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-windows-amd64.zip'

$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
    [Security.Principal.WindowsBuiltInRole]::Administrator
)

if (-not $isAdmin) {
    Write-Host 'Requesting Administrator permission...' -ForegroundColor Yellow
    $arg = "-NoProfile -ExecutionPolicy Bypass -File `"$PSCommandPath`""
    Start-Process powershell -Verb RunAs -Wait -ArgumentList $arg
    exit $LASTEXITCODE
}

Write-Host 'Adding Windows Defender exclusions for ngrok...' -ForegroundColor Cyan
New-Item -ItemType Directory -Force -Path $toolsDir | Out-Null
Add-MpPreference -ExclusionPath $toolsDir
Add-MpPreference -ExclusionProcess 'ngrok.exe'

Write-Host 'Downloading latest stable ngrok...' -ForegroundColor Cyan
Invoke-WebRequest -Uri $downloadUrl -OutFile $zipPath -UseBasicParsing
Expand-Archive -Path $zipPath -DestinationPath $toolsDir -Force
Remove-Item $zipPath -Force -ErrorAction SilentlyContinue

$ngrokExe = Join-Path $toolsDir 'ngrok.exe'
if (-not (Test-Path $ngrokExe)) {
    throw "Download finished but ngrok.exe was not found in $toolsDir"
}

Unblock-File $ngrokExe
$version = & $ngrokExe version
Write-Host "Installed: $version" -ForegroundColor Green
Write-Host "Path:      $ngrokExe" -ForegroundColor Green
Write-Host ''
Write-Host 'If you have not set an authtoken yet:' -ForegroundColor DarkYellow
Write-Host '  ngrok config add-authtoken YOUR_TOKEN' -ForegroundColor White
Write-Host 'Or with the project binary:' -ForegroundColor DarkYellow
Write-Host "  & `"$ngrokExe`" config add-authtoken YOUR_TOKEN" -ForegroundColor White
Write-Host ''
Write-Host 'Press Enter to close...'
[void][Console]::ReadLine()
