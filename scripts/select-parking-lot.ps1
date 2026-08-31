# Switch active parking lot for single-camera calibration (see hardware/ai_parking/CALIBRATION_ROLLOUT.md)
param(
    [Parameter(Position = 0)]
    [ValidateSet('acad1', 'duran', 'auditorium', 'list')]
    [string] $Lot = 'list'
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location (Join-Path $root 'hardware\ai_parking')

if ($Lot -eq 'list') {
    python select_lot.py --list
} else {
    python select_lot.py $Lot
}
