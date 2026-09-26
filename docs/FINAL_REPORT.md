# ISCVMS Final Turnover Report

**Date:** 2026-09-26  
**Scope:** Safe turnover hardening (docs, ops scripts, Admin health, backup, secret hygiene) — no feature redesign.

---

## 1. Final architecture

Laravel 12 + MongoDB web app; dual front **:8001** (artisan) + **:8000** (LAN router); Reverb **:8080**; scheduler; optional Python AI parking **:8090** (YOLOv9 + EasyOCR); ESP32 RFID → `/api/rfid/*`.

## 2. Required services

Laravel loopback, LAN front, Reverb, `schedule:work`, MongoDB (local or Atlas), optional AI Python, optional Vite (dev).

## 3. Required ports

8000, 8001, 8080, 8090, 27017 (local Mongo).

## 4. Environment / configuration variables

Documented in `.env.example` and `docs/INSTALLATION.md` / `docs/MAINTENANCE.md` (`MONGODB_*`, `RFID_*`, `AI_*`, `AI_CAMERA_*`, Reverb, mail, Google, sync).

## 5. Hardcoded values corrected

- README / start scripts no longer print demo passwords.
- ESP32 `rfid_gate_config.example.h` token placeholder sanitized.
- Python AI default token no longer embeds a fixed lab token (requires `.env`).
- Purge command no longer echoes plaintext passwords.
- Seeders/tests retain lab passwords for local seeding only (documented to change after login).

## 6. Files created

- `docs/TURNOVER_AUDIT.md`, `INSTALLATION.md`, `CODEBASE_GUIDE.md`, `FILE_STRUCTURE.md`, `MODULES.md`, `DATABASE.md`, `API.md`, `ADMIN_MANUAL.md`, `GUARD_QUICK_GUIDE.md`, `MAINTENANCE.md`, `TROUBLESHOOTING.md`, `TURNOVER_CHECKLIST.md`, `FINAL_REPORT.md`
- `scripts/iscvms-common.ps1`, `status-system.ps1`, `stop-system.ps1`, `restart-system.ps1`, `backup-system.ps1`, `restore-system.ps1`
- `app/Services/SystemHealthService.php`, `app/Http/Controllers/Admin/SystemHealthController.php`, `resources/views/admin/system-health.blade.php`

## 7. Files modified

- `scripts/start-system.ps1`, `scripts/start-ai-parking.ps1`
- `routes/web.php`, `app/Services/NavigationService.php`, `app/Services/AiParkingHealthService.php`
- `README.md`, `.gitignore`
- `hardware/ai_parking/ai_parking_service.py`, `plate_owner_lookup.py`, `parking_rules.py`
- ESP32 `rfid_gate_config.example.h` copies
- `app/Console/Commands/PurgeUserDataCommand.php`

## 8–11. Start / stop / status / backup / restore

See `docs/MAINTENANCE.md` and scripts listed above.

## 12–14. Camera / AI / RFID configuration

Still via `.env` + zone JSON + ESP32 `rfid_gate_config.h` from example — documented in MODULES / INSTALLATION / API.

## 15. Log locations

`storage/logs/laravel.log`; AI stdout; ESP32 serial — see MAINTENANCE.md.

## 16. Security issues fixed / mitigated

Demo password banners removed from ops docs/scripts; example tokens cleaned; AI token default emptied; backups gitignored; Admin health does not expose secrets. Remaining: rotate live `.env` / firmware tokens on the target machine; change seeded passwords; clear `bootstrap/cache/config.php` if it cached old secrets.

## 17. Maintainability improvements

Ops scripts, Admin System Health, turnover docs, selective PHPDoc/docstrings on health/AI/parking intelligence, logging map.

## 18. Documentation created

Full set under `docs/` linked from README.

## 19. Dead / duplicate code discovered

- Dual start entrypoints (`start.ps1` → `start-ai-parking.ps1` vs `start-system.ps1`) — kept, documented.
- Events can be posted via occupancy and separate `/events` route — left as-is.
- Tracked `rfid_gate_config.h` may still exist locally (gitignored) with lab secrets — operators must rotate.

## 20. Remaining technical debt

- AI logging still stdout-only (no rotating file logger).
- Duplicate detection ghosts under YOLO track churn (known; not fully eliminated).
- `QUEUE_CONNECTION=sync` — fine for this deploy; scale later needs a real queue.
- Linux packaging not provided.

## 21. Remaining limitations

Hardware-dependent features require campus cameras/ESP32. Atlas sync optional and off by default.

## 22. Test results

| Item | Result |
|------|--------|
| `scripts/status-system.ps1` runs and probes ports | **PASS** (2026-09-26; LAN/Laravel/Reverb/AI/Mongo/storage reported) |
| Docs files present | **PASS** |
| Routes `admin.system-health` registered | **PASS** (`php artisan route:list`) |
| `.env.example` free of real Brevo/Google/RFID secrets | **PASS** (placeholders only) |
| Admin System Health page in browser | **NOT TESTED** (no authenticated browser session in this pass) |
| `backup-system.ps1` / `restore-system.ps1` end-to-end | **NOT TESTED** (mongodump may be absent; scripts authored) |
| `stop-system.ps1` / `restart-system.ps1` full cycle | **NOT TESTED** (would interrupt live stack) |
| RFID valid/invalid / boom | **NOT TESTED** (hardware) |
| Camera / YOLO / OCR live correctness | **NOT TESTED** as part of this turnover pass (stack was up; functional QA not re-run) |
| Student/Staff login | **NOT TESTED** |

## 23. Tasks still required before actual turnover

1. Change Admin/Guard (and any seeded) passwords on the target PC.
2. Set unique `RFID_API_TOKEN` / `AI_PARKING_API_TOKEN`; reflash ESP32 from updated example.
3. Run `backup-system.ps1` once with MongoDB Database Tools installed; verify restore on a spare DB.
4. Walk Admin through `/admin/system-health` and Guard through AI/Gate monitors.
5. Complete [TURNOVER_CHECKLIST.md](TURNOVER_CHECKLIST.md) with hardware tests on site.
6. Confirm `APP_DEBUG=false` and production mail/Google settings on the handover machine.
7. Deliver credentials out-of-band (not git).
