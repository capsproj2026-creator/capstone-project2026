#Requires -Version 5.1
<#
.SYNOPSIS
  Configure MongoDB Atlas in .env and test the Laravel connection.

.EXAMPLE
  .\scripts\setup-atlas-mongo.ps1 -User capstone_user -Password "yourPass" -Host cluster0.xxxxx.mongodb.net
#>
param(
    [string]$User,
    [string]$Password,
    [string]$ClusterHost,
    [string]$Database = "capstone"
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$EnvFile = Join-Path $Root ".env"

if (-not (Test-Path $EnvFile)) {
    Write-Error "Missing .env - copy .env.example first."
}

if (-not $User) {
    $User = Read-Host "Atlas database username"
}
if (-not $Password) {
    $secure = Read-Host "Atlas database password" -AsSecureString
    $Password = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
        [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    )
}
if (-not $ClusterHost) {
    Write-Host "From Atlas: Database -> Connect -> Drivers -> copy host (e.g. cluster0.xxxxx.mongodb.net)" -ForegroundColor DarkGray
    $ClusterHost = Read-Host "Atlas cluster host"
}

$ClusterHost = $ClusterHost.Trim().Trim("/")
$ClusterHost = $ClusterHost -replace '^mongodb(\+srv)?://', ''

function Set-EnvKey([string[]]$Lines, [string]$Key, [string]$Value) {
    $pattern = "^\s*$([regex]::Escape($Key))="
    $newLine = "$Key=$Value"
    $found = $false
    $out = @()
    foreach ($line in $Lines) {
        if ($line -match $pattern) {
            $out += $newLine
            $found = $true
        } else {
            $out += $line
        }
    }
    if (-not $found) { $out += $newLine }
    return ,$out
}

$lines = Get-Content $EnvFile
$lines = (Set-EnvKey $lines "DB_CONNECTION" "mongodb")[0]
$lines = (Set-EnvKey $lines "MONGODB_MODE" "atlas")[0]
$lines = (Set-EnvKey $lines "MONGODB_ATLAS_USER" $User)[0]
$lines = (Set-EnvKey $lines "MONGODB_ATLAS_PASSWORD" $Password)[0]
$lines = (Set-EnvKey $lines "MONGODB_ATLAS_HOST" $ClusterHost)[0]
$lines = (Set-EnvKey $lines "MONGODB_DATABASE" $Database)[0]
$lines = (Set-EnvKey $lines "MONGODB_AUTH_DATABASE" "admin")[0]
$lines = (Set-EnvKey $lines "MONGODB_TLS_ALLOW_INVALID" "true")[0]
$lines = (Set-EnvKey $lines "MONGODB_URI" "")[0]
Set-Content -Path $EnvFile -Value $lines -Encoding UTF8

Write-Host "Atlas credentials saved to .env (MONGODB_MODE=atlas)" -ForegroundColor Green
Write-Host "Testing connection..." -ForegroundColor Cyan

Set-Location $Root
php artisan config:clear | Out-Null
php scripts/mongo_ping.php
if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "Connection failed. Checklist:" -ForegroundColor Yellow
    Write-Host "  1. Atlas -> Network Access -> add your IP (or 0.0.0.0/0 for dev)"
    Write-Host "  2. Database Access user/password match what you entered"
    Write-Host "  3. Host is like cluster0.xxxxx.mongodb.net (from Connect -> Drivers)"
    exit 1
}

php artisan capstone:db-status
Write-Host ""
Write-Host "Done. Laravel is using MongoDB Atlas database '$Database'." -ForegroundColor Green
