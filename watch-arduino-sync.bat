@echo off
title ESP32 Arduino auto-sync
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\sync-arduino-sketches.ps1" -Watch
