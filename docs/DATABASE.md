# ISCVMS Database

## Configuration

- Runtime DB: **MongoDB** (`DB_CONNECTION=mongodb`)
- Env: `MONGODB_URI`, `MONGODB_DATABASE`, optional Atlas fields / `MONGODB_MODE`
- Optional secondary: `mongodb_atlas` for local-first sync (`SYNC_ENABLED`)
- MySQL (`MYSQL_IMPORT_*`): **import only** via `php artisan capstone:import-mysql`

## Important collections / models

Documented relationships also live in [ERD.md](ERD.md). Primary Eloquent models:

| Model | Purpose |
|-------|---------|
| `User` | Accounts, roles, RFID binding fields |
| `UserVehicle` / `Vehicle` | Registered vehicles / plates |
| `RegisteredPlate` | Lookup cache for plate OCR |
| `Visitor` / `VisitorRfidCard` | Visitors + temp cards |
| `GateLog` | Entry/exit scans |
| `ViolationLog` / `ViolationType` / `ViolationSanction` | Violations |
| `ParkingArea` / `ParkingSlot` / `ParkingRule` | Lots and rules |
| `Notification` | User notifications |
| `SystemSetting` / `GeneralInformation` | Settings content |
| `RolePermission` / `UserRole` / `Department` | RBAC / org |

Migrations under `database/migrations/` bootstrap Laravel cache/jobs/users scaffolding and domain collections; **campus data is seeded**, not fully migration-driven.

## Backup considerations

- Prefer `scripts\backup-system.ps1` (`mongodump` + `storage/app` + zone JSON)
- Restore: `scripts\restore-system.ps1 -BackupDir ...`
- **Never** `php artisan migrate:fresh` on production/turnover data
- Do not commit `storage/backups/`
