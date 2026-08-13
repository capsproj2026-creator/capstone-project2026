# Smart Campus VMS (Laravel + MongoDB)

Vehicle Management System for CSPC — **Laravel 12** + **MongoDB**, with optional ESP32 RFID boom gates, live cameras, and YOLOv9 AI parking.

---

## Quick start (Windows)

### First time only

```powershell
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan storage:link
php artisan db:seed
npm run build
```

Configure MongoDB in `.env` (Atlas or local — see [Configure MongoDB](#3-configure-mongodb) below).

### Start the whole system

From the project root (`Capstone_System.3`):

```powershell
# Easiest — double-click start.bat, OR run:
.\start.ps1
```

Same thing, full path:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\start-ai-parking.ps1
```

| What you want | Command |
|---------------|---------|
| **Website + AI cameras** (normal demo) | `.\start.ps1` |
| **Website only** (no YOLO) | `powershell -ExecutionPolicy Bypass -File .\scripts\start-system.ps1 -SkipAi` |
| **AI cameras only** (Laravel already running) | `powershell -ExecutionPolicy Bypass -File .\scripts\start-ai-parking.ps1 -SkipWebStack` |

Then open: **http://127.0.0.1:8000**

| Role  | Email                    | Password      |
|-------|--------------------------|---------------|
| Admin | `admin@my.cspc.edu.ph`   | `admin123`    |
| Guard | `guard@my.cspc.edu.ph`   | `password123` |

`.\start.ps1` / `start-ai-parking.ps1` opens Laravel, Reverb, Vite (if needed), and the AI parking window.

---

## Requirements

- PHP 8.2+ with **mongodb** extension enabled
- Composer
- Node.js / npm
- MongoDB (local, Atlas, or Docker)
- Python 3.10+ (only if you use YOLOv9 AI parking)
- Windows: XAMPP PHP works if the MongoDB DLL is enabled in `php.ini`

## 1. Enable PHP MongoDB extension (XAMPP)

1. Download the matching `php_mongodb.dll` for your PHP version from [PECL](https://pecl.php.net/package/mongodb).
2. Place the DLL in `C:\xampp\php\ext\`
3. Edit `C:\xampp\php\php.ini` and add:
   ```ini
   extension=mongodb
   ```
4. Restart Apache and verify:
   ```bash
   php -m | findstr mongodb
   ```

## 2. Install dependencies

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan storage:link
npm run build
```

## 3. Configure MongoDB

**Local MongoDB:**

```env
DB_CONNECTION=mongodb
MONGODB_URI=mongodb://127.0.0.1:27017
MONGODB_DATABASE=capstone
```

**MongoDB Atlas:**

```env
DB_CONNECTION=mongodb
MONGODB_URI=mongodb+srv://user:pass@cluster.mongodb.net/?retryWrites=true&w=majority
MONGODB_DATABASE=capstone
MONGODB_AUTH_DATABASE=admin
MONGODB_TLS_ALLOW_INVALID=true
```

(`MONGODB_TLS_ALLOW_INVALID=true` is for local Windows/XAMPP only — turn it off in production.)

In Atlas → **Network Access**, whitelist your IP (or `0.0.0.0/0` for dev).

Verify:

```bash
php scripts/mongo_ping.php
```

## 4. Seed the database

```bash
php artisan db:seed
```

Quick recreate test users:

```bash
php scripts/ensure_test_users.php
```

## 5. Run the application (details)

### Recommended one-command start

| File | What it does |
|------|----------------|
| [`start.bat`](start.bat) / [`start.ps1`](start.ps1) | Shortcut → full stack |
| [`scripts/start-ai-parking.ps1`](scripts/start-ai-parking.ps1) | Starts **Laravel + Reverb + Vite + YOLOv9** |
| [`scripts/start-system.ps1`](scripts/start-system.ps1) | Same stack; use `-SkipAi` for website only |

```powershell
.\start.ps1
# or
powershell -ExecutionPolicy Bypass -File .\scripts\start-ai-parking.ps1
# website only:
powershell -ExecutionPolicy Bypass -File .\scripts\start-system.ps1 -SkipAi
```

### What starts

| Service | Purpose | URL / port |
|---------|---------|------------|
| Laravel | Website + RFID/AI APIs | http://127.0.0.1:8000 |
| Reverb | Live Gate Monitor realtime | (configured in `.env`) |
| Vite | Frontend hot reload (optional) | — |
| YOLOv9 AI | Cameras + plate OCR | http://127.0.0.1:8090 |

### Manual start (advanced)

```bash
php artisan serve --host=0.0.0.0 --port=8000
php artisan reverb:start
npm run dev
```

Use `--host=0.0.0.0` so the **ESP32** can reach Laravel on your Wi‑Fi IP (not only `localhost`).

---

## Roles & routes

| Role    | URL prefix | Dashboard |
|---------|------------|-----------|
| Admin   | `/admin`   | Registrations, users, RFID, parking, reports, live cameras |
| Guard   | `/guard`   | Live gate, visitors, violations, AI parking, plate lookup |
| Student / Staff | `/user` | Notifications, parking, my violations |

---

## ESP32 + RC522 + Servo (boom gate)

Full wiring and flash steps live here; also see [`hardware/esp32_rfid_gate/README.md`](hardware/esp32_rfid_gate/README.md).

### How it works

```
RFID card → RC522 → ESP32 → POST /api/rfid/scan → Laravel decision
                                              ↓
                         granted? → ESP32 moves servo / relay open
                         denied?  → red LED + buzzer (gate stays closed)
```

Laravel **does not** wire to the servo. The ESP32 opens the boom only when the API returns `granted: true` (or Guard emergency open via heartbeat).

### Parts

- ESP32 DevKit (or similar)
- RC522 RFID reader
- Servo (e.g. SG90 / MG996R) **or** a relay module for a real boom barrier
- External **5V** supply for the servo (do **not** power a servo from ESP32 3.3V)
- Optional: green LED, red LED, buzzer

### Wiring — RC522 → ESP32

| RC522 pin | ESP32 GPIO | Meaning |
|-----------|------------|---------|
| SDA (SS)  | **5**      | SPI chip select |
| SCK       | **18**     | SPI clock |
| MOSI      | **23**     | SPI data out |
| MISO      | **19**     | SPI data in |
| RST       | **22**     | Reset |
| 3.3V      | **3.3V**   | Power (RC522 is 3.3V — not 5V) |
| GND       | **GND**    | Common ground |

### Wiring — LEDs / buzzer (optional)

| Device | ESP32 GPIO | Notes |
|--------|------------|-------|
| Green LED | **25** | Access granted |
| Red LED   | **26** | Access denied |
| Buzzer    | **27** | Deny pattern |

Use a resistor (~220Ω) in series with each LED.

### Wiring — Servo → ESP32 (recommended for demo arm)

| Servo wire | Connect to | Notes |
|------------|------------|-------|
| Signal (usually orange/yellow) | **GPIO 14** | PWM from ESP32 |
| VCC (usually red) | **External 5V +** | Separate supply |
| GND (usually brown/black) | **External 5V −** **and** ESP32 **GND** | Common ground is required |

Set in config: `ACTUATOR_MODE ACTUATOR_SERVO`  
Open angle default **90°**, close **0°** (adjust in `rfid_gate_config.h`).

### Wiring — Relay (real boom barrier)

| Relay | ESP32 | Notes |
|-------|-------|-------|
| IN / Signal | **GPIO 14** | HIGH = open (active-high module) |
| VCC | 5V or 3.3V (per module) | Follow module label |
| GND | GND | Common ground |
| COM / NO | Barrier motor / lock circuit | Isolate high voltage properly |

Default firmware mode: `ACTUATOR_MODE ACTUATOR_RELAY`.

### What code to put on the ESP32

You do **not** write a new sketch from scratch. Use the project firmware:

| Gate | Folder / file to open in Arduino IDE |
|------|--------------------------------------|
| **Entry** | `hardware/esp32_rfid_gate/esp32_rfid_gate.ino` |
| **Exit** | `hardware/esp32_rfid_gate_exit/esp32_rfid_gate_exit.ino` |

Shared logic is in `hardware/esp32_rfid_gate/rfid_gate_common.h`.

#### Flash steps (Entry gate)

1. Install **Arduino IDE** + ESP32 board support.
2. Install libraries: **MFRC522**, **ArduinoJson**, and **ESP32Servo** (if using a servo).
3. Copy config (once):
   ```text
   hardware/esp32_rfid_gate/rfid_gate_config.example.h
     → hardware/esp32_rfid_gate/rfid_gate_config.h
   ```
4. Edit `rfid_gate_config.h`:
   ```cpp
   #define WIFI_SSID     "YourWifiName"
   #define WIFI_PASSWORD "YourWifiPassword"
   #define API_BASE      "http://192.168.1.6:8000"   // THIS PC Wi‑Fi IP (ipconfig), not localhost
   #define RFID_API_TOKEN "capstone-rfid-dev-token-change-me"  // must match .env RFID_API_TOKEN

   #define ACTUATOR_MODE  ACTUATOR_SERVO   // or ACTUATOR_RELAY
   #define SERVO_OPEN_ANGLE  90
   #define SERVO_CLOSE_ANGLE 0
   ```
5. Open `esp32_rfid_gate.ino` → select your ESP32 board + COM port → **Upload**.
6. Start Laravel with LAN access (`.\start.ps1` already uses `--host=0.0.0.0`).
7. Serial Monitor @ 115200: you should see Wi‑Fi IP and `Gate GATE-IN-1 (Entry) ready.`
8. Tap a registered RFID card → green LED + servo opens if Laravel grants access.

#### Exit gate

Same wiring. Flash `hardware/esp32_rfid_gate_exit/esp32_rfid_gate_exit.ino`  
(it sets `GATE-OUT-1` / `Exit`). Keep the same `rfid_gate_config.h` (Wi‑Fi + API + token) in the entry folder so both sketches can include it.

#### Important

- `API_BASE` must be your **PC Wi‑Fi IPv4** (e.g. `192.168.1.6`), never `127.0.0.1` / `localhost` from the ESP32’s point of view.
- `RFID_API_TOKEN` must match `.env`.
- After flash, Guard → **Live Gate Monitor** shows the gate **Online** and can **emergency-open** the boom.

---

## YOLOv9 AI parking (optional)

```powershell
.\scripts\setup-yolov9.ps1
```

```env
AI_PARKING_YOLO_MODEL=yolov9c
AI_PARKING_OCR_ENABLED=1
AI_CAMERA_1_IP=192.168.1.108
```

- Live Cameras: `/guard/live-cameras`
- AI Parking Monitor: `/guard/ai-parking`  
Details: [`hardware/ai_parking/README.md`](hardware/ai_parking/README.md)

---

## Other `.env` keys

| Variable | Purpose |
|----------|---------|
| `RFID_API_TOKEN` | ESP32 gate API |
| `REVERB_*` / `VITE_REVERB_*` | Live Gate realtime |
| `AI_PARKING_*` / `AI_CAMERA_*` | AI parking cameras |

## Production checklist

| Item | Action |
|------|--------|
| `APP_DEBUG=false` | No stack traces in production |
| `APP_URL` | Public HTTPS URL |
| Tokens | Change default `RFID_API_TOKEN` / `AI_PARKING_API_TOKEN` |
| Atlas TLS | `MONGODB_TLS_ALLOW_INVALID=false` in production |
| `php artisan storage:link` | Violation evidence |
| Scheduler | `* * * * * php artisan schedule:run` |
| ESP32 | `rfid_gate_config.h` with real Wi‑Fi + API URL |

## Notes

- Reference SQL: [`capstone.sql`](capstone.sql) — not imported automatically.
- Uploads: `storage/app/public` → `public/storage` after `storage:link`.
- Gate daily IDs: `App\Observers\GateLogObserver`.
