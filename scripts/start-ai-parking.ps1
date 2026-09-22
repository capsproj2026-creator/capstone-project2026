#Requires -Version 5.1
<#
.SYNOPSIS
  Start EVERYTHING: website (Laravel + MongoDB) + Reverb + Vite + YOLOv9 AI parking.

.DESCRIPTION
  One command for the full Smart Campus VMS demo.
  Opens Laravel/Reverb/Vite/AI in Windows Terminal tabs when available
  (falls back to separate PowerShell windows). Use -SeparateWindows for the old layout.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\start-ai-parking.ps1
#>
param(
    [switch]$SkipWebStack,
    [switch]$SkipNgrok,
    [switch]$SeparateWindows
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$EnvFile = Join-Path $Root ".env"
$AiDir = Join-Path $Root "hardware\ai_parking"
$ScriptsDir = $PSScriptRoot

if (-not (Test-Path $EnvFile)) {
    Write-Error "Missing .env at $EnvFile - copy .env.example and configure values first."
}

function Import-DotEnv([string]$Path) {
    # Never clobber process PATH / system vars from .env keys.
    $blocked = @{
        'PATH' = $true; 'PATHEXT' = $true; 'SYSTEMROOT' = $true
        'WINDIR' = $true; 'COMSPEC' = $true; 'PSMODULEPATH' = $true
    }
    Get-Content $Path | ForEach-Object {
        $line = $_.Trim()
        if (-not $line -or $line.StartsWith("#") -or -not $line.Contains("=")) { return }
        $parts = $line.Split("=", 2)
        $key = $parts[0].Trim()
        $value = $parts[1].Trim().Trim('"').Trim("'")
        if (-not $key) { return }
        if ($blocked.ContainsKey($key.ToUpperInvariant())) { return }
        [Environment]::SetEnvironmentVariable($key, $value, "Process")
    }
}

function Get-NetstatExe {
    $candidate = Join-Path $env:SystemRoot "System32\netstat.exe"
    if (Test-Path -LiteralPath $candidate) { return $candidate }
    $cmd = Get-Command netstat -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    return $null
}

function Test-HttpOk([string]$Url) {
    try {
        # Local Laravel + Mongo can take several seconds when busy; 4s false-fails often.
        $r = Invoke-WebRequest -Uri $Url -TimeoutSec 12 -UseBasicParsing
        return ($r.StatusCode -ge 200 -and $r.StatusCode -lt 500)
    } catch {
        return $false
    }
}

function Stop-PortListeners([int]$Port) {
    $netstat = Get-NetstatExe
    if (-not $netstat) { return }
    $lines = & $netstat -ano 2>$null | Select-String ":$Port\s+.*LISTENING"
    $procIds = @()
    foreach ($line in $lines) {
        $procId = ($line.ToString() -split '\s+')[-1]
        if ($procId -match '^\d+$') { $procIds += [int]$procId }
    }
    foreach ($procId in ($procIds | Select-Object -Unique)) {
        Write-Host ("Stopping previous process on port {0} (PID {1})" -f $Port, $procId) -ForegroundColor Yellow
        Stop-Process -Id $procId -Force -ErrorAction SilentlyContinue
    }
    if ($procIds.Count -gt 0) { Start-Sleep -Seconds 2 }
}

function Test-PortListening([int]$Port) {
    $netstat = Get-NetstatExe
    if (-not $netstat) { return $false }
    $hit = & $netstat -ano 2>$null | Select-String (":{0}\s+.*LISTENING" -f $Port)
    return [bool]$hit
}

function Resolve-PythonExe {
    $candidates = @(
        (Join-Path $AiDir ".venv\Scripts\python.exe"),
        (Join-Path $Root ".venv\Scripts\python.exe"),
        "C:\Python312\python.exe",
        "C:\Python311\python.exe",
        "C:\Python310\python.exe",
        "$env:LOCALAPPDATA\Programs\Python\Python312\python.exe",
        "$env:LOCALAPPDATA\Programs\Python\Python311\python.exe",
        "$env:LOCALAPPDATA\Programs\Python\Python310\python.exe"
    )
    foreach ($c in $candidates) {
        if ($c -and (Test-Path -LiteralPath $c)) { return $c }
    }
    foreach ($name in @("python", "python3", "py")) {
        $cmd = Get-Command $name -ErrorAction SilentlyContinue
        if ($cmd -and $cmd.Source -and ($cmd.Source -notlike "*\WindowsApps\*")) {
            return $cmd.Source
        }
    }
    $py = Get-Command py -ErrorAction SilentlyContinue
    if ($py) { return $py.Source }
    return $null
}

Import-DotEnv $EnvFile

# Ensure System32/php/git/python are visible in stripped Windows Terminal tab PATH.
foreach ($dir in @(
    "$env:SystemRoot\System32",
    "C:\xampp\php",
    "C:\Python312",
    "C:\Python312\Scripts",
    "C:\Python311",
    "C:\Python311\Scripts",
    "$env:LOCALAPPDATA\Programs\Python\Python312",
    "$env:LOCALAPPDATA\Programs\Python\Python312\Scripts",
    "$env:ProgramFiles\Git\cmd",
    "$env:ProgramFiles\Git\bin",
    "$env:LOCALAPPDATA\Programs\Git\cmd",
    "$env:LOCALAPPDATA\GitHubDesktop\bin"
)) {
    if ($dir -and (Test-Path -LiteralPath $dir) -and ($env:Path -notlike "*$dir*")) {
        $env:Path = "$dir;$env:Path"
    }
}

$PythonExe = Resolve-PythonExe
if (-not $PythonExe) {
    Write-Host "Python not found. Install Python 3.12+ or add it to PATH." -ForegroundColor Red
    Write-Host "  Expected: C:\Python312\python.exe" -ForegroundColor DarkYellow
    exit 1
}

# Occupancy ingest must hit local Laravel. APP_URL / AI_LARAVEL_API_BASE are often
# :8000 (LAN front) or a public ngrok host — use :8001 directly for speed/reliability.
if (
    -not $env:AI_LARAVEL_API_BASE -or
    $env:AI_LARAVEL_API_BASE -match 'ngrok' -or
    $env:AI_LARAVEL_API_BASE -match ':8000/?$'
) {
    $env:AI_LARAVEL_API_BASE = "http://127.0.0.1:8001"
}

$laravelUrl = $env:AI_LARAVEL_API_BASE

Write-Host "========================================" -ForegroundColor Green
Write-Host " Smart Campus VMS - start all" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""

Set-Location $Root
Write-Host "Checking MongoDB (capstone)..." -ForegroundColor Cyan
# Native php stderr (e.g. missing git for sebastian/version) must not abort under Stop.
$prevEap = $ErrorActionPreference
$ErrorActionPreference = "Continue"
& php artisan config:clear *> $null
& php scripts/mongo_ping.php *> $null
$mongoExit = $LASTEXITCODE
$ErrorActionPreference = $prevEap
if ($mongoExit -ne 0) {
    Write-Host ""
    Write-Host "MongoDB is not connected." -ForegroundColor Red
    Write-Host "  Local .env:" -ForegroundColor Yellow
    Write-Host "    MONGODB_MODE=local" -ForegroundColor Yellow
    Write-Host "    MONGODB_URI=mongodb://127.0.0.1:27017" -ForegroundColor Yellow
    Write-Host "  Atlas:  .\scripts\setup-atlas-mongo.ps1" -ForegroundColor Yellow
    exit 1
}
Write-Host "  MongoDB: OK" -ForegroundColor Green

function Wait-LaravelReady([string]$Url, [int]$Seconds = 45) {
    for ($i = 0; $i -lt $Seconds; $i++) {
        if (Test-HttpOk $Url) { return $true }
        Start-Sleep -Seconds 1
    }
    return $false
}

if (-not $SkipWebStack) {
    # Port open but HTTP hung (common after many restarts) - recycle Laravel first.
    if ((Test-PortListening 8000) -and -not (Test-HttpOk $laravelUrl)) {
        Write-Host "Port 8000 is open but not responding - restarting front + Laravel..." -ForegroundColor Yellow
        Stop-PortListeners 8000
        Stop-PortListeners 8001
    }
    if (-not (Test-HttpOk $laravelUrl)) {
        Write-Host ""
        Write-Host "Starting website stack (LAN front + Laravel + Reverb + Vite)..." -ForegroundColor Cyan
        $sysArgs = @("-SkipAi", "-SkipMongoCheck")
        if ($SkipNgrok) { $sysArgs += "-SkipNgrok" }
        if ($SeparateWindows) { $sysArgs += "-SeparateWindows" }
        if (Test-Path (Join-Path $Root "public\build\manifest.json")) {
            $sysArgs += "-SkipVite"
        }
        & powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $ScriptsDir "start-system.ps1") @sysArgs
        if ($LASTEXITCODE -ne 0) {
            Write-Host ""
            Write-Host "Website stack did not start (see messages above)." -ForegroundColor Red
            exit 1
        }
        Write-Host "Waiting for Laravel..." -ForegroundColor DarkGray
        if (-not (Wait-LaravelReady -Url $laravelUrl -Seconds 45)) {
            Write-Host "Laravel did not respond at $laravelUrl - check the Laravel window for errors." -ForegroundColor Red
            Write-Host "Tip: close old Laravel/LAN windows, then run: .\scripts\start-system.ps1" -ForegroundColor DarkYellow
            exit 1
        }
    }
    Write-Host "  Laravel: OK ($laravelUrl)" -ForegroundColor Green
} else {
    # -SkipWebStack (from start-system WT tab): Laravel/LAN are sibling tabs.
    # Wait for them — do NOT Start-Process separate PowerShell windows (that
    # put Laravel/LAN "outside" the Windows Terminal).
    if ((Test-PortListening 8000) -and -not (Test-HttpOk $laravelUrl)) {
        Write-Host "Laravel port is open but not responding - waiting for recycle..." -ForegroundColor Yellow
    }
    if (-not (Test-HttpOk $laravelUrl)) {
        Write-Host "Waiting for Laravel tab ($laravelUrl)..." -ForegroundColor DarkGray
        if (-not (Wait-LaravelReady -Url $laravelUrl -Seconds 50)) {
            Write-Host "Laravel did not respond at $laravelUrl" -ForegroundColor Red
            Write-Host "  Check the Laravel / LAN Front tabs in this Windows Terminal window." -ForegroundColor DarkYellow
            Write-Host "  Or re-run: .\scripts\start-system.ps1" -ForegroundColor DarkYellow
            exit 1
        }
    }
    Write-Host "  Laravel: OK ($laravelUrl)" -ForegroundColor Green
}

