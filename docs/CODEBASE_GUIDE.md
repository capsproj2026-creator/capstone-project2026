# ISCVMS Codebase Guide

## Architecture

Laravel 12 web app + MongoDB + Reverb realtime + optional Python AI parking (YOLOv9/EasyOCR) + ESP32 RFID gates.

```
Browser / Guard UI ──► LAN :8000 (lan_front_router) ──► Laravel :8001
ESP32 RFID ──────────► LAN :8000 /api/rfid/* ──────────► Laravel
Python AI :8090 ─────► POST /api/ai-parking/* ──────────► Laravel
Laravel / Browser ───► Reverb :8080 (gate.scans, ai.parking)
Cameras RTSP ────────► Python AI (detect + OCR + MJPEG)
```

## Important directories

| Path | Role |
|------|------|
| `app/Http/Controllers` | Admin / Guard / User / Api / Auth |
| `app/Services` | Occupancy, violations, RFID gate logic, health, sync |
| `app/Models` | Mongo Eloquent models |
| `routes/web.php`, `routes/api.php` | HTTP routes |
| `hardware/ai_parking` | YOLO/OCR service |
| `hardware/esp32_rfid_gate*` | Gate firmware |
| `scripts/` | Start/stop/status/backup/setup |
| `docs/` | Turnover documentation |
| `storage/` | Logs, uploads, backups (gitignored) |

## RFID flow

Reader → ESP32 → `POST /api/rfid/scan` (`X-RFID-TOKEN`) → validate UID → user/vehicle/visitor lookup → grant/deny → `GateLog` → boom via heartbeat `open` command → Echo `gate.scans`.

## AI parking flow

Camera RTSP → Python frame loop → YOLO boxes → track/session → zone occupancy → parked gate → plate OCR → plate-lookup API → Laravel occupancy POST → Latest Detections / violations → Guard AI monitor poll + Reverb.

## Safe development practices

- Prefer `.env` over hardcoding; never commit `.env` or `rfid_gate_config.h`
- Do not run `migrate:fresh` on turnover/production data
- After changing `.env`, `php artisan config:clear` (or let start script `config:cache`)
- Test with `.\scripts\status-system.ps1` and Admin → System Health
