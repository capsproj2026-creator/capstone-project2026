#Requires -Version 5.1
<#
.SYNOPSIS
  Restore ISCVMS files (+ optional mongorestore) from a timestamped backup folder.

.PARAMETER BackupDir
  Path to storage/backups/YYYYMMDD-HHMMSS

.PARAMETER SkipMongo
  Restore files only.

.PARAMETER Force
  Skip confirmation prompt (still never runs migrate:fresh).

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\restore-system.ps1 -BackupDir .\storage\backups\20260926-130000
#>
param(
    [Parameter(Mandatory = $true)]
    [string]$BackupDir,
    [switch]$SkipMongo,
    [switch]$Force
)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

if (-not (Test-Path -LiteralPath $BackupDir)) {
    Write-Error "Backup folder not found: $BackupDir"
}

Write-Host "Restore from: $BackupDir" -ForegroundColor Yellow
Write-Host 'This overwrites storage/app and zone JSON files from the backup.' -ForegroundColor Yellow
if (-not $Force) {
    $ans = Read-Host 'Type YES to continue'
    if ($ans -ne 'YES') {
        Write-Host 'Cancelled.'
        exit 1
    }
}

$filesRoot = Join-Path $BackupDir 'files'
$storageSrc = Join-Path $filesRoot 'storage_app'
if (Test-Path -LiteralPath $storageSrc) {
    $storageDst = Join-Path $Root 'storage\app'
    Write-Host 'Restoring storage/app ...' -ForegroundColor Cyan
    Copy-Item -Path (Join-Path $storageSrc '*') -Destination $storageDst -Recurse -Force
}

$zonesSrc = Join-Path $filesRoot 'zones'
if (Test-Path -LiteralPath $zonesSrc) {
    $zonesDst = Join-Path $Root 'hardware\ai_parking'
    Write-Host 'Restoring zone JSON files ...' -ForegroundColor Cyan
    Get-ChildItem $zonesSrc -Filter '*.json' | ForEach-Object {
        Copy-Item $_.FullName -Destination (Join-Path $zonesDst $_.Name) -Force
        Write-Host ("  " + $_.Name)
    }
}

if (-not $SkipMongo) {
    $mongoSrc = Join-Path $BackupDir 'mongo'
    $mongorestore = Get-Command mongorestore -ErrorAction SilentlyContinue
    if ($mongorestore -and (Test-Path $mongoSrc)) {
        $uri = ''
        $dbName = 'capstone'
        Get-Content (Join-Path $Root '.env') -ErrorAction SilentlyContinue | ForEach-Object {
            if ($_ -match '^\s*MONGODB_URI\s*=\s*(.+)$') {
                $uri = $Matches[1].Trim().Trim('"').Trim("'")
            }
            if ($_ -match '^\s*MONGODB_DATABASE\s*=\s*(.+)$') {
                $dbName = $Matches[1].Trim().Trim('"').Trim("'")
            }
        }
        Write-Host "Running mongorestore into database '$dbName' ..." -ForegroundColor Cyan
        $dumpDbPath = Join-Path $mongoSrc $dbName
        if (-not (Test-Path $dumpDbPath)) {
            $first = Get-ChildItem $mongoSrc -Directory -ErrorAction SilentlyContinue | Select-Object -First 1
            if ($first) { $dumpDbPath = $first.FullName }
        }
        if ($uri) {
            & mongorestore --uri $uri --db $dbName --drop $dumpDbPath
        } else {
            & mongorestore --db $dbName --drop $dumpDbPath
        }
    } else {
        Write-Host 'Skipping Mongo restore (mongorestore missing or no mongo dump).' -ForegroundColor DarkYellow
    }
}

Write-Host ''
Write-Host 'Restore finished. Restart services: .\scripts\restart-system.ps1' -ForegroundColor Green
Write-Host 'Never use php artisan migrate:fresh on the production/turnover database.' -ForegroundColor DarkYellow
