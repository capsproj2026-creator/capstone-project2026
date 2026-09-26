# ISCVMS File Structure

```
ISCVMS/   (Laravel project root)
├── app/
│   ├── Console/Commands/     # Artisan ops (db-status, ai-parking:check, sync, …)
│   ├── Http/Controllers/     # Admin, Guard, User, Api, Auth
│   ├── Http/Middleware/      # role, RFID/AI token verification
│   ├── Models/               # Mongo models
│   ├── Services/             # Business logic + SystemHealthService
│   └── Support/              # DatabaseUnavailable, helpers
├── bootstrap/                # app.php, lan_front_router.php
├── config/                   # services.php (rfid, ai_parking), database, reverb
├── database/
│   ├── migrations/           # Minimal Laravel tables + domain bootstrap
│   └── seeders/              # CapstoneSeeder (campus data)
├── docs/                     # Turnover manuals (this folder)
├── hardware/
│   ├── ai_parking/           # Python AI service, zones_*.json, models/
│   ├── esp32_rfid_gate/      # Entry firmware (+ example config)
│   ├── esp32_rfid_gate_exit/ # Exit firmware
│   └── arduino/              # Synced Entry/Exit copies
├── public/
├── resources/views/          # Blade UI (admin/guard/user)
├── routes/                   # web.php, api.php, channels.php, console.php
├── scripts/                  # start/stop/status/backup/restore/setup
├── storage/                  # logs, app uploads, backups/
├── tests/
├── .env.example
├── start.ps1 / start.bat
└── README.md
```

Omitted from structure docs: `vendor/`, `node_modules/`, caches, generated logs, `*.pt` weights.
