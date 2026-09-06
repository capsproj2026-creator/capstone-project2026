# Background GitHub sync watcher for demos / local machines.
# Pulls remote updates on an interval. Does NOT auto-commit secrets.
# Usage: powershell -ExecutionPolicy Bypass -File .\scripts\auto-sync-github.ps1

param(
    [int]$IntervalSeconds = 120
)

$ErrorActionPreference = 'Continue'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

Write-Host "Auto-sync watching $root every ${IntervalSeconds}s (Ctrl+C to stop)." -ForegroundColor Green

while ($true) {
    try {
        $branch = (git rev-parse --abbrev-ref HEAD).Trim()
        git fetch origin $branch 2>$null | Out-Null
        $behind = [int](git rev-list --count "HEAD..origin/$branch" 2>$null)
        $ahead = [int](git rev-list --count "origin/$branch..HEAD" 2>$null)

        if ($behind -gt 0) {
            Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Pulling $behind commit(s)..." -ForegroundColor Cyan
            git pull --ff-only origin $branch 2>&1 | Out-Host
        }

        if ($ahead -gt 0) {
            $dirty = git status --porcelain
            if (-not $dirty) {
                Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Pushing $ahead commit(s)..." -ForegroundColor Cyan
                git push origin HEAD 2>&1 | Out-Host
            } else {
                Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Ahead by $ahead but working tree dirty — skip push until committed." -ForegroundColor Yellow
            }
        }
    } catch {
        Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Sync error: $_" -ForegroundColor Red
    }

    Start-Sleep -Seconds $IntervalSeconds
}
