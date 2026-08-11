#Requires -Version 5.1
<#
.SYNOPSIS
  Watch the repo: pull from GitHub, then commit+push local changes (never .env/secrets).

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\auto-sync-github.ps1

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\auto-sync-github.ps1 -IntervalSeconds 60
#>
param(
    [int]$IntervalSeconds = 90
)

$ErrorActionPreference = "Continue"
$Root = Split-Path -Parent $PSScriptRoot
$syncScript = Join-Path $PSScriptRoot "sync-project.ps1"

$IgnoreExact = @(
    ".env",
    "public/hot",
    "hardware/ai_parking/debug_plates"
)
$IgnorePrefixes = @(
    "storage/",
    "vendor/",
    "node_modules/",
    "hardware/ai_parking/__pycache__/",
    "hardware/ai_parking/debug_plates/"
)
$IgnoreGlobs = @(
    "*.log",
    "*.pyc",
    ".phpunit.result.cache"
)

function Get-DotEnvValue([string]$Key) {
    $envFile = Join-Path $Root ".env"
    if (-not (Test-Path $envFile)) { return "" }
    foreach ($line in Get-Content $envFile) {
        if ($line -match "^\s*$([regex]::Escape($Key))\s*=\s*(.*)$") {
            return $Matches[1].Trim().Trim('"').Trim("'")
        }
    }
    return ""
}

function Test-IgnoredPath([string]$Path) {
    $norm = ($Path -replace "\\", "/").TrimStart("./")
    foreach ($exact in $IgnoreExact) {
        if ($norm -eq $exact -or $norm.StartsWith("$exact/")) { return $true }
    }
    foreach ($prefix in $IgnorePrefixes) {
        if ($norm.StartsWith($prefix)) { return $true }
    }
    foreach ($glob in $IgnoreGlobs) {
        if ($norm -like $glob) { return $true }
    }
    return $false
}

function Invoke-SafeGit([string[]]$GitArgs) {
    & git -C $Root @GitArgs 2>&1
    return $LASTEXITCODE
}

function Ensure-GitRepo {
    $repoUrl = Get-DotEnvValue "GITHUB_REPO_URL"
    if (-not $repoUrl) { $repoUrl = "https://github.com/capsproj2026-creator/capstone-project2026.git" }
    $branch = Get-DotEnvValue "GITHUB_BRANCH"
    if (-not $branch) { $branch = "main" }

    if (-not (Test-Path (Join-Path $Root ".git"))) {
        Write-Host "Initializing git and linking origin..." -ForegroundColor Cyan
        Invoke-SafeGit @("init") | Out-Null
        Invoke-SafeGit @("remote", "remove", "origin") | Out-Null
        Invoke-SafeGit @("remote", "add", "origin", $repoUrl) | Out-Null
        Invoke-SafeGit @("fetch", "origin", $branch) | Out-Null
        Invoke-SafeGit @("checkout", "-B", $branch, "origin/$branch") | Out-Null
    }
    else {
        $existing = (& git -C $Root remote get-url origin 2>$null)
        if (-not $existing) {
            Invoke-SafeGit @("remote", "add", "origin", $repoUrl) | Out-Null
        }
    }
    return $branch
}

function Sync-Pull([string]$Branch) {
    Write-Host "  Pulling origin/$Branch..." -ForegroundColor DarkGray
    & powershell -ExecutionPolicy Bypass -File $syncScript -SkipDb -SkipDeps -Quiet
}

function Sync-Push([string]$Branch) {
    $porcelain = & git -C $Root status --porcelain 2>&1
    if (-not $porcelain) {
        Write-Host "  No local changes to push." -ForegroundColor DarkGray
        return
    }

    $toAdd = @()
    foreach ($line in ($porcelain -split "`n")) {
        $line = $line.TrimEnd()
        if (-not $line) { continue }
        $path = $line.Substring(3).Trim().Trim('"')
        if ($path -match " -> ") { $path = ($path -split " -> ")[-1].Trim().Trim('"') }
        if (Test-IgnoredPath $path) { continue }
        $toAdd += $path
    }

    if ($toAdd.Count -eq 0) {
        Write-Host "  Only ignored/runtime files changed - skipping commit." -ForegroundColor DarkGray
        return
    }

    Write-Host ("  Staging {0} path(s)..." -f $toAdd.Count) -ForegroundColor Cyan
    foreach ($path in $toAdd) {
        Invoke-SafeGit @("add", "--", $path) | Out-Null
    }

    $staged = & git -C $Root diff --cached --name-only 2>&1
    if (-not $staged) {
        Write-Host "  Nothing staged after filters." -ForegroundColor DarkGray
        return
    }

    $stamp = Get-Date -Format "yyyy-MM-dd HH:mm"
    $msg = "chore: auto-sync local changes ($stamp)"
    $commitOut = & git -C $Root -c user.email=capstone-sync@local -c user.name="Capstone Sync" commit -m $msg 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Warning ("Commit skipped/failed: " + ($commitOut | Out-String).Trim())
        return
    }

    Write-Host "  Pushing to origin/$Branch..." -ForegroundColor Cyan
    $pushOut = & git -C $Root push origin $Branch 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Warning ("Push failed: " + ($pushOut | Out-String).Trim())
        return
    }
    $hash = (& git -C $Root rev-parse --short HEAD).Trim()
    Write-Host "  Pushed $hash" -ForegroundColor Green
}

Write-Host "=== GitHub auto-sync (pull + push) ===" -ForegroundColor Green
Write-Host "Repo:  $Root" -ForegroundColor DarkGray
Write-Host "Every: $IntervalSeconds seconds" -ForegroundColor DarkGray
Write-Host "Press Ctrl+C to stop." -ForegroundColor DarkGray
Write-Host ""

$branch = Ensure-GitRepo

while ($true) {
    $stamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Write-Host "[$stamp] Sync cycle..." -ForegroundColor Cyan
    try {
        Sync-Pull -Branch $branch
        Sync-Push -Branch $branch
    }
    catch {
        Write-Warning $_.Exception.Message
    }
    Start-Sleep -Seconds $IntervalSeconds
}
