# Local-First Sync: 3-Laptop Setup Guide

This documents how to run the Smart Campus VMS on 3 independent laptops,
each with its own local MongoDB, while staying synchronized with the
central MongoDB Atlas cluster used by the cloud deployment.

Read `config/sync.php` and `app/Services/Sync/AtlasSyncService.php` first —
this doc assumes their behavior.

## How it works (short version)

- Each laptop's Laravel app talks to a **local** MongoDB as its default
  database (`DB_CONNECTION=mongodb`, `MONGODB_MODE=local`). All core
  features (login, registration, RFID, gate/servo, parking, YOLOv9, OCR,
  violations, visitors, reports) read/write locally — nothing waits on the
  internet.
- A **separate** connection (`mongodb_atlas`, driven by `MONGODB_ATLAS_URI`)
  is used only by a background sync job (`php artisan sync:run`, scheduled
  every 2 minutes) to push pending local changes to Atlas and pull changes
  made on other laptops.
- Every laptop has a stable `SYNC_DEVICE_ID` (`LAPTOP-01`/`02`/`03`). This
  is also used to give each laptop a private block of the app's numeric ids
  (see "Why device IDs matter" below), so laptops can never generate the
  same id for two different real records.
- The cloud deployment leaves `SYNC_ENABLED=false` and talks to Atlas
  directly via the existing `MONGODB_URI`/`MONGODB_MODE=atlas` settings —
  nothing here changes cloud behavior.

## Why device IDs matter (read before first use)

This app assigns human-friendly sequential integer ids (`SequenceService`),
and — importantly — Mongo's native `_id` for every document **is** that
same integer (verified directly against the database; there is no separate
ObjectId to fall back on). A fresh empty database always starts counting
from 1. If two laptops both start from 1 independently, laptop 1's record
`id=5` and laptop 2's unrelated record `id=5` would collide as the *same*
document once synced to Atlas.

To prevent this without changing the id type (which would touch every
relation, foreign key, and URL in the app), each registered `SYNC_DEVICE_ID`
reserves a private 10,000,000-id block (`config('sync.device_offset_block')`)
the moment `SYNC_ENABLED=true`:

| Device      | Id block start |
|-------------|-----------------|
| (no device / cloud) | 1 |
| LAPTOP-01   | 10,000,001 |
| LAPTOP-02   | 20,000,001 |
| LAPTOP-03   | 30,000,001 |

**Set `SYNC_DEVICE_ID` before creating any real data on that laptop.**
Changing it later does not renumber existing records, but new records would
start from the new device's block, which is safe — it just means a laptop
should stick to one device id for its lifetime.

To add a 4th/5th laptop, add another entry to `device_registry` in
`config/sync.php`.

## First-time setup, per laptop

1. **Install MongoDB Community Server** locally and make sure the service
   is running (`mongod` listening on `127.0.0.1:27017`).
2. **Clone the repo** and run `composer install`, `npm install && npm run build`.
3. **Configure `.env`** — copy `.env.example` and fill in:
   ```
   DB_CONNECTION=mongodb
   MONGODB_URI=mongodb://127.0.0.1:27017
   MONGODB_MODE=local
   MONGODB_DATABASE=capstone

   SYNC_ENABLED=true
   SYNC_DEVICE_ID=LAPTOP-01          # LAPTOP-02 / LAPTOP-03 on the others
   MONGODB_ATLAS_URI=<the same Atlas URI used in production>
   MONGODB_ATLAS_DATABASE=capstone
   ```
   Keep all other settings (mail, RFID token, AI parking, cameras, Reverb)
   the same style as the existing `.env.example` — these are unrelated to
   sync and already documented there.
4. **Set the device ID** (`SYNC_DEVICE_ID`) — must be unique per laptop and
   never reused, per the table above.
5. **Start Laravel**: `php artisan serve` (or the project's existing
   `scripts/start-system.ps1`), plus `php artisan reverb:start` and the
   Python AI parking service as usual — unchanged.
6. **Initialize the local database**: `php artisan migrate` (for the
   non-Mongo `migrations`/`sessions` bookkeeping tables) and
   `php artisan mongo:ensure-indexes` (existing command) to create Mongo
   indexes locally. Optionally seed with `php artisan db:seed`.
7. **Connect to Atlas** once the laptop has internet: nothing extra to do —
   `MONGODB_ATLAS_URI` is already set; the scheduler will pick it up on its
   next tick (every 2 minutes), or run it immediately: `php artisan sync:run`.
8. **Perform an initial sync**: run `php artisan sync:run` manually once and
   check the output — it should report `pushed`/`pulled` counts per model,
   or `skipped: Atlas is not reachable right now` if offline.
9. **Test offline operation**: disconnect the laptop from the internet,
   confirm login/registration/RFID/gate/parking/violations/visitors all
   still work (see Test Plan below), then reconnect and re-run
   `php artisan sync:run` to confirm it catches up.
10. Make sure the scheduler is actually running continuously, e.g. via
    Windows Task Scheduler running `php artisan schedule:run` every minute
    (the project may already have a script for this — check
    `scripts/start-system.ps1`). Without a running scheduler, `sync:run`
    only executes when triggered manually.

All 3 laptops point `MONGODB_ATLAS_URI`/`MONGODB_ATLAS_DATABASE` at the
**same** Atlas cluster/database.

## Cloud deployment

No changes needed. Leave `SYNC_ENABLED=false` (the `.env.example` default)
and keep using `MONGODB_URI`/`MONGODB_MODE=atlas` as before. The
`mongodb_atlas` connection and `sync:run` command are simply inert there.

## Conflict handling

- **Append-only data** (gate/RFID logs, violations) is never edited after
  creation by this app, so it is synced as a plain "insert if new"
  operation — no conflicts possible by construction.
- **Editable data** (users, visitors, visitor RFID cards) uses a
  `sync_version` counter. If Atlas has a newer version from a *different*
  device than the one about to push, the local record is marked
  `sync_status = conflict` instead of overwriting or being overwritten.
  Conflicted records are left alone by both push and pull until resolved.
  **Current limitation**: there is no admin UI yet to review/resolve
  `sync_status = conflict` records — an admin would need to inspect the
  document directly (e.g. via `php artisan tinker`) and decide which side
  to keep. Given the project timeline this was intentionally scoped out;
  flag it if you need it built.

## Known limitations

- No admin UI for reviewing `sync_status = conflict` records (see above).
- Only `User`, `Visitor`, `GateLog`, `ViolationLog`, and `VisitorRfidCard`
  are synced (`config('sync.collections')`). Camera streams/frames, YOLO
  debug files, sessions, and cache are intentionally never synced, per the
  original requirements.
- Evidence images (violation photos, registration documents) stay wherever
  the existing file-storage system puts them (local `storage/app`) and are
  **not** copied between laptops/Atlas by this feature — only the database
  records referencing them are synced. If a violation is created on
  Laptop 2, an admin viewing it from Laptop 1/Atlas will see the record but
  not the photo file unless the underlying storage disk is itself shared
  (e.g. S3) — this matches the existing single-storage architecture and was
  explicitly out of scope to change ("do not store large images in sync
  documents").
- True 3-laptop, offline-hardware, and long-running scheduler tests were
  **not** executed as part of this change (this workspace only has one
  machine and no local MongoDB installed) — see the PASS/FAIL test report
  for exactly what was and was not actually run.
