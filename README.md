# Smart Campus VMS (Laravel + MongoDB)

Vehicle Management System for CSPC — **Laravel 12** + **MongoDB**, with ESP32 RFID boom gates, live cameras, and optional YOLOv9 AI parking.

---

## Quick Start (Windows)

### Prerequisites

- PHP 8.2+ with **mongodb** extension (`php -m | findstr mongodb`)
- Composer, Node.js/npm
- MongoDB (local or Atlas)
- Python 3.10+ *(only if using AI cameras)*

### 1. Install (first time)

```powershell
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan storage:link
```

Edit `.env`: set `MONGODB_URI`, `MONGODB_DATABASE`, and (for hardware) `RFID_API_TOKEN` / `AI_PARKING_API_TOKEN`.

```powershell
php artisan db:seed
npm run build
```

### 2. Start

From this project folder:

```powershell
.\start.ps1
```

Or double-click `start.bat`.

| Goal | Command |
|------|---------|
| Full stack (website + AI cameras) | `.\start.ps1` |
| Website only (no YOLO) | `.\scripts\start-system.ps1 -SkipAi` |
| AI cameras only (Laravel already up) | `.\start.ps1 -SkipWebStack` |

Open **http://127.0.0.1:8000**

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@my.cspc.edu.ph` | `admin123` |
| Guard | `guard@my.cspc.edu.ph` | `password123` |

Keep the PowerShell windows open while using the system.

---

## Requirements detail

- PHP 8.2+ with **mongodb** extension enabled
- Composer, Node.js / npm
- MongoDB (local, Atlas, or Docker)
- Python 3.10+ (AI parking only)
- Windows: XAMPP PHP works if the MongoDB DLL is enabled in `php.ini`

### Enable PHP MongoDB (XAMPP)

1. Download matching `php_mongodb.dll` from [PECL](https://pecl.php.net/package/mongodb).
2. Place in `C:\xampp\php\ext\`
3. Add `extension=mongodb` to `php.ini`, restart Apache
4. Verify: `php -m | findstr mongodb`

---

## Configure MongoDB

**Local:**

```env
DB_CONNECTION=mongodb
MONGODB_URI=mongodb://127.0.0.1:27017
MONGODB_DATABASE=capstone
```

**Atlas:**

```env
DB_CONNECTION=mongodb
MONGODB_URI=mongodb+srv://user:pass@cluster.mongodb.net/?retryWrites=true&w=majority
MONGODB_DATABASE=capstone
MONGODB_AUTH_DATABASE=admin
```

(`MONGODB_TLS_ALLOW_INVALID=true` is for local Windows/XAMPP only — turn it **off** in production.)

Verify: `php scripts/mongo_ping.php`

Re-seed: `php artisan db:seed`  
Test users: `php scripts/ensure_test_users.php`

---

## What starts with `.\start.ps1`

| Service | Purpose | URL / port |
|---------|---------|------------|
| Laravel | Website + RFID/AI APIs | http://127.0.0.1:8000 (`--host=0.0.0.0`) |
| Reverb | Live Gate Monitor realtime | from `.env` |
| Vite | Frontend hot reload (if needed) | — |
| YOLOv9 AI | Cameras + plate OCR | http://127.0.0.1:8090 |

Manual (advanced):

```powershell
php artisan serve --host=0.0.0.0 --port=8000
php artisan reverb:start
npm run dev
```

Use `--host=0.0.0.0` so ESP32 boards can reach Laravel on your LAN IP.

---

## Roles & routes

| Role | URL prefix | Highlights |
|------|------------|------------|
| Admin | `/admin` | Registrations, RFID, users, parking, reports, live cameras |
| Guard | `/guard` | Live gate, visitors, AI parking, plate lookup, live cameras |
| Student / Staff | `/user` | Notifications, parking, violations, entry/exit |

---

## ESP32 RFID boom gates

Two boards: **Entry** (RC522 + servo) and **Exit** (RC522 only). Shared boom opens from Entry via Laravel heartbeat.

| Sketch folder | Role |
|---------------|------|
| `hardware/arduino/Entry/` | `GATE-IN-1` + servo GPIO 14 |
| `hardware/arduino/Exit/` | `GATE-OUT-1`, no servo |

Or sync to Arduino IDE sketchbook:

```powershell
.\sync-arduino.bat
```

Then open `Documents\Arduino\Entry\Entry.ino` and `Exit\Exit.ino`.

### Wiring — RC522 → ESP32

| RC522 | ESP32 |
|-------|-------|
| SDA | **5** |
| SCK | **18** |
| MOSI | **23** |
| MISO | **19** |
| RST | **22** |
| 3.3V | **3.3V** (not 5V) |
| GND | **GND** |

LEDs: green **25**, red **26**, buzzer **27**. Servo signal **14** (Entry only) + external 5V + common GND.

### Config

Copy `hardware/esp32_rfid_gate/rfid_gate_config.example.h` → `rfid_gate_config.h` (gitignored). Set:

- `WIFI_SSID` / `WIFI_PASSWORD` (same network as the PC)
- `API_HOST` = PC LAN IP from `ipconfig`
- `RFID_API_TOKEN` = same as Laravel `.env`

Libraries: **MFRC522**, **ArduinoJson**, **ESP32Servo** (Entry). Board: ESP32 Dev Module, core 3.3.x.

Full details: [`hardware/esp32_rfid_gate/README.md`](hardware/esp32_rfid_gate/README.md) and [`hardware/arduino/README.md`](hardware/arduino/README.md).

If Serial shows `HTTP error -1`, run `allow-laravel-firewall.bat` once as Admin.

---

## AI parking cameras

Configure `AI_CAMERA_1_*` / `AI_CAMERA_2_*` in `.env` (IP, user, pass, `ENABLED=true`). Setup:

```powershell
.\scripts\setup-yolov9.ps1
php artisan db:seed --class=AiTestLotSeeder
```

See [`hardware/ai_parking/README.md`](hardware/ai_parking/README.md).

---

## Tests

```powershell
php artisan test
```

---

## Project layout

```
app/                 Laravel application
routes/              web.php + api.php (RFID + AI tokens)
hardware/arduino/    Entry + Exit ESP32 sketches (Arduino IDE)
hardware/esp32_rfid_gate/   Shared firmware + config example
hardware/ai_parking/ YOLOv9 parking service
scripts/             start-system, start-ai-parking, sync-arduino, …
start.ps1 / start.bat
```

---

## Production notes

- Change all default passwords and API tokens
- Do not commit `.env` or `rfid_gate_config.h`
- Serve HTTPS in production; ESP32↔Laravel is plain HTTP on LAN by design for the demo
- Turn off `MONGODB_TLS_ALLOW_INVALID` when using proper TLS
