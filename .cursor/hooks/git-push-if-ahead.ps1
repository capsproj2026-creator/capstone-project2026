# Cursor stop: pull, commit safe local changes, push if ahead.
$ErrorActionPreference = 'Continue'
$inputJson = [Console]::In.ReadToEnd()

try {
    if (Test-Path .git) {
        $syncer = Join-Path (Get-Location) 'scripts\auto-sync-github.ps1'
        if (Test-Path $syncer) {
            & powershell -NoProfile -ExecutionPolicy Bypass -File $syncer -Once 2>$null | Out-Null
        } else {
            $branch = (git rev-parse --abbrev-ref HEAD 2>$null).Trim()
            if ($branch) {
                git pull --ff-only origin $branch 2>$null | Out-Null
                $ahead = 0
                try { $ahead = [int](git rev-list --count "origin/$branch..HEAD" 2>$null) } catch { $ahead = 0 }
                if ($ahead -gt 0) {
                    git push origin HEAD 2>$null | Out-Null
                }
            }
        }
    }
} catch {
    # fail open
}

Write-Output '{}'
exit 0
