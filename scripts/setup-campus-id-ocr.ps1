#Requires -Version 5.1
<#
.SYNOPSIS
  Create the project OCR virtualenv and install campus ID scan dependencies.
#>
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$VenvDir = Join-Path $Root ".venv-campus-id-ocr"
$Python = Join-Path $VenvDir "Scripts\python.exe"

function Get-BasePython {
    foreach ($candidate in @("C:\Python312\python.exe", "py -3", "python")) {
        try {
            if ($candidate -eq "py -3") {
                & py -3 -c "import sys; print(sys.executable)" | Out-Null
            } elseif (Test-Path -LiteralPath $candidate) {
                & $candidate -c "import sys; print(sys.executable)" | Out-Null
            } else {
                continue
            }
            return $candidate
        } catch {
            continue
        }
    }

    throw "Python 3 not found. Install from https://www.python.org/downloads/ then re-run this script."
}

$basePython = Get-BasePython
Write-Host "Using base Python: $basePython" -ForegroundColor Cyan

if (-not (Test-Path -LiteralPath $Python)) {
    Write-Host "Creating virtualenv at $VenvDir ..." -ForegroundColor Yellow
    if ($basePython -eq "py -3") {
        & py -3 -m venv $VenvDir
    } else {
        & $basePython -m venv $VenvDir
    }
}

Write-Host "Installing campus ID OCR packages (RapidOCR, no PyTorch)..." -ForegroundColor Yellow
& $Python -m pip install --upgrade pip
& $Python -m pip install opencv-python-headless rapidocr-onnxruntime

Write-Host "Verifying OCR imports..." -ForegroundColor Yellow
& $Python -c "import cv2; from rapidocr_onnxruntime import RapidOCR; print('Campus ID OCR ready in .venv-campus-id-ocr')"

Write-Host "Done. Restart Laravel if it is already running." -ForegroundColor Green
