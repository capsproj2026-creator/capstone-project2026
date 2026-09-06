# Cursor sessionStart: fast-forward pull from origin (never force).
$ErrorActionPreference = 'Continue'
$inputJson = [Console]::In.ReadToEnd()

try {
    if (Test-Path .git) {
        $branch = (git rev-parse --abbrev-ref HEAD 2>$null).Trim()
        if ($branch) {
            git fetch origin $branch 2>$null | Out-Null
            git pull --ff-only origin $branch 2>$null | Out-Null
        }
    }
} catch {
    # fail open
}

Write-Output '{}'
exit 0
