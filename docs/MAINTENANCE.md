# ISCVMS Maintenance

## Architecture & ports

| Service | Port |
|---------|------|
| LAN front (ESP32/browser) | 8000 |
| Laravel loopback | 8001 |
| Reverb | 8080 |
| AI parking | 8090 |
| MongoDB (local) | 27017 |

## Start / stop / status

```powershell
.\scripts\start-system.ps1          # -SkipAi to omit YOLO
.\scripts\status-system.ps1
.\scripts\stop-system.ps1           # -SkipAi to leave AI running
.\scripts\restart-system.ps1
.\start.ps1                         # AI-inclusive shortcut
```

Admin UI: `/admin/system-health`

## Configuration

- All secrets and cameras: `.env` (template `.env.example`)
- AI tunables: `AI_PARKING_*` in `.env`
- Zones: `hardware/ai_parking/zones_*.json`
- ESP32: copy `rfid_gate_config.example.h` → `rfid_gate_config.h` (gitignored)
- **Uploads (images/PDFs):** set `ISCVMS_UPLOADS_ROOT` to a folder **outside** the project (e.g. `C:\ISCVMS-Uploads` or `../iscvms-uploads`). Then run:

```powershell
php artisan config:clear
php artisan iscvms:ensure-uploads
```

  Layout: `{ISCVMS_UPLOADS_ROOT}/private/...` (IDs, licenses, OR/CR, violation evidence) and `{ISCVMS_UPLOADS_ROOT}/public/uploads/profile/...` (avatars). Laravel still accesses them via the `local`/`private`/`public` disks; `public/storage` is symlinked to the public tree. Old files under `storage/app` remain readable as a fallback.

## Log locations

| Source | Location |
|--------|----------|
| Laravel (auth, RFID API, occupancy, mail, DB) | `storage/logs/laravel.log` |
| AI / YOLO / OCR | AI PowerShell/Terminal **stdout** (no default file logger) |
| OCR debug crops (if enabled) | `hardware/ai_parking/debug_plates/` |
| ESP32 | USB Serial Monitor |
| Start scripts | console output in Windows Terminal tabs |

Do not log passwords, OTP, tokens, or camera passwords.

## Backup / restore

```powershell
.\scripts\backup-system.ps1
.\scripts\restore-system.ps1 -BackupDir .\storage\backups\<stamp>
```

Backups go to `storage/backups/` (gitignored). Requires MongoDB Database Tools for dump/restore.

## Updating the system

1. Backup
2. `git pull` (or deliver release package)
3. `composer install --no-dev` / `npm ci && npm run build` as appropriate
4. `php artisan config:clear` then start scripts (or `config:cache`)
5. Re-flash ESP32 only if firmware/token changed
6. Verify `status-system.ps1` + Admin System Health

## Related docs

[INSTALLATION](INSTALLATION.md) · [TROUBLESHOOTING](TROUBLESHOOTING.md) · [API](API.md) · [TURNOVER_AUDIT](TURNOVER_AUDIT.md)
