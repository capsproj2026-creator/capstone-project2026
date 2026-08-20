@echo off
:: Double-click to allow ESP32 / LAN devices to reach Laravel on port 8000 (requires Admin).
powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process powershell -Verb RunAs -ArgumentList '-NoProfile -ExecutionPolicy Bypass -File \"%~dp0scripts\allow-laravel-firewall.ps1\"'"
