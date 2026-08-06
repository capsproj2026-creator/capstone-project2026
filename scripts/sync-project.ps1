#Requires -Version 5.1
<#
.SYNOPSIS
  Pull latest GitHub code and refresh dependencies.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\sync-project.ps1
#>
param(
    [switch]$SkipGit,
    [switch]$SkipDb,
    [switch]$SkipDeps,
    [switch]$Quiet
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot

$env:Path = [System.Environment]::GetEnvironmentVariable("Path", "Machine") + ";" +
    [System.Environment]::GetEnvironmentVariable("Path", "User")
if (Test-Path "C:\Program Files\nodejs") {
    $env:Path = "C:\Program Files\nodejs;$env:Path"
}

function Get-DotEnvValue {
    param([string]$Key)
    $envFile = Join-Path $Root ".env"
    if (-not (Test-Path $envFile)) { return "" }
    foreach ($line in Get-Content $envFile) {
        if ($line -match "^\s*$([regex]::Escape($Key))\s*=\s*(.*)$") {
            return $Matches[1].Trim().Trim('"').Trim("'")
        }
    }
    return ""
}

function Invoke-Git {
    param(
        [Parameter(Mandatory = $true)][string[]]$Args,
        [switch]$AllowFailure
    )
    $prev = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
    try {
        $output = & git -c user.email=capstone-sync@local -c user.name="Capstone Sync" @Args 2>&1
        $exit = $LASTEXITCODE
        if ($exit -ne 0 -and -not $AllowFailure) {
            throw ($output | Out-String).Trim()
        }
        return @{ Exit = $exit; Out = ($output | Out-String).Trim() }
    }
    finally {
        $ErrorActionPreference = $prev
    }
}

function Sync-GitRepo {
    $repoUrl = Get-DotEnvValue "GITHUB_REPO_URL"
    if (-not $repoUrl) { $repoUrl = "https://github.com/capsproj2026-creator/capstone-project2026.git" }
    $branch = Get-DotEnvValue "GITHUB_BRANCH"
    if (-not $branch) { $branch = "main" }

    if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
        Write-Warning "git not found - skipping git pull."
        return $false
    }

    Push-Location $Root
    $envBackup = Join-Path $env:TEMP "capstone-vms-env-backup"
    $pulled = $false

    try {
        if (Test-Path ".env") { Copy-Item ".env" $envBackup -Force }

        if (-not (Test-Path ".git")) {
            if (-not $Quiet) { Write-Host "Linking to GitHub..." -ForegroundColor Cyan }
            Invoke-Git @("init") | Out-Null
            Invoke-Git @("remote", "add", "origin", $repoUrl) -AllowFailure | Out-Null
            if ($LASTEXITCODE -ne 0) { Invoke-Git @("remote", "set-url", "origin", $repoUrl) | Out-Null }
            Invoke-Git @("fetch", "origin", $branch) | Out-Null
            Invoke-Git @("checkout", "-B", $branch, "origin/$branch") | Out-Null
            $pulled = $true
        }
        else {
            if (-not $Quiet) { Write-Host "Pulling latest code ($branch)..." -ForegroundColor Cyan }
            $status = Invoke-Git @("status", "--porcelain")
            $hadLocal = [bool]$status.Out
            if ($hadLocal) {
                if (-not $Quiet) { Write-Host "Stashing local edits..." -ForegroundColor DarkGray }
                Invoke-Git @("stash", "push", "-u", "-m", "capstone-auto-sync") | Out-Null
            }

            Invoke-Git @("fetch", "origin", $branch) | Out-Null
            $behind = Invoke-Git @("rev-list", "--count", "HEAD..origin/$branch")
            $behindCount = 0
            [void][int]::TryParse(($behind.Out -split "`n" | Select-Object -First 1), [ref]$behindCount)

            if ($behindCount -gt 0) {
                if (-not $Quiet) { Write-Host "  $behindCount new commit(s) - updating..." -ForegroundColor Cyan }
                Invoke-Git @("reset", "--hard", "origin/$branch") | Out-Null
                $pulled = $true
            }
            elseif (-not $Quiet) {
                Write-Host "  Already up to date." -ForegroundColor DarkGray
            }

            if ($hadLocal) {
                Invoke-Git @("stash", "pop") -AllowFailure | Out-Null
            }
        }

        if (Test-Path $envBackup) { Copy-Item $envBackup ".env" -Force }
        if (-not $Quiet) { Write-Host "Git sync done." -ForegroundColor Green }
    }
    catch {
        Write-Warning "Git sync failed: $($_.Exception.Message)"
        if (Test-Path $envBackup) { Copy-Item $envBackup ".env" -Force }
        return $false
    }
    finally {
        Pop-Location
    }

    return $pulled
}

function Sync-Dependencies {
    if (-not (Test-Path (Join-Path $Root "composer.json"))) { return }
    if (-not $Quiet) { Write-Host "Checking PHP dependencies..." -ForegroundColor Cyan }
    Push-Location $Root
    try {
        & composer install --no-interaction --prefer-dist
        if ($LASTEXITCODE -ne 0) { throw "composer install failed" }
    }
    finally { Pop-Location }

    if (-not (Get-Command npm -ErrorAction SilentlyContinue)) { return }
    if (-not (Test-Path (Join-Path $Root "package.json"))) { return }
    if (-not $Quiet) { Write-Host "Checking Node dependencies..." -ForegroundColor Cyan }
    Push-Location $Root
    try {
        & npm install
        if ($LASTEXITCODE -ne 0) { throw "npm install failed" }
    }
    finally { Pop-Location }
}

if (-not $Quiet) {
    Write-Host "=== Smart Campus VMS sync ===" -ForegroundColor Green
    Write-Host "Project: $Root" -ForegroundColor DarkGray
    Write-Host ""
}

$pulled = $false
if (-not $SkipGit) { $pulled = Sync-GitRepo }
if (-not $SkipDeps -or $pulled) { Sync-Dependencies }

Push-Location $Root
try {
    & php artisan config:clear | Out-Null
    if (-not $Quiet) { & php scripts/mongo_ping.php }
}
finally { Pop-Location }

if (-not $Quiet) { Write-Host "Sync complete." -ForegroundColor Green }
