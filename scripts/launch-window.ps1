#Requires -Version 5.1
param(
    [Parameter(Mandatory = $true)]
    [string]$Title,

    [Parameter(Mandatory = $true)]
    [string]$WorkingDirectory,

    # JSON array of command tokens, base64-encoded so powershell.exe -File
    # cannot strip quotes (e.g. ["php","-S","0.0.0.0:8000",...]).
    [Parameter(Mandatory = $true)]
    [string]$CommandLine
)

$ErrorActionPreference = "Continue"

try {
    $json = [System.Text.Encoding]::UTF8.GetString([Convert]::FromBase64String($CommandLine))
    # [string[]] unwraps the PS 5.1 ConvertFrom-Json nested-array quirk
    # (@(ConvertFrom-Json ...) yields Count=1 with the whole argv inside).
    $CommandArgs = [string[]](ConvertFrom-Json -InputObject $json)
} catch {
    Write-Host "Invalid -CommandLine payload." -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
}

if (-not $CommandArgs -or $CommandArgs.Count -eq 0) {
    Write-Error "No command provided."
    exit 1
}

$extraPaths = @(
    "$env:ProgramFiles\nodejs",
    "${env:ProgramFiles(x86)}\nodejs",
    "$env:LOCALAPPDATA\Programs\nodejs",
    "$env:APPDATA\npm",
    "$env:ProgramFiles\PHP",
    "${env:ProgramFiles(x86)}\PHP",
    "C:\xampp\php",
    # artisan/sebastian Version.php runs `git describe` via proc_open
    "$env:ProgramFiles\Git\cmd",
    "$env:ProgramFiles\Git\bin",
    "$env:LOCALAPPDATA\Programs\Git\cmd",
    "$env:LOCALAPPDATA\GitHubDesktop\bin"
)
$wingetPhp = Get-ChildItem -Path "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Filter "PHP.*" -Directory -ErrorAction SilentlyContinue |
    ForEach-Object { $_.FullName }
$extraPaths += $wingetPhp
foreach ($dir in $extraPaths) {
    if ($dir -and (Test-Path -LiteralPath $dir) -and ($env:Path -notlike "*$dir*")) {
        $env:Path = "$dir;$env:Path"
    }
}

$phpCmd = Get-Command php -ErrorAction SilentlyContinue
if ($phpCmd) {
    $env:Path = "$(Split-Path -Parent $phpCmd.Source);$env:Path"
}

Set-Location -LiteralPath $WorkingDirectory
Write-Host ("=== $Title ===") -ForegroundColor Cyan

$envFile = Join-Path $WorkingDirectory ".env"
$cmdLine = ($CommandArgs | ForEach-Object { [string]$_ }) -join " "
if ((Test-Path $envFile) -and ($cmdLine -match "artisan serve")) {
    $workers = $null
    foreach ($line in Get-Content $envFile) {
        if ($line -match '^\s*#?\s*PHP_CLI_SERVER_WORKERS\s*=\s*(\d+)') {
            $workers = $Matches[1]
            break
        }
    }
    if (-not $workers -or [int]$workers -lt 1) {
        $workers = "4"
    }
    $env:PHP_CLI_SERVER_WORKERS = $workers
    Write-Host "PHP_CLI_SERVER_WORKERS=$workers (parallel browser + AI requests)" -ForegroundColor DarkGray
    # PHP only honors PHP_CLI_SERVER_WORKERS when --no-reload is set.
    if ($cmdLine -notmatch "--no-reload") {
        $CommandArgs = @($CommandArgs) + @("--no-reload")
        $cmdLine = ($CommandArgs | ForEach-Object { [string]$_ }) -join " "
        Write-Host "Added --no-reload so worker pool is active" -ForegroundColor DarkGray
    }
}

$exe = [string]$CommandArgs[0]
$params = @()
if ($CommandArgs.Count -gt 1) {
    $params = @($CommandArgs[1..($CommandArgs.Count - 1)] | ForEach-Object { [string]$_ })
}

if ($exe -eq "npm" -and (Test-Path -LiteralPath "$env:ProgramFiles\nodejs\npm.cmd")) {
    $exe = "$env:ProgramFiles\nodejs\npm.cmd"
}
if ($exe -eq "php") {
    $php = Get-Command php -ErrorAction SilentlyContinue
    if ($php) { $exe = $php.Source }
    else {
        Write-Host "php not found on PATH. Install PHP 8.2+ or add it to PATH." -ForegroundColor Red
        exit 1
    }
}
if ($exe -eq "powershell" -or $exe -eq "powershell.exe") {
    $psExe = Join-Path $env:SystemRoot "System32\WindowsPowerShell\v1.0\powershell.exe"
    if (Test-Path -LiteralPath $psExe) {
        $exe = $psExe
    } else {
        $ps = Get-Command powershell -ErrorAction SilentlyContinue
        if ($ps) { $exe = $ps.Source }
        else {
            Write-Host "powershell.exe not found." -ForegroundColor Red
            exit 1
        }
    }
}
if ($exe -eq "pwsh" -or $exe -eq "pwsh.exe") {
    $pwsh = Get-Command pwsh -ErrorAction SilentlyContinue
    if ($pwsh) { $exe = $pwsh.Source }
}

Write-Host ("> " + $cmdLine) -ForegroundColor DarkGray
& $exe @params
if ($null -ne $LASTEXITCODE -and $LASTEXITCODE -ne 0) {
    Write-Host ("Command exited with code " + $LASTEXITCODE) -ForegroundColor Red
}
