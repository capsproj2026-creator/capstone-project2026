#Requires -Version 5.1
<#
.SYNOPSIS
  Download portable arduino-cli into tools/arduino-cli for compile verification.
#>
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$CliDir = Join-Path $Root "tools\arduino-cli"
New-Item -ItemType Directory -Force -Path $CliDir | Out-Null
$zip = Join-Path $CliDir "arduino-cli.zip"
Invoke-WebRequest -Uri "https://downloads.arduino.cc/arduino-cli/arduino-cli_latest_Windows_64bit.zip" -OutFile $zip -UseBasicParsing
Expand-Archive -Path $zip -DestinationPath $CliDir -Force
Remove-Item $zip -Force
& (Join-Path $CliDir "arduino-cli.exe") version
