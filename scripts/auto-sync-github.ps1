#Requires -Version 5.1
<#
.SYNOPSIS
  Background GitHub sync: pull remote updates, auto-commit safe local changes, push.

.DESCRIPTION
  - Pulls with --ff-only when behind origin
  - Commits tracked/untracked source changes (never .env, vendor, node_modules, storage, secrets)
  - Pushes when ahead of origin

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\auto-sync-github.ps1
  powershell -ExecutionPolicy Bypass -File .\scripts\auto-sync-github.ps1 -IntervalSeconds 60
#>
param(
    [int]$IntervalSeconds = 90,
    [switch]$Once
)

$ErrorActionPreference = 'Continue'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$excludePatterns = @(
    '^\.env$',
    '^\.env\..+',
    '^vendor/',
    '^node_modules/',
    '^public/hot$',
    '^public/build/',
    '^storage/',
    '\.log$',
    'credentials\.json$',
    'secrets?',
    'debug_plates/',
    '\.pem$',
    '\.key$'
)

function Test-IsExcluded([string]$path) {
    $norm = ($path -replace '\\', '/').TrimStart('./')
    foreach ($pat in $excludePatterns) {
        if ($norm -match $pat) { return $true }
    }
    return $false
}

function Get-SafeChangedPaths {
    $paths = @()
    $porcelain = git status --porcelain -u 2>$null
    if (-not $porcelain) { return @() }

    foreach ($line in $porcelain) {
        if ([string]::IsNullOrWhiteSpace($line)) { continue }
        # format: XY PATH or XY ORIG -> PATH
        $raw = $line.Substring(3).Trim()
        if ($raw -match ' -> ') {
            $raw = ($raw -split ' -> ', 2)[1]
        }
        $raw = $raw.Trim('"')
        if (-not (Test-IsExcluded $raw)) {
            $paths += $raw
        }
    }
    return $paths | Select-Object -Unique
}

function Sync-Once {
    $branch = (git rev-parse --abbrev-ref HEAD 2>$null).Trim()
    if (-not $branch) {
        Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Not a git repo." -ForegroundColor Red
        return
    }

    git fetch origin $branch 2>$null | Out-Null
    $behind = 0
    $ahead = 0
    try { $behind = [int](git rev-list --count "HEAD..origin/$branch" 2>$null) } catch { $behind = 0 }
    try { $ahead = [int](git rev-list --count "origin/$branch..HEAD" 2>$null) } catch { $ahead = 0 }

    if ($behind -gt 0) {
        Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Pulling $behind commit(s) from origin/$branch..." -ForegroundColor Cyan
        $pullOut = git pull --ff-only origin $branch 2>&1
        $pullOut | ForEach-Object { Write-Host $_ }
        if ($LASTEXITCODE -ne 0) {
            Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Pull failed (need manual merge). Skipping commit/push this cycle." -ForegroundColor Yellow
            return
        }
    }

    $safePaths = @(Get-SafeChangedPaths)
    if ($safePaths.Count -gt 0) {
        Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Auto-committing $($safePaths.Count) safe file(s)..." -ForegroundColor Cyan
        foreach ($p in $safePaths) {
            git add -- "$p" 2>$null | Out-Null
        }
        $stamp = Get-Date -Format 'yyyy-MM-dd HH:mm'
        git commit -m "chore: auto-sync local changes ($stamp)" 2>&1 | Out-Host
        if ($LASTEXITCODE -eq 0) {
            $ahead = $ahead + 1
        }
    }

    try { $ahead = [int](git rev-list --count "origin/$branch..HEAD" 2>$null) } catch { }

    if ($ahead -gt 0) {
        Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Pushing $ahead commit(s) to origin/$branch..." -ForegroundColor Cyan
        git push origin HEAD 2>&1 | Out-Host
        if ($LASTEXITCODE -eq 0) {
            Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Synced with GitHub." -ForegroundColor Green
        } else {
            Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Push failed." -ForegroundColor Yellow
        }
    } elseif ($behind -eq 0 -and $safePaths.Count -eq 0) {
        Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Already in sync with origin/$branch." -ForegroundColor DarkGray
    }
}

Write-Host "Auto-sync: $root (every ${IntervalSeconds}s). Pull + safe commit + push. Ctrl+C to stop." -ForegroundColor Green

if ($Once) {
    Sync-Once
    exit 0
}

while ($true) {
    try {
        Sync-Once
    } catch {
        Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Sync error: $_" -ForegroundColor Red
    }
    Start-Sleep -Seconds $IntervalSeconds
}
