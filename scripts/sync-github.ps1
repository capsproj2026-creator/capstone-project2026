# Sync local main with GitHub: pull, then push if ahead.
# Usage: powershell -ExecutionPolicy Bypass -File .\scripts\sync-github.ps1
# Watcher: powershell -ExecutionPolicy Bypass -File .\scripts\auto-sync-github.ps1

param(
    [switch]$PullOnly,
    [switch]$PushOnly
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

function Write-Step([string]$msg) {
    Write-Host "[git-sync] $msg" -ForegroundColor Cyan
}

if (-not (Test-Path .git)) {
    throw "Not a git repository: $root"
}

$branch = (git rev-parse --abbrev-ref HEAD).Trim()
if (-not $branch) { throw 'Could not determine branch.' }

if (-not $PushOnly) {
    Write-Step "Pulling origin/$branch (ff-only)..."
    git fetch origin $branch 2>&1 | Out-Host
    $pull = git pull --ff-only origin $branch 2>&1
    $pull | Out-Host
}

if ($PullOnly) {
    Write-Step 'Pull done.'
    exit 0
}

$status = git status --porcelain
if ($status) {
    Write-Step 'Working tree has local changes — commit them first (or leave them unstaged). Pushing commits only.'
}

Write-Step "Pushing $branch to origin..."
git push -u origin HEAD 2>&1 | Out-Host
Write-Step 'Done.'
