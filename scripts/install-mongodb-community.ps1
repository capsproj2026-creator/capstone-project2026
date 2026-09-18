# Run this script as Administrator to install MongoDB Community Server 8.3.7
# Right-click PowerShell -> Run as administrator, then:
#   powershell -ExecutionPolicy Bypass -File .\scripts\install-mongodb-community.ps1

$ErrorActionPreference = "Stop"
$msi = Join-Path $env:TEMP "mongodb-windows-x86_64-8.3.7-signed.msi"
$log = Join-Path $env:TEMP "mongodb-install-admin.log"
$url = "https://fastdl.mongodb.org/windows/mongodb-windows-x86_64-8.3.7-signed.msi"

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
    [Security.Principal.WindowsBuiltInRole]::Administrator
)
if (-not $isAdmin) {
    Write-Host "ERROR: Open an Administrator PowerShell and re-run this script." -ForegroundColor Red
    exit 1
}

if (-not (Test-Path $msi) -or (Get-Item $msi).Length -lt 500MB) {
    Write-Host "Downloading MongoDB MSI..." -ForegroundColor Cyan
    curl.exe -L --retry 3 -o $msi $url
}

Write-Host "Installing MongoDB Community Server..." -ForegroundColor Cyan
$p = Start-Process -FilePath "msiexec.exe" -ArgumentList "/i `"$msi`" /qn /norestart ADDLOCAL=ALL SHOULD_INSTALL_COMPASS=`"0`" /L*v `"$log`"" -Wait -PassThru
Write-Host "msiexec exit=$($p.ExitCode)"

$svc = Get-Service -Name "MongoDB" -ErrorAction SilentlyContinue
if (-not $svc) {
    $svc = Get-Service | Where-Object { $_.Name -match "mongo" -or $_.DisplayName -match "MongoDB" } | Select-Object -First 1
}
if ($svc -and $svc.Status -ne "Running") {
    Start-Service -Name $svc.Name
}

Start-Sleep -Seconds 3
$ok = Test-NetConnection 127.0.0.1 -Port 27017 -WarningAction SilentlyContinue -InformationLevel Quiet
if ($ok) {
    Write-Host "MongoDB is listening on 127.0.0.1:27017" -ForegroundColor Green
    Write-Host "Next: cd to the project and run: php artisan db:seed" -ForegroundColor Yellow
    exit 0
}

Write-Host "Install may have failed. Check log: $log" -ForegroundColor Red
exit 1
