# ISCVMS Turnover Audit

**System:** Integrated Smart Campus Vehicle Management System (ISCVMS)  
**Audit date:** 2026-09-26  
**Codebase root:** project Laravel app (this repository)

This document records facts from the repository before turnover hardening. It does not claim hardware was tested live.

---

## A. Architecture

| Layer | Technology |
|-------|------------|
| Web app | Laravel 12, PHP ^8.2 |
| Database | MongoDB (`mongodb/laravel-mongodb`); MySQL is import-only (`capstone:import-mysql`) |
| Frontend | Blade, Vite, Tailwind, Laravel Echo + Pusher protocol for Reverb |
| Realtime | Laravel Reverb (WebSockets) |
| AI parking | Python service `hardware/ai_parking/ai_parking_service.py` — YOLOv9 + plate YOLO + EasyOCR |
| Gate hardware | ESP32 RFID Entry/Exit sketches under `hardware/esp32_rfid_gate*` and synced `hardware/arduino/` |

**Roles:** Admin (1), Guard (2), Student (3), Staff (4), Visitor (5).  
**Route prefixes:** `/admin`, `/guard`, `/user`.

**Dual web front:**

- Loopback app: `php artisan serve` on **8001**
- LAN front for browsers/ESP32: `php -S 0.0.0.0:8000` with `bootstrap/lan_front_router.php` (proxies most traffic to 8001; short-circuits RFID heartbeat)

---

## B. Services

| Service | Start (existing) | Port / notes |
|---------|------------------|--------------|
| Laravel (loopback) | `scripts/start-system.ps1` | **8001** |
| LAN front | same | **8000** |
| Reverb | `php artisan reverb:start` | **8080** |
| Scheduler | `php artisan schedule:work` | visitors:expire, sync:run, email:retry-verification |
| Vite | optional `npm run dev` | Vite default |
| AI parking | `python -u ai_parking_service.py` | **8090** (`AI_STREAM_PORT`) |
| MongoDB | OS service or Atlas | **27017** or cloud URI |
| Queue | `QUEUE_CONNECTION=sync` | No separate worker |

**Hardware APIs** (`routes/api.php`):

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/rfid/scan` | `X-RFID-TOKEN` |
| POST | `/api/rfid/heartbeat` | `X-RFID-TOKEN` |
| POST | `/api/ai-parking/occupancy` | `X-AI-TOKEN` |
| POST | `/api/ai-parking/events` | `X-AI-TOKEN` |
| POST | `/api/ai-parking/plate-lookup` | `X-AI-TOKEN` |
| POST | `/api/visitor/pre-register/google` | webhook token |

---

## C. Dependencies

- PHP 8.2+ with `mongodb` extension, Composer, Node.js/npm
- MongoDB (local Community or Atlas)
- Python 3.10+ for AI: `opencv-python`, `ultralytics`, `numpy`, `easyocr` (`hardware/ai_parking/requirements.txt`)
- Model files under `hardware/ai_parking/models/` (gitignored `*.pt`; install via `scripts/setup-yolov9.ps1`, `scripts/setup-plate-model.ps1`)

---

## D. Startup process

| Entry | Behavior |
|-------|----------|
| `start.ps1` / `start.bat` | Delegates to `scripts/start-ai-parking.ps1` |
| `scripts/start-system.ps1` | Web stack (8001/8000/Reverb/scheduler/optional Vite/AI) |
| `scripts/start-ai-parking.ps1` | Full stack + YOLO; may kill listeners on 8090/8000/8001 before start |

**Gaps before turnover work:** no dedicated `status-system.ps1`, `stop-system.ps1`, `restart-system.ps1`.

---

## E. Configuration problems

- Cameras/AI settings are correctly env-driven (`AI_CAMERA_*`, `AI_PARKING_*`); zones persist as JSON on disk.
- `.env.example` is largely complete; local `.env` holds real secrets (gitignored).
- `QUEUE_CONNECTION=sync` means no queue worker; fine for turnover but document it.
- `SESSION_ENCRYPT=false` and `MONGODB_TLS_ALLOW_INVALID=true` in example are lab-oriented — must be documented for production.

---

## F. Hardcoded values

| Location | Issue |
|----------|--------|
| README / seeders / start scripts | Demo emails/passwords for Admin/Guard |
| `hardware/ai_parking/*.py` defaults | Dev AI token / `127.0.0.1:8000` fallbacks |
| ESP32 `rfid_gate_config.h` | WiFi / API host / RFID token (file is gitignored; example.h is template) |
| `rfid_gate_common.h` | Fallback host/token strings |
| Camera IPs in `.env.example` | Example LAN IPs (not secrets) |

Localhost defaults for co-located Laravel↔AI are intentional and should remain.

---

## G. Missing documentation (pre-turnover)

Present: `README.md`, `docs/ERD.md`, `docs/4.2-system-design.md`, `docs/LOCAL_FIRST_SYNC_SETUP.md`, `docs/google-forms/`, AI README/CALIBRATION.

Absent at audit time (to be created): INSTALLATION, CODEBASE_GUIDE, FILE_STRUCTURE, MODULES, DATABASE, API, ADMIN_MANUAL, GUARD_QUICK_GUIDE, MAINTENANCE, TROUBLESHOOTING, TURNOVER_CHECKLIST.

---

## H. Backup / recovery status

- No project `mongodump` / restore scripts at audit time.
- `scripts/sync-project.ps1` only temp-backs `.env`.
- Assets needing backup: MongoDB data, `storage/app`, public storage uploads, `hardware/ai_parking/zones_*.json`, optional OCR debug crops.

---

## I. Security concerns

1. Published seeded demo passwords in docs/scripts.
2. Dev RFID/AI tokens in examples and Python defaults — must be rotated for production.
3. Empty `RFID_API_TOKEN` / `AI_PARKING_API_TOKEN` correctly fail closed (503).
4. No Admin unified System Health page (partial probes via `AiParkingHealthService`, CLI `capstone:db-status`, `ai-parking:check`, `GET /up`).
5. Database unavailable → friendly 503 (`DatabaseUnavailable`); guard monitors show Laravel-offline banners.

---

## J. Recommended fixes (implemented in this turnover pass)

1. Lifecycle: `status` / `stop` / `restart` PowerShell scripts; safer start checks.
2. Admin System Health page with real probes (no fake Online).
3. `.env.example` / README secret hygiene; ESP32 example-as-template documented.
4. Timestamped backup/restore scripts; gitignore `storage/backups/`.
5. Full `docs/*` turnover manuals + README links.
6. Selective PHPDoc on critical entrypoints; logging map in MAINTENANCE.

**Non-goals:** feature redesign, schema relationship changes, mass refactors, Linux systemd, fabricated PASS tests.
