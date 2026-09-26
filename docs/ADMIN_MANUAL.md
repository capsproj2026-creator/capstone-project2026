# ISCVMS Admin Manual

## Login

Use a seeded or provisioned Admin account. Change password after first login on turnover machines. Prefer `APP_DEBUG=false`.

## Core tasks

| Task | Where |
|------|--------|
| Approve/decline registrations | Admin → Registrations |
| Assign RFID | Admin → RFID Assignment |
| Manage users / plates | User Management, Registered Plates |
| Visitors | Registered Visitors / History |
| Parking areas & slots | Parking |
| Live cameras | Live Cameras |
| Violations / access logs / reports | matching nav items |
| System settings & parking rules | Settings |
| **System Health** | Admin → System Health (live probes) |

## Cameras & AI

- Configure cameras in `.env` (`AI_CAMERA_n_*`), not in PHP source.
- Zones: calibrate with scripts; JSON under `hardware/ai_parking/`.
- If AI is down, web/RFID still work; System Health shows AI offline.

## Backups

```powershell
.\scripts\backup-system.ps1
.\scripts\restore-system.ps1 -BackupDir .\storage\backups\<stamp>
```

Never `migrate:fresh` on production data.

## Credentials to rotate on takeover

- Admin/Guard passwords
- `RFID_API_TOKEN` / `AI_PARKING_API_TOKEN`
- Mail, Google OAuth, visitor webhook token
- Camera RTSP passwords
- ESP32 WiFi + matching RFID token in `rfid_gate_config.h`
