# Smart Campus VMS

CSPC Vehicle Management System — **Laravel 12** + **MongoDB**, with ESP32 RFID gates and optional YOLOv9 AI parking.

## Requirements

- PHP 8.2+ with the `mongodb` extension (`php -m` should list `mongodb`)
- Composer, Node.js / npm
- MongoDB (local or Atlas)
- Windows: XAMPP PHP is fine if the MongoDB DLL is enabled in `php.ini`

## 1. First-time setup

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan storage:link
php artisan db:seed
npm run build
```

Edit **`.env`** and set at least:

| Variable | Purpose |
|----------|---------|
| `MONGODB_URI` / `MONGODB_DATABASE` | Database |
| `RFID_API_TOKEN` | ESP32 gate API |
| `REVERB_*` / `VITE_REVERB_*` | Live Gate realtime |
| `AI_PARKING_*` / `AI_CAMERA_*` | AI parking (optional) |

Check MongoDB:

```bash
php scripts/mongo_ping.php
```

## 2. Start the system (every demo / day)

**Easiest (Windows)** — one script opens Laravel + Reverb + Vite + **YOLOv9 AI parking**:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\start-system.ps1
```

Then open [http://127.0.0.1:8000](http://127.0.0.1:8000).

| Process | Command | Purpose |
|---------|---------|---------|
| Laravel | `php artisan serve --host=0.0.0.0 --port=8000` | Website + API (LAN for ESP32) |
| Reverb | `php artisan reverb:start` | Live Gate & Access Records realtime |
| Vite | `npm run dev` *(or `npm run build` once)* | Frontend / Echo |
| YOLOv9 | `.\scripts\start-ai-parking.ps1` | AI cameras, occupancy, plate OCR |

**Useful flags**

```powershell
# Web stack only (no cameras)
powershell -ExecutionPolicy Bypass -File .\scripts\start-system.ps1 -SkipAi

# Skip Vite if you already ran npm run build
powershell -ExecutionPolicy Bypass -File .\scripts\start-system.ps1 -SkipVite

# YOLOv9 alone (if Laravel is already running)
powershell -ExecutionPolicy Bypass -File .\scripts\start-ai-parking.ps1
```

Or from Composer (Laravel + Reverb + Vite in one terminal — start AI separately if needed):

```bash
composer run dev
powershell -ExecutionPolicy Bypass -File .\scripts\start-ai-parking.ps1
```

Plate → owner names need `AI_PARKING_OCR_ENABLED=1` in `.env`. Details: [`hardware/ai_parking/README.md`](hardware/ai_parking/README.md).

### Test accounts

| Role | Email | Password |
|------|--------|----------|
| Admin | `admin@my.cspc.edu.ph` | `admin123` |
| Guard | `guard@my.cspc.edu.ph` | `password123` |

Quick recreate test users: `php scripts/ensure_test_users.php`

### Portals

| Role | URL |
|------|-----|
| Admin | `/admin` |
| Guard | `/guard` |
| Student / Staff | `/user` |

## 3. Hardware tips

**RFID (ESP32)**  
- Flash `hardware/esp32_rfid_gate/` (Entry) or `hardware/esp32_rfid_gate_exit/` (Exit).  
- Set `API_BASE` to this PC’s Wi‑Fi IP, e.g. `http://192.168.1.104:8000` (not `localhost`).  
- Match `RFID_API_TOKEN` with `.env`.  
- Laravel must use `--host=0.0.0.0` so the board can connect.

**AI parking**  
- Details: [`hardware/ai_parking/README.md`](hardware/ai_parking/README.md)  
- Owner names on the monitor need `AI_PARKING_OCR_ENABLED=1`.

## 4. Optional extras

**Import old MySQL dump into MongoDB** (only if you still use `capstone.sql`):

```bash
# Load SQL into MySQL first, then:
php artisan capstone:import-mysql --fresh
```

Otherwise `php artisan db:seed` is enough for a fresh demo database.

**XAMPP Apache** instead of `artisan serve`: point the vhost document root to `public/`. Still run Reverb (and AI) in other terminals.
