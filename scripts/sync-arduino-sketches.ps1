#Requires -Version 5.1
<#
.SYNOPSIS
  Copy Entry/Exit Arduino sketches to OneDrive Arduino IDE folders.

.DESCRIPTION
  Source (canonical):
    hardware/arduino/Entry  ->  %USERPROFILE%\OneDrive\Documents\Arduino\Entry
    hardware/arduino/Exit   ->  %USERPROFILE%\OneDrive\Documents\Arduino\Exit

  Also refreshes rfid_gate_common.h from hardware/esp32_rfid_gate when newer.
#>
param(
    [string]$EntryDir = $env:ARDUINO_ENTRY_DIR,
    [string]$ExitDir = $env:ARDUINO_EXIT_DIR,
    [switch]$Watch,
    [switch]$Quiet
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$EntrySrc = Join-Path $Root "hardware\arduino\Entry"
$ExitSrc = Join-Path $Root "hardware\arduino\Exit"
$CommonSrc = Join-Path $Root "hardware\esp32_rfid_gate\rfid_gate_common.h"
$ConfigSrc = Join-Path $Root "hardware\esp32_rfid_gate\rfid_gate_config.h"

if (-not $EntryDir) {
    $EntryDir = Join-Path $env:USERPROFILE "OneDrive\Documents\Arduino\Entry"
}
if (-not $ExitDir) {
    $ExitDir = Join-Path $env:USERPROFILE "OneDrive\Documents\Arduino\Exit"
}

function Write-SyncLog {
    param([string]$Message, [string]$Color = "Gray")
    if (-not $Quiet) {
        Write-Host $Message -ForegroundColor $Color
    }
}

function Sync-SketchFolder {
    param(
        [string]$Source,
        [string]$Dest,
        [string]$InoName
    )

    if (-not (Test-Path $Source)) {
        throw "Missing source folder: $Source"
    }

    New-Item -ItemType Directory -Force -Path $Dest | Out-Null

    $files = @(
        @{ Src = Join-Path $Source $InoName; Dst = Join-Path $Dest $InoName },
        @{ Src = Join-Path $Source "rfid_gate_common.h"; Dst = Join-Path $Dest "rfid_gate_common.h" }
    )

    if (Test-Path $CommonSrc) {
        $files[1].Src = $CommonSrc
    }

    foreach ($f in $files) {
        if (-not (Test-Path $f.Src)) {
            throw "Missing source file: $($f.Src)"
        }
        Copy-Item -Path $f.Src -Destination $f.Dst -Force
    }

    $configDest = Join-Path $Dest "rfid_gate_config.h"
    if (Test-Path $ConfigSrc) {
        Copy-Item -Path $ConfigSrc -Destination $configDest -Force
    } elseif (Test-Path (Join-Path $Source "rfid_gate_config.h")) {
        Copy-Item -Path (Join-Path $Source "rfid_gate_config.h") -Destination $configDest -Force
    } elseif (-not (Test-Path $configDest)) {
        Copy-Item -Path (Join-Path $Source "rfid_gate_config.example.h") -Destination $configDest -Force
        Write-SyncLog '  Created rfid_gate_config.h from example - edit Wi-Fi/API before flashing' 'Yellow'
    }
}

function Sync-ArduinoSketches {
    # Keep in-repo arduino copies aligned with canonical esp32_rfid_gate common header.
    if (Test-Path $CommonSrc) {
        Copy-Item -Path $CommonSrc -Destination (Join-Path $EntrySrc "rfid_gate_common.h") -Force
        Copy-Item -Path $CommonSrc -Destination (Join-Path $ExitSrc "rfid_gate_common.h") -Force
    }
    if (Test-Path $ConfigSrc) {
        Copy-Item -Path $ConfigSrc -Destination (Join-Path $EntrySrc "rfid_gate_config.h") -Force
        Copy-Item -Path $ConfigSrc -Destination (Join-Path $ExitSrc "rfid_gate_config.h") -Force
    }

    Sync-SketchFolder -Source $EntrySrc -Dest $EntryDir -InoName "Entry.ino"
    Sync-SketchFolder -Source $ExitSrc -Dest $ExitDir -InoName "Exit.ino"

    $stamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Write-SyncLog "[$stamp] Synced ESP32 sketches" "Green"
    Write-SyncLog "  Entry -> $EntryDir" "DarkGray"
    Write-SyncLog "  Exit  -> $ExitDir" "DarkGray"
}

if (-not $Watch) {
    Sync-ArduinoSketches
    return
}

Write-SyncLog 'Auto-sync ON - watching hardware/arduino (Ctrl+C to stop)' 'Cyan'
Sync-ArduinoSketches

$watchPaths = @($EntrySrc, $ExitSrc, (Split-Path $CommonSrc -Parent))
$watchers = @()
$script:syncPending = $false

foreach ($path in $watchPaths) {
    if (-not (Test-Path $path)) { continue }
    $w = New-Object System.IO.FileSystemWatcher
    $w.Path = $path
    $w.Filter = '*.*'
    $w.IncludeSubdirectories = $false
    $w.EnableRaisingEvents = $true
    $action = { $script:syncPending = $true }
    Register-ObjectEvent -InputObject $w -EventName Changed -Action $action | Out-Null
    Register-ObjectEvent -InputObject $w -EventName Created -Action $action | Out-Null
    Register-ObjectEvent -InputObject $w -EventName Renamed -Action $action | Out-Null
    $watchers += $w
}

$lastRun = Get-Date
try {
    while ($true) {
        if ($script:syncPending -and ((Get-Date) - $lastRun).TotalMilliseconds -gt 800) {
            Start-Sleep -Milliseconds 300
            Sync-ArduinoSketches
            $lastRun = Get-Date
            $script:syncPending = $false
        }
        Start-Sleep -Milliseconds 300
    }
} finally {
    foreach ($w in $watchers) {
        $w.EnableRaisingEvents = $false
        $w.Dispose()
    }
    Get-EventSubscriber | ForEach-Object {
        Unregister-Event -SubscriptionId $_.SubscriptionId -ErrorAction SilentlyContinue
    }
}
