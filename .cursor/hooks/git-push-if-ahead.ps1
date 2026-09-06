# Cursor stop: push local commits if ahead of origin and tree is clean.
$ErrorActionPreference = 'Continue'
$inputJson = [Console]::In.ReadToEnd()

try {
    if (Test-Path .git) {
        $branch = (git rev-parse --abbrev-ref HEAD 2>$null).Trim()
        $dirty = git status --porcelain 2>$null
        if ($branch -and -not $dirty) {
            git fetch origin $branch 2>$null | Out-Null
            $ahead = 0
            try { $ahead = [int](git rev-list --count "origin/$branch..HEAD" 2>$null) } catch { $ahead = 0 }
            if ($ahead -gt 0) {
                git push origin HEAD 2>$null | Out-Null
            }
        }
    }
} catch {
    # fail open
}

Write-Output '{}'
exit 0
