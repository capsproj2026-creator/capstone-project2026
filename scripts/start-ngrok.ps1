#Requires -Version 5.1
<#
.SYNOPSIS
  Start ngrok for Laravel on port 8000 using the project binary.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\start-ngrok.ps1
#>
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$NgrokExe = Join-Path $Root "tools\ngrok\ngrok.exe"

if (-not (Test-Path -LiteralPath $NgrokExe)) {
    Write-Host "ngrok not found. Run: powershell -ExecutionPolicy Bypass -File .\scripts\install-ngrok.ps1" -ForegroundColor Red
    exit 1
}

Write-Host "Starting ngrok -> http://127.0.0.1:8000" -ForegroundColor Cyan
Write-Host "Dashboard: http://127.0.0.1:4040" -ForegroundColor DarkGray
Write-Host ""

& $NgrokExe http 8000
$code = $LASTEXITCODE
if ($code -ne 0) {
    Write-Host ""
    Write-Host "If you see ERR_NGROK_334, your free dev domain is already in use elsewhere." -ForegroundColor Yellow
    Write-Host "1. Open https://dashboard.ngrok.com/endpoints and stop the active tunnel" -ForegroundColor White
    Write-Host "2. Or open https://dashboard.ngrok.com/agents and disconnect other agents" -ForegroundColor White
    Write-Host "3. Run this script again" -ForegroundColor White
    Write-Host ""
    Write-Host "After ngrok is online, set APP_URL in .env to the public HTTPS URL," -ForegroundColor DarkGray
    Write-Host "then run: php artisan config:clear" -ForegroundColor DarkGray
}
exit $code