Write-Host ""

if (-not $env:AI_PARKING_API_TOKEN) {
    Write-Warning "AI_PARKING_API_TOKEN is empty - Laravel will reject occupancy posts."
}

if (-not $env:AI_CAMERA_2_USER -or -not $env:AI_CAMERA_2_PASS) {
    if ($env:AI_CAMERA_2_IP) {
        Write-Warning "AI_CAMERA_2_IP is set but USER/PASS are empty - Tapo RTSP will fail (401)."
    }
}

$modelName = if ($env:AI_PARKING_YOLO_MODEL) {
    $env:AI_PARKING_YOLO_MODEL.Trim().ToLower() -replace '\.pt$',''
} else {
    "yolov9c"
}
$model = Join-Path $AiDir "models\$modelName.pt"
if (-not (Test-Path $model)) {
    Write-Host ("Downloading YOLO model ({0})..." -f $modelName) -ForegroundColor Yellow
    Set-Location $AiDir
    & $PythonExe download_model.py --model $modelName
}

$plateModel = Join-Path $AiDir "models\plate.pt"
if ($env:AI_PARKING_OCR_ENABLED -eq "1" -and -not (Test-Path $plateModel)) {
    Write-Host "Downloading plate YOLO model (OCR enabled, plate.pt missing)..." -ForegroundColor Yellow
    Set-Location $AiDir
    & $PythonExe download_plate_model.py
}

$streamPort = if ($env:AI_STREAM_PORT) { [int]$env:AI_STREAM_PORT } else { 8090 }
Stop-PortListeners -Port $streamPort

Write-Host "Starting YOLOv9 AI parking (this window)..." -ForegroundColor Cyan
Write-Host ("  Website:  " + $laravelUrl) -ForegroundColor Yellow
Write-Host ("  Database: MongoDB capstone") -ForegroundColor Yellow
Write-Host ("  AI stream: http://127.0.0.1:" + $streamPort + "/stream.mjpg") -ForegroundColor Yellow
Write-Host ("  Model:    " + $modelName) -ForegroundColor DarkGray
Write-Host ("  Python:   " + $PythonExe) -ForegroundColor DarkGray
Write-Host ""
Write-Host "  Admin: admin@my.cspc.edu.ph / admin123" -ForegroundColor Cyan
Write-Host "  Guard: guard@my.cspc.edu.ph / password123" -ForegroundColor Cyan
Write-Host ""
Write-Host "Keep ALL PowerShell windows open while using the site." -ForegroundColor DarkGray
Write-Host ""

Set-Location $AiDir
& $PythonExe -u ai_parking_service.py
