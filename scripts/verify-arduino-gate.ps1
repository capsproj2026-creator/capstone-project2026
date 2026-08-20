#Requires -Version 5.1
<#
.SYNOPSIS
  Verify Entry and Exit ESP32 sketches compile; optionally run Laravel RFID tests.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\verify-arduino-gate.ps1
#>
param(
    [switch]$SkipLaravelTests,
    [switch]$SyncFirst
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$Cli = Join-Path $Root "tools\arduino-cli\arduino-cli.exe"
$EntrySketch = Join-Path $Root "hardware\arduino\Entry"
$ExitSketch = Join-Path $Root "hardware\arduino\Exit"
$Fqbn = "esp32:esp32:esp32"
$UserDir = Join-Path $env:USERPROFILE "OneDrive\Documents\Arduino"
$LibDir = Join-Path $UserDir "libraries"
$DataDir = Join-Path $env:LOCALAPPDATA "Arduino15"

function Write-Step {
    param([string]$Message, [string]$Color = "Cyan")
    Write-Host "`n=== $Message ===" -ForegroundColor $Color
}

function Test-SketchCompile {
    param([string]$Name, [string]$Path)

    Write-Step "Compiling $Name" "Yellow"
    if (-not (Test-Path $Cli)) {
        throw "arduino-cli not found at $Cli - run scripts/setup-arduino-cli.ps1"
    }
    if (-not (Test-Path (Join-Path $Path "rfid_gate_config.h"))) {
        $example = Join-Path $Path "rfid_gate_config.example.h"
        if (Test-Path $example) {
            Copy-Item $example (Join-Path $Path "rfid_gate_config.h")
            Write-Host "Created rfid_gate_config.h from example for compile test" -ForegroundColor DarkYellow
        } else {
            throw "Missing rfid_gate_config.h in $Path"
        }
    }

    & $Cli compile --fqbn $Fqbn $Path `
        --libraries $LibDir `
        --build-property "build.extra_flags=-DCORE_DEBUG_LEVEL=0" 2>&1 | ForEach-Object { $_ }

    if ($LASTEXITCODE -ne 0) {
        throw "$Name compile FAILED (exit $LASTEXITCODE)"
    }
    Write-Host "$Name compile OK" -ForegroundColor Green
}

if ($SyncFirst) {
    Write-Step "Syncing sketches to OneDrive"
    & powershell -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot "sync-arduino-sketches.ps1") -Quiet
}

Write-Step "Arduino CLI / ESP32 core check"
& $Cli version
& $Cli core list | Select-String "esp32"

$results = @{
    EntryCompile = $false
    ExitCompile = $false
    LaravelTests = $null
}

try {
    Test-SketchCompile -Name "Entry" -Path $EntrySketch
    $results.EntryCompile = $true
} catch {
    Write-Host $_.Exception.Message -ForegroundColor Red
}

try {
    Test-SketchCompile -Name "Exit" -Path $ExitSketch
    $results.ExitCompile = $true
} catch {
    Write-Host $_.Exception.Message -ForegroundColor Red
}

if (-not $SkipLaravelTests) {
    Write-Step "Laravel RFID API tests"
    Push-Location $Root
    try {
        $testOut = php artisan test --filter=RfidGateApiTest 2>&1
        $testOut | ForEach-Object { $_ }
        if ($LASTEXITCODE -eq 0) {
            $results.LaravelTests = "PASS"
            Write-Host "RfidGateApiTest PASS" -ForegroundColor Green
        } else {
            $results.LaravelTests = "FAIL"
            Write-Host "RfidGateApiTest FAIL" -ForegroundColor Red
        }
    } finally {
        Pop-Location
    }
}

Write-Step "Summary" "Green"
Write-Host "Entry compile: $(if ($results.EntryCompile) { 'PASS' } else { 'FAIL' })"
Write-Host "Exit compile:  $(if ($results.ExitCompile) { 'PASS' } else { 'FAIL' })"
if ($null -ne $results.LaravelTests) {
    Write-Host "Laravel RFID:  $($results.LaravelTests)"
}

if (-not $results.EntryCompile -or -not $results.ExitCompile) {
    exit 1
}
