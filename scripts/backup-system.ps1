#Requires -Version 5.1
<#
.SYNOPSIS
  Timestamped backup of MongoDB (mongodump) + critical ISCVMS files.

.DESCRIPTION
  Writes to storage/backups/YYYYMMDD-HHMMSS/ (gitignored).
  Never commits backups. Does not run migrate:fresh.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\backup-system.ps1
#>
param(
    [string]$OutRoot = ''
)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

if (-not $OutRoot) {
    $OutRoot = Join-Path $Root 'storage\backups'
}
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$dest = Join-Path $OutRoot $stamp
New-Item -ItemType Directory -Force -Path $dest | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $dest 'files') | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $dest 'mongo') | Out-Null

Write-Host "Backup destination: $dest" -ForegroundColor Cyan

# Parse MONGODB_URI / database from .env (no secrets printed)
$envPath = Join-Path $Root '.env'
$uri = ''
$dbName = 'capstone'
if (Test-Path $envPath) {
    Get-Content $envPath | ForEach-Object {
        if ($_ -match '^\s*MONGODB_URI\s*=\s*(.+)$') {
            $uri = $Matches[1].Trim().Trim('"').Trim("'")
        }
        if ($_ -match '^\s*MONGODB_DATABASE\s*=\s*(.+)$') {
            $dbName = $Matches[1].Trim().Trim('"').Trim("'")
        }
    }
}

$mongodump = Get-Command mongodump -ErrorAction SilentlyContinue
if ($mongodump) {
    Write-Host 'Running mongodump...' -ForegroundColor Green
    $dumpArgs = @('--out', (Join-Path $dest 'mongo'))
    if ($uri) {
        $dumpArgs = @('--uri', $uri, '--db', $dbName, '--out', (Join-Path $dest 'mongo'))
    } else {
        $dumpArgs = @('--db', $dbName, '--out', (Join-Path $dest 'mongo'))
    }
    & mongodump @dumpArgs
    if ($LASTEXITCODE -ne 0) {
        Write-Host 'mongodump reported an error — check MongoDB tools install and URI.' -ForegroundColor Yellow
    }
} else {
    Write-Host 'mongodump not found on PATH. Install MongoDB Database Tools, then re-run.' -ForegroundColor Yellow
    "mongodump_missing=1" | Set-Content (Join-Path $dest 'mongo\README.txt')
    @"
Install: https://www.mongodb.com/try/download/database-tools
Then: mongodump --uri=`"`$MONGODB_URI`" --db=$dbName --out=.\storage\backups\<stamp>\mongo
"@ | Add-Content (Join-Path $dest 'mongo\README.txt')
}

function Copy-TreeSafe([string]$From, [string]$ToRel) {
    if (-not (Test-Path -LiteralPath $From)) { return }
    $to = Join-Path (Join-Path $dest 'files') $ToRel
    $parent = Split-Path $to -Parent
    if (-not (Test-Path $parent)) { New-Item -ItemType Directory -Force -Path $parent | Out-Null }
    Copy-Item -LiteralPath $From -Destination $to -Recurse -Force -ErrorAction SilentlyContinue
    Write-Host "  Copied $ToRel"
}

Write-Host 'Copying application files...' -ForegroundColor Green
Copy-TreeSafe (Join-Path $Root 'storage\app') 'storage_app'

# External uploads root (ISCVMS_UPLOADS_ROOT) if configured in .env
$uploadsRoot = ''
Get-Content (Join-Path $Root '.env') -ErrorAction SilentlyContinue | ForEach-Object {
    if ($_ -match '^\s*ISCVMS_UPLOADS_ROOT\s*=\s*(.+)$') {
        $uploadsRoot = $Matches[1].Trim().Trim('"').Trim("'")
    }
}
if ($uploadsRoot) {
    if (-not [System.IO.Path]::IsPathRooted($uploadsRoot)) {
        $uploadsRoot = [System.IO.Path]::GetFullPath((Join-Path $Root $uploadsRoot))
    }
    if (Test-Path -LiteralPath $uploadsRoot) {
        Copy-TreeSafe $uploadsRoot 'iscvms_uploads'
    } else {
        Write-Host "  External uploads root not found yet: $uploadsRoot" -ForegroundColor DarkYellow
    }
}

Copy-TreeSafe (Join-Path $Root 'hardware\ai_parking\zones_prototype.json') 'zones\zones_prototype.json'
Copy-TreeSafe (Join-Path $Root 'hardware\ai_parking\zones_acad1.json') 'zones\zones_acad1.json'
Copy-TreeSafe (Join-Path $Root 'hardware\ai_parking\zones_duran.json') 'zones\zones_duran.json'
Copy-TreeSafe (Join-Path $Root 'hardware\ai_parking\zones_auditorium.json') 'zones\zones_auditorium.json'
Copy-TreeSafe (Join-Path $Root 'hardware\ai_parking\zones.json') 'zones\zones.json'
Get-ChildItem (Join-Path $Root 'hardware\ai_parking') -Filter 'zones_*.json' -ErrorAction SilentlyContinue |
    ForEach-Object { Copy-TreeSafe $_.FullName ("zones\" + $_.Name) }

# Manifest (no secrets)
@"
created=$stamp
database=$dbName
root=$Root
note=Do not commit this folder. Restore with scripts\restore-system.ps1 -BackupDir <path>
"@ | Set-Content (Join-Path $dest 'MANIFEST.txt')

Write-Host ''
Write-Host "Backup complete: $dest" -ForegroundColor Green
Write-Host 'Restore: powershell -ExecutionPolicy Bypass -File .\scripts\restore-system.ps1 -BackupDir "' + $dest + '"'
