#Requires -Version 5.1
# Sends the local camera pictures to iscvms.com only when the SSH tunnel is down.
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$AiDir = Join-Path $Root "hardware\ai_parking"
$candidates = @(
    (Join-Path $AiDir ".venv\Scripts\python.exe"),
    (Join-Path $Root ".venv\Scripts\python.exe"),
    "C:\Python312\python.exe",
    "C:\Python311\python.exe"
)
$PythonExe = $null
foreach ($c in $candidates) {
    if (Test-Path -LiteralPath $c) { $PythonExe = $c; break }
}
if (-not $PythonExe) {
    $cmd = Get-Command python -ErrorAction SilentlyContinue
    if ($cmd -and $cmd.Source -notlike "*\WindowsApps\*") { $PythonExe = $cmd.Source }
}
if (-not $PythonExe) {
    Write-Host "Python not found. Camera uplink did not start." -ForegroundColor Red
    exit 1
}
Set-Location $AiDir
$ErrorActionPreference = "Continue"
Write-Host "Camera uplink is running. Leave this window open." -ForegroundColor Cyan
while ($true) {
    & $PythonExe -u (Join-Path $AiDir "camera_uplink.py")
    Write-Host "Camera uplink stopped (exit $LASTEXITCODE). Restarting in 5 seconds..." -ForegroundColor Yellow
    Start-Sleep -Seconds 5
}
