@echo off
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\sync-arduino-sketches.ps1" %*
