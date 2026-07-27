# Smart Campus VMS (Laravel + MongoDB)

Vehicle Management System for CSPC, built with **Laravel 12** and **MongoDB** as the primary database.

Reference SQL schema (for documentation / legacy import): [`capstone.sql`](capstone.sql)  
Original plain PHP app: [`legacy-php/`](legacy-php/)

## Requirements

- PHP 8.2+ with **mongodb** extension enabled
- Composer
- MongoDB Server 6.0+ (local, Atlas, or Docker)
- Apache/Nginx, or `php artisan serve`

## 1. Enable PHP MongoDB extension (XAMPP)

1. Download the matching `php_mongodb.dll` for your PHP version from [PECL](https://pecl.php.net/package/mongodb) or use MongoDB’s Windows install guide.
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
php artisan storage:link
```

## 3. Configure MongoDB

In **`.env`** (already set for local MongoDB):

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

This loads roles, departments, parking areas/slots, rules, violations metadata, and a default admin user:

```bash
php artisan db:seed
```

**Test logins (development)**

| Field    | Value            |
|----------|------------------|
| Email    | `admin@my.cspc.edu.ph` |
| Password | `admin123`       |

Additional test users can be created quickly (without running the full seeder) by running:

```bash
php scripts/ensure_test_users.php
```

| Role    | Email              | Password       |
|---------|---------------------|----------------|
| Guard   | `guard@my.cspc.edu.ph`    | `password123`  |

## 5. Run the application

```bash
php artisan serve
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000)

For XAMPP Apache, point the document root to the `public/` folder.

## Roles & routes

| Role    | URL prefix | Dashboard        |
|---------|------------|------------------|
| Admin   | `/admin`   | Registrations, users, RFID, parking, settings |
| Guard   | `/guard`   | Violations, parking, gate tools |
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

That uses `CapstoneSeeder` with the same structure as `capstone.sql`, plus a default admin user.

## Notes

- `capstone.sql` is kept for reference and capstone documentation; it is **not** imported automatically.
- File uploads: `storage/app/public/uploads/` → `public/storage` after `php artisan storage:link`.
- Gate log `daily_log_id` reset logic (formerly a MySQL trigger) runs in `App\Observers\GateLogObserver`.
