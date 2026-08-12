# Smart Campus VMS (Laravel + MongoDB)

Vehicle Management System for CSPC, built with **Laravel 12** and **MongoDB** as the primary database.

Reference SQL schema (for documentation / legacy import): [`capstone.sql`](capstone.sql)

Optional extras: ESP32 RFID gates, live cameras, and **YOLOv9 AI parking** (plate OCR + occupancy).

## Requirements

- PHP 8.2+ with **mongodb** extension enabled
- Composer
- Node.js / npm (frontend assets + Vite)
- MongoDB Server 6.0+ (local, Atlas, or Docker)
- Apache/Nginx, or `php artisan serve`
- Python 3.10+ (optional — YOLOv9 AI parking only)
- Windows: XAMPP PHP works if the MongoDB DLL is enabled in `php.ini`

## 1. Enable PHP MongoDB extension (XAMPP)

1. Download the matching `php_mongodb.dll` for your PHP version from [PECL](https://pecl.php.net/package/mongodb) or use MongoDB's Windows install guide.
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

In **`.env`** (default — local MongoDB on this PC):

```env
DB_CONNECTION=mongodb
MONGODB_URI=mongodb://127.0.0.1:27017
MONGODB_DATABASE=capstone
```

For **MongoDB Atlas**, use your connection string:

```env
MONGODB_URI=mongodb+srv://user:pass@cluster.mongodb.net/?retryWrites=true&w=majority
MONGODB_DATABASE=capstone
```

Connection settings live in **`config/database.php`** under the `mongodb` key.

**Atlas network access (required)** — in [MongoDB Atlas](https://cloud.mongodb.com) → your cluster → **Network Access**, add your current IP or `0.0.0.0/0` for development. A blocked IP often shows as `TLS handshake failed` / `tlsv1 alert internal error`.

**Atlas TLS on Windows (XAMPP)** — if connection still fails after whitelisting your IP:

```env
MONGODB_AUTH_DATABASE=admin
MONGODB_TLS_ALLOW_INVALID=true
```

Use `MONGODB_TLS_ALLOW_INVALID` only for local development. Prefer updating PHP/OpenSSL and the `mongodb` PECL extension, or use a local MongoDB instance.

Verify connectivity:

```bash
php scripts/mongo_ping.php
```

Remove unused legacy collections (e.g. duplicate `offense_sanctions`):

```bash
php artisan capstone:prune-legacy-mongo
php artisan capstone:prune-legacy-mongo --dry-run   # preview only
```

## 4. Seed the database

This loads roles, departments, parking areas/slots, rules, violations metadata, and default test users:

```bash
php artisan db:seed
```

**Test logins (development)**

| Role  | Email                    | Password      |
|-------|--------------------------|---------------|
| Admin | `admin@my.cspc.edu.ph`   | `admin123`    |
| Guard | `guard@my.cspc.edu.ph`   | `password123` |

Quick recreate test users without re-seeding:

```bash
php scripts/ensure_test_users.php
```

**Change these passwords on any shared or production database.** Demo seeds are for local QA only.

## 5. Run the application

### One command (recommended)

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\start-ai-parking.ps1
```

Starts **everything**: MongoDB check, Laravel website, Reverb, Vite, and AI cameras.

Or use the shortcut:

```powershell
.\start.ps1
```

| Flag | Effect |
|------|--------|
| `-SkipAi` | Website only (no AI cameras) |
| `-SkipVite` | Skip `npm run dev` (use after `npm run build`) |

### What starts automatically

| Service | Purpose |
|---------|---------|
| Laravel `:8000` | Website + API (uses MongoDB) |
| Reverb | Live gate realtime |
| Vite | Frontend (skipped if `public/build` exists) |
| YOLOv9 AI | Cameras + plate scan |

Login: **http://127.0.0.1:8000** — `admin@my.cspc.edu.ph` / `admin123` or `guard@my.cspc.edu.ph` / `password123`

### Manual start (advanced)

```bash
php artisan serve
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000)

For XAMPP Apache, point the document root to the `public/` folder.

## Roles & routes

| Role    | URL prefix | Dashboard |
|---------|------------|-----------|
| Admin   | `/admin`   | Registrations, users, RFID, parking, settings, live cameras |
| Guard   | `/guard`   | Violations, parking, live cameras, AI parking monitor |
| Student | `/user`    | Notifications, parking info |
| Staff   | `/user`    | Same as Student |

Public: `/` (home), `/login`, `/register`

## MongoDB collections

Data is stored in collections matching the original MySQL tables, for example:

- `users`, `user_roles`, `departments`, `vehicles`
- `notifications`, `gate_logs`, `violations_log`
- `parking_areas`, `parking_slots`, `parking_rules`
- `general_informations`, `violation_types`, etc.

Numeric `id` fields are auto-assigned via a `counters` collection (same behavior as auto-increment in SQL).

**View data:** connect with [MongoDB Compass](https://www.mongodb.com/products/compass) using `mongodb://127.0.0.1:27017` and open database **`capstone`**.

## Converting `capstone.sql` to MongoDB

MySQL cannot load a `.sql` file directly into MongoDB. Use this two-step flow:

### Step 1 — Import the SQL file into MySQL (one time)

Using **phpMyAdmin** (XAMPP):

1. Create database `capstone` (if it does not exist).
2. Select the `capstone` database → **Import** → choose `capstone.sql` → **Go**.

Or from the command line:

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS capstone;"
mysql -u root capstone < capstone.sql
```

### Step 2 — Copy MySQL data into MongoDB

Ensure MongoDB is running and `MONGODB_*` is set in `.env`. The MySQL source uses `MYSQL_IMPORT_*` (defaults match XAMPP).

```bash
php artisan capstone:import-mysql --fresh
```

| Option | Description |
|--------|-------------|
| `--fresh` | Drops MongoDB collections before import (recommended on first run) |
| `--chunk=500` | Batch size for large tables like `parking_slots` |

This preserves numeric `id` values and relationships from your SQL dump.

### Alternative: seed without MySQL

If you only need reference data (no SQL dump rows), skip MySQL and run:

```bash
php artisan db:seed
```

That uses `CapstoneSeeder` with the same structure as `capstone.sql`, plus default admin/guard users.

## YOLOv9 AI parking (optional)

Download Ultralytics **pretrained YOLOv9** weights once (not in Git — ~50 MB for `yolov9c`):

```powershell
.\scripts\setup-yolov9.ps1
```

| Model | Use case |
|-------|----------|
| `yolov9c` | Default — balanced (recommended) |
| `yolov9m` | Long-range / distant vehicles |
| `yolov9t` / `yolov9s` | Faster on weak CPU |
| `yolov9e` | Max accuracy (GPU recommended) |

Set in `.env`:

```env
AI_PARKING_YOLO_MODEL=yolov9c
AI_PARKING_OCR_ENABLED=1
AI_CAMERA_1_IP=192.168.1.108
```

- **Live Cameras** (`/guard/live-cameras`) — clean CCTV feed
- **AI Parking Monitor** (`/guard/ai-parking`) — vehicle detection + plate scan (cars & motorcycles)

Full details: [`hardware/ai_parking/README.md`](hardware/ai_parking/README.md)

## Hardware tips

**RFID (ESP32)**

- Flash `hardware/esp32_rfid_gate/` (Entry) or `hardware/esp32_rfid_gate_exit/` (Exit).
- Set `API_BASE` to this PC's Wi-Fi IP, e.g. `http://192.168.1.104:8000` (not `localhost`).
- Match `RFID_API_TOKEN` with `.env`.
- Laravel must use `--host=0.0.0.0` so the board can connect.

**AI parking**

- Configure `AI_CAMERA_*` and `AI_PARKING_API_TOKEN` in `.env`.
- Plate → owner matching needs `AI_PARKING_OCR_ENABLED=1` and seeded users with `plate_number`.

## Other `.env` keys

| Variable | Purpose |
|----------|---------|
| `RFID_API_TOKEN` | ESP32 gate API |
| `REVERB_*` / `VITE_REVERB_*` | Live Gate realtime |
| `AI_PARKING_*` / `AI_CAMERA_*` | AI parking cameras |

## Production deployment checklist

Before going live, verify:

| Item | Action |
|------|--------|
| `APP_DEBUG=false` | Never expose stack traces in production |
| `APP_URL` | Set to public HTTPS URL |
| `RFID_API_TOKEN` / `AI_PARKING_API_TOKEN` | Change from dev defaults — empty tokens return HTTP 503 |
| MongoDB Atlas | Whitelist server IP; set `MONGODB_TLS_ALLOW_INVALID=false` in production |
| `php artisan storage:link` | Required for violation evidence uploads |
| `npm run build` | Serve compiled assets from `public/build` |
| Scheduler | Cron: `* * * * * php artisan schedule:run` (visitor expiry) |
| Reverb | Run `php artisan reverb:start` for live gate monitor |
| Queue | Consider `QUEUE_CONNECTION=database` or `redis` for mail |
| ESP32 | Copy `rfid_gate_config.example.h` → `rfid_gate_config.h`; set WiFi + API URL |
| Firewall | Allow HTTP port from ESP32/cameras to Laravel host |

Use Apache/Nginx + PHP-FPM instead of `php artisan serve` in production. See [`hardware/esp32_rfid_gate/README.md`](hardware/esp32_rfid_gate/README.md) for boom-gate wiring.

## Notes

- `capstone.sql` is kept for reference and capstone documentation; it is **not** imported automatically.
- File uploads: `storage/app/public/uploads/` → `public/storage` after `php artisan storage:link`.
- Gate log `daily_log_id` reset logic (formerly a MySQL trigger) runs in `App\Observers\GateLogObserver`.
