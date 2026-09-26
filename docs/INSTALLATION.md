# ISCVMS Installation Guide

## Requirements

- Windows 10/11 (primary supported deployment)
- PHP 8.2+ with `mongodb` extension (`php -m | findstr mongodb`)
- Composer, Node.js/npm
- MongoDB Community (local) **or** MongoDB Atlas
- Python 3.10+ (only if using AI parking cameras)
- Optional: MongoDB Database Tools (`mongodump` / `mongorestore`) for backups

## First-time setup

```powershell
cd <project-root>
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan storage:link
```

Edit `.env`:

1. `APP_URL`, `APP_DEBUG=false` for turnover/production
2. `MONGODB_URI` / `MONGODB_DATABASE` (or Atlas fields + `scripts\setup-atlas-mongo.ps1`)
3. Unique `RFID_API_TOKEN` and `AI_PARKING_API_TOKEN`
4. Camera `AI_CAMERA_*` blocks as needed
5. Mail / Google OAuth only if those features are used

```powershell
php artisan db:seed
npm run build
```

Change seeded Admin/Guard passwords immediately after first login.

### External uploads folder (recommended for turnover)

Store registration images/PDFs and violation evidence **outside** the project:

```env
ISCVMS_UPLOADS_ROOT=../iscvms-uploads
# or: ISCVMS_UPLOADS_ROOT=C:\ISCVMS-Uploads
```

```powershell
php artisan config:clear
php artisan iscvms:ensure-uploads
```

Creates `{root}/private` (documents + evidence) and `{root}/public` (profile photos). The app still accesses them through Laravel disks; `public/storage` is linked to the public tree. Leave `ISCVMS_UPLOADS_ROOT` empty to keep using `storage/app`.

## AI parking (optional)

```powershell
python -m pip install -r hardware\ai_parking\requirements.txt
.\scripts\setup-yolov9.ps1
.\scripts\setup-plate-model.ps1
```

Calibrate slots: `.\scripts\calibrate-cam1.ps1` (writes `hardware\ai_parking\zones_*.json`).

## RFID / ESP32

1. Copy `hardware\esp32_rfid_gate\rfid_gate_config.example.h` → `rfid_gate_config.h`
2. Set WiFi, `API_HOST` (LAN IP of PC), port **8000**, and `RFID_API_TOKEN` matching `.env`
3. Flash Entry/Exit sketches (`scripts\setup-esp32-gate.ps1` / Arduino IDE)
4. Allow firewall: `allow-laravel-firewall.bat`

## Start / verify

```powershell
.\scripts\start-system.ps1          # or .\start.ps1 for AI-inclusive shortcut
.\scripts\status-system.ps1
```

- Website: http://127.0.0.1:8001  
- LAN/ESP32: http://127.0.0.1:8000  
- AI health: http://127.0.0.1:8090/health  
- Admin health UI: `/admin/system-health`

See [MAINTENANCE.md](MAINTENANCE.md) and [TROUBLESHOOTING.md](TROUBLESHOOTING.md).
