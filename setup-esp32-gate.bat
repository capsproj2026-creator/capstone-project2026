@echo off
title ESP32 Gate Setup
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\setup-esp32-gate.ps1" -Hotspot
pause
