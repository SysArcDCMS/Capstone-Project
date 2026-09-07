# IMRAWS-NLP Backend - Setup Guide

This document explains how to install, configure, and run the IMRAWS-NLP
Laravel backend from a clean Windows machine. Follow each section in order.

> **If you got stuck anywhere**, check [Section 16 - Common Gotchas](#16-common-gotchas) first.

---

## Table of Contents

- [0. Prerequisites](#0-prerequisites)
- [1. Install PHP 8.2 via XAMPP](#1-install-php-82-via-xampp)
- [2. Enable Postgres PHP Extensions](#2-enable-postgres-php-extensions)
- [3. Install Composer](#3-install-composer)
- [4. Install PostgreSQL 16](#4-install-postgresql-16)
- [5. Add Postgres to System PATH](#5-add-postgres-to-system-path)
- [6. Create the Database and User](#6-create-the-database-and-user)
- [7. Clone the Repository](#7-clone-the-repository)
- [8. Install PHP Dependencies](#8-install-php-dependencies)
- [9. Configure .env](#9-configure-env)
- [10. Generate Keys](#10-generate-keys)
- [11. Storage Link, Migrations, Seed](#11-storage-link-migrations-seed)
- [12. Start the NLP Microservice](#12-start-the-nlp-microservice)
- [13. Start the Laravel Server](#13-start-the-laravel-server)
- [14. Default Credentials](#14-default-credentials)
- [15. API Quick Reference](#15-api-quick-reference)
- [16. Common Gotchas](#16-common-gotchas)
- [Appendix A: Capstone Variance Log](#appendix-a-capstone-variance-log)

---

## 0. Prerequisites

| Requirement | Notes |
|---|---|
| Windows 10 or 11 | Tested on Windows 10/11 |
| ~2 GB free disk | Postgres ~300 MB + transformers model ~500 MB + composer/vendor ~200 MB |
| Administrator access | Needed for XAMPP install, Postgres install, Firewall rules |

---

## 1. Install PHP 8.2 via XAMPP

We chose XAMPP because it bundles a tested PHP build with the extensions
we need (and avoids the Visual C++ compiler issues that hit when trying
to install full PHP 8.3 from source).

1. Download the XAMPP installer from
   [apachefriends.org](https://www.apachefriends.org/download.html).
   Pick the **8.2.x** version (matches Laravel 12's PHP requirement).
2. Run the installer as Administrator.
3. Install to the default `C:\xampp`.
4. When prompted which components to install, you only need:
   - Core files (required)
   - PHP (required)
   - You can skip Apache, MySQL, phpMyAdmin, etc.
5. Verify the installation:

   ```powershell
   & "C:\xampp\php\php.exe" --version
   ```

   Expected output: PHP 8.2.x (ZTS Visual C++ 2019 x64).

---

## 2. Enable Postgres PHP Extensions

Out of the box, XAMPP's `php.ini` has the Postgres extensions **commented out**.
You must uncomment them or Laravel will not be able to talk to Postgres.

1. Open `C:\xampp\php\php.ini` in a text editor (admin rights).
2. Find and uncomment these three lines (~line 947):

   ```ini
   ;extension=pdo_pgsql   ->  extension=pdo_pgsql
   ;extension=pgsql       ->  extension=pgsql
   ;extension=gd          ->  extension=gd
   ```

3. Save the file.
4. Verify:

   ```powershell
   & "C:\xampp\php\php.exe" -m | Select-String "pgsql|^gd"
   ```

   Expected output:
   ```
   gd
   pdo_pgsql
   pgsql
   ```

> **If you skip this step**, you'll get cryptic errors like
> `could not find driver` or `could not translate host name` when running
> `php artisan migrate`.

---

## 3. Install Composer

1. Download `Composer-Setup.exe` from
   [getcomposer.org](https://getcomposer.org/Composer-Setup.exe).
2. Run the installer.
3. When prompted for the PHP path, **point it at `C:\xampp\php\php.exe`**.
4. Uncheck "Install Shell Menus" if you want a clean install.
5. Verify:

   ```powershell
   composer --version
   ```

   Expected: `Composer version 2.x.x`.

If `composer` is not recognized, **close all PowerShell windows and reopen**.

---

## 4. Install PostgreSQL 16

1. Download the PostgreSQL 16 Windows x86-64 installer from
   [enterprisedb.com](https://www.enterprisedb.com/downloads/postgres-postgresql-downloads).
2. Run the installer **as Administrator**.
3. Component selection - install these:
   - **PostgreSQL Server** (required)
   - **pgAdmin 4** (optional but useful)
   - **Command Line Tools** (required - this gives you `psql.exe`)
   - **Stack Builder** (un-check to save time, not needed)
4. Data directory: leave default `C:\Program Files\PostgreSQL\16\data\`.
5. **Set the postgres superuser password and remember it.**
   For development we use `ZAQ!2wsx` throughout this doc. Pick something
   different in your own setup and use it consistently below.
6. Port: leave default `5432`.
7. Locale: leave default.
8. Finish. The installer auto-runs `initdb` and starts the service.

Verify:

```powershell
Get-Service -Name "postgresql-x64-16"
```

Expected status: `Running`.

---

## 5. Add Postgres to System PATH

The installer does not always add Postgres to PATH. Do it manually:

### Option A: PowerShell one-liner

```powershell
[Environment]::SetEnvironmentVariable(
    "Path",
    "$(([Environment]::GetEnvironmentVariable('Path','User'));C:\Program Files\PostgreSQL\16\bin",
    "User"
)
$env:Path = "$env:Path;C:\Program Files\PostgreSQL\16\bin"
```

### Option B: GUI

System Properties -> Environment Variables -> User variables -> Path ->
Edit -> Add `C:\Program Files\PostgreSQL\16\bin`.

### Verify

Open a **NEW** PowerShell window (the existing one won't see the new PATH):

```powershell
psql --version
```

Expected: `psql (PostgreSQL) 16.x`.

---

## 6. Create the Database and User

We use these credentials throughout the doc (substitute yours):

- Database: `imraws_db`
- User: `imraws_user`
- Password: `ZAQ!2wsx` (same as the postgres superuser, only because this is dev)

Run in a PowerShell window:

```powershell
$env:PGPASSWORD = "ZAQ!2wsx"
psql -U postgres -h localhost -p 5432 -c "CREATE DATABASE imraws_db;"
psql -U postgres -h localhost -p 5432 -c "CREATE USER imraws_user WITH PASSWORD 'ZAQ!2wsx';"
psql -U postgres -h localhost -p 5432 -c "GRANT ALL PRIVILEGES ON DATABASE imraws_db TO imraws_user;"
psql -U postgres -h localhost -p 5432 -d imraws_db -c "GRANT ALL ON SCHEMA public TO imraws_user; ALTER DATABASE imraws_db OWNER TO imraws_user;"
```

> **The `ALTER DATABASE ... OWNER TO` is critical.** Without it, `migrate:fresh`
> fails with `permission denied to drop table` because Laravel switches
> to `imraws_user` mid-migration but Postgres requires the table owner.

### Verify connection

```powershell
$env:PGPASSWORD = "ZAQ!2wsx"
psql -U imraws_user -h localhost -p 5432 -d imraws_db -c "SELECT current_database(), current_user;"
```

Expected:
```
 current_database | current_user
------------------+-------------
 imraws_db        | imraws_user
```

---

## 7. Clone the Repository

```powershell
cd C:\Users\irayj\Desktop\JR
git clone <your-repo-url> imraws
cd imraws
```

If you are reading this doc **after** cloning, just `cd` into the existing
folder.

The repo has three folders:

| Folder | Purpose |
|---|---|
| `ai-nlp/` | FastAPI NLP microservice |
| `imraws-backend/` | Laravel backend + Blade web portal |
| `website-ui-only/` | Reference design (gitignored — gitkeep only) |

---

## 8. Install PHP Dependencies

```powershell
cd imraws-backend
composer install
```

Expected output: 100+ packages installed. Composer will pick **Laravel 12**
(not 13) because we're on PHP 8.2 and Laravel 13 needs PHP 8.3.

---

## 9. Configure `.env`

Laravel ships a `.env.example` but no `.env`. Create it:

```powershell
copy .env.example .env
```

Open `.env` in a text editor and verify/set these values:

```ini
APP_NAME="IMRAWS-NLP"
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=imraws_db
DB_USERNAME=imraws_user
DB_PASSWORD=ZAQ!2wsx

# JWT auth (overrides default session guard)
AUTH_GUARD=api

# Generated in the next step
JWT_SECRET=<generated-in-next-step>

# Where the FastAPI NLP microservice runs (different port to avoid clash)
NLP_SERVICE_URL=http://127.0.0.1:8001
NLP_SERVICE_TIMEOUT=30
```

> **`APP_URL`** matters for absolute URLs in emails and notifications.
> If you bind Laravel to `0.0.0.0` for LAN testing (see §13), set this
> to `http://<your-LAN-IP>:8000`.

---

## 10. Generate Keys

```powershell
php artisan key:generate          # writes APP_KEY to .env (if not already)
php artisan jwt:secret --force   # writes JWT_SECRET=<64-hex-chars> to .env
```

Verify `.env` after:

```powershell
Select-String -Path .env -Pattern "^APP_KEY|^JWT_SECRET"
```

Both should be present and non-empty.

---

## 11. Storage Link, Migrations, Seed

### 11a. Link storage (for photo uploads)

```powershell
php artisan storage:link
```

Creates a symlink `public/storage` -> `storage/app/public`. Required so
uploaded photos (DFD 5.6 photo proof) are reachable via URL.

### 11b. Drop leftover Postgres ENUMs (only if migrating from a previous attempt)

If you previously ran migrations, the Postgres ENUM types persist even
after `migrate:fresh`. Drop them first:

```powershell
$env:PGPASSWORD = "ZAQ!2wsx"
psql -U imraws_user -h localhost -p 5432 -d imraws_db -c "DROP TYPE IF EXISTS user_role, incident_status, assignment_action, availability_status, feedback_action CASCADE;"
```

### 11c. Run migrations + seed

```powershell
php artisan migrate:fresh --seed
```

Expected: 10 migrations run, then `DemoDataSeeder` outputs 4 sample logins.

---

## 12. Start the NLP Microservice

The `ai-nlp/` folder is a standalone Python service on **port 8001** (a
different port from Laravel's 8000 to avoid conflict).

### 12a. Create the venv (one-time)

```powershell
cd ..\ai-nlp
python -m venv ai_nlp_env
```

> **Note:** The original requirements.txt pinned versions from 2023
> (`scikit-learn==1.3.2`, `fastapi==0.104.1`) which **do not compile on
> Python 3.13**. We ship an updated `requirements.txt` with lower
> bounds (`fastapi>=0.110`, `scikit-learn>=1.5`, etc.) that install
> cleanly on 3.11 and 3.13. The venv name was originally
> `fastapi_nlp_env` and was **renamed to `ai_nlp_env`** — make sure
> you create the new name.

### 12b. Install dependencies (one-time)

```powershell
ai_nlp_env\Scripts\python -m pip install --upgrade pip
ai_nlp_env\Scripts\python -m pip install fastapi "uvicorn[standard]" "pydantic>=2.7" "pydantic-settings>=2.3" requests python-multipart
ai_nlp_env\Scripts\python -m pip install scikit-learn pandas numpy
ai_nlp_env\Scripts\python -m pip install transformers torch
```

The last command downloads ~500 MB of RoBERTa weights the first time.

### 12c. Configure

```powershell
copy .env.example .env
```

Open `.env` and ensure:

```ini
PORT=8001
HOST=0.0.0.0
LARAVEL_CONFIG_URL=    # leave blank unless you wire a per-request config endpoint
```

### 12d. Train the model (one-time)

```powershell
ai_nlp_env\Scripts\python train_classifier.py
```

Expected: training completes, accuracy ~96%, models saved to `models/`.

### 12e. Run the service

```powershell
ai_nlp_env\Scripts\python -m app.main
```

> **First startup is slow** (30-60 seconds) because RoBERTa is being
> downloaded the first time. Subsequent restarts take ~5 seconds.

Verify in another PowerShell:

```powershell
curl http://127.0.0.1:8001/api/v1/health
```

Expected:
```json
{"status":"online","service":"FastAPI NLP Microservice","timestamp":...}
```

---

## 13. Start the Laravel Server

Once both the database and the NLP service are running, start Laravel.

### 13a. Loopback binding (same PC testing)

```powershell
cd imraws-backend
php artisan serve --host=127.0.0.1 --port=8000
```

### 13b. LAN binding (phone or another PC on same Wi-Fi)

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

Then allow inbound TCP 8000 in Windows Firewall (run as Administrator once):

```powershell
New-NetFirewallRule -DisplayName "Laravel Dev" -Direction Inbound -LocalPort 8000 -Protocol TCP -Action Allow
```

> **Don't use `--host=0.0.0.0` on production.** In production, Laravel sits
> behind Apache/Nginx on the Oracle Cloud VPS, and the web server is the
> public-facing process.

### 13c. Verify

Open a browser:

- `http://127.0.0.1:8000/login` - Laravel web portal
- `http://127.0.0.1:8000/up` - Laravel health check
- `http://127.0.0.1:8001/api/v1/health` - NLP health check

---

## 14. Default Credentials

After running `migrate:fresh --seed`, these accounts are available:

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@imraws.local` | `admin123` |
| Engineer | `engineer.axel@imraws.local` | `engineer123` |
| Offsite Staff (Metering) | `tl.metering@imraws.local` | `staff123` |
| Offsite Staff (Billing) | `tl.billing@imraws.local` | `staff123` |
| Offsite Staff (Water Quality) | `tl.water_quality@imraws.local` | `staff123` |
| Offsite Staff (Operations) | `tl.operations@imraws.local` | `staff123` |
| Customer | `robert.j@example.com` | `customer123` |

**Web portal login:** `http://127.0.0.1:8000/login`
(recommended: log in as `admin@imraws.local` to see the most screens).

---

## 15. API Quick Reference

All API routes live under `/api/*` and (except register/login) require a
JWT in the `Authorization: Bearer <token>` header.

### Auth (public)

| Method | URL | Notes |
|---|---|---|
| POST | `/api/auth/register` | Customer self-registration; always returns role=customer |
| POST | `/api/auth/login` | Returns JWT for any role |

### Auth (any authenticated)

| Method | URL | Notes |
|---|---|---|
| GET | `/api/auth/me` | Current user profile |
| PATCH | `/api/auth/profile` | Update own profile (DFD 1.8) |
| POST | `/api/auth/logout` | Invalidate JWT |
| POST | `/api/auth/refresh` | Get a new JWT |

### Incidents

| Method | URL | Role | Notes |
|---|---|---|---|
| POST | `/api/incidents` | customer | Triggers NLP, persists composite_score |
| GET | `/api/incidents` | any | Role-filtered listing |
| GET | `/api/incidents/{id}` | any | Customer sees own only |
| PATCH | `/api/incidents/{id}/status` | offsite_staff / engineer / admin | DFD 5.3, 5.7 |
| POST | `/api/incidents/{id}/route` | engineer / admin | Manual re-route |

### Assignments (capstone HITL flow)

| Method | URL | Role | Notes |
|---|---|---|---|
| GET | `/api/assignments` | any | Offsite staff sees own only |
| GET | `/api/assignments/{id}` | any | |
| POST | `/api/assignments/{id}/team-leader-action` | offsite_staff | `action=accept\|reject\|correct` |
| POST | `/api/assignments/{id}/engineer-adjudicate` | engineer | `mode=accept\|override\|reassign` |

### Attachments (DFD 5.6 - Photo Proof)

| Method | URL | Role | Notes |
|---|---|---|---|
| POST | `/api/incidents/{id}/attachments` | offsite_staff+ | multipart, jpeg/png/webp, 5MB max |
| GET | `/api/incidents/{id}/attachments` | any | Lists thumbnails |
| DELETE | `/api/attachments/{id}` | uploader or admin | Removes file + row |

### Users (DFD 1.0)

| Method | URL | Role | Notes |
|---|---|---|---|
| GET | `/api/users` | admin / engineer | List accounts |
| POST | `/api/users` | admin | Create (any role) |
| GET | `/api/users/{id}` | admin / engineer | |
| PATCH | `/api/users/{id}` | admin or self | Update role / is_active / password |
| PATCH | `/api/users/{id}/deactivate` | admin | Toggle is_active flag |

### Availability

| Method | URL | Role | Notes |
|---|---|---|---|
| GET | `/api/availability` | any | Offsite staff sees own only |
| GET | `/api/availability/me` | any | Own record shortcut |
| POST | `/api/availability` | offsite_staff | Toggle status (4 ENUM values) |

### Notifications

| Method | URL | Role | Notes |
|---|---|---|---|
| GET | `/api/notifications` | any | Own notifications + unread_count |
| PATCH | `/api/notifications/{id}/read` | any | Mark one read |
| POST | `/api/notifications/mark-all-read` | any | |

### Reports (DFD 6.0)

| Method | URL | Role | Notes |
|---|---|---|---|
| GET | `/api/reports/dashboard` | any | KPI counts, status/severity/category breakdowns, avg resolution, top corrections |
| GET | `/api/reports/incidents` | any | Filterable incident listing |
| GET | `/api/reports/audit-logs` | admin / engineer | Tamper-evident trail |

### Mobile Web Routes (Session auth, not JWT)

| Method | URL | Notes |
|---|---|---|
| GET | `/login` | Web login form |
| GET | `/dashboard` | Admin dashboard (KPIs) |
| GET | `/complaints` | Complaint list |
| GET | `/complaints/{id}` | Complaint detail |
| GET | `/users` | User management |
| GET | `/categories` | Category cards |
| GET | `/reports` | Reports dashboard |
| GET | `/settings` | Profile editor |

---

## 16. Common Gotchas

The list below covers every error we hit while building this. Future
errors should be added here as they appear.

### 16.1 `type "user_role" already exists` on `migrate:fresh`

Cause: Postgres ENUM types are not dropped by Laravel's `migrate:fresh`.
They persist across runs.

Fix (run once before re-migrating):
```powershell
$env:PGPASSWORD = "ZAQ!2wsx"
psql -U imraws_user -h localhost -p 5432 -d imraws_db -c "DROP TYPE IF EXISTS user_role, incident_status, assignment_action, availability_status, feedback_action CASCADE;"
php artisan migrate:fresh --seed
```

### 16.2 `could not find driver` on Laravel boot

Cause: `pdo_pgsql` extension not loaded.

Fix: Edit `C:\xampp\php\php.ini`, uncomment `extension=pdo_pgsql`, save,
restart any running `artisan serve`.

### 16.3 `composer: command not found`

Cause: PATH wasn't updated by the Composer installer, or no PowerShell
restart after install.

Fix: Close **all** PowerShell windows, reopen, run `composer --version` again.

### 16.4 `psql: The term 'psql' is not recognized`

Cause: Postgres `bin` directory is not on PATH.

Fix: Add `C:\Program Files\PostgreSQL\16\bin` to your user PATH (see §5).

### 16.5 `419 Page Expired` on web login via curl

Cause: Laravel's CSRF middleware. The login Blade form has `@csrf` which
expects a token from a prior GET.

Fix: For curl-based smoke tests, FIRST `GET /login` to seed the XSRF cookie,
THEN POST with header `X-XSRF-TOKEN: <decoded-cookie-value>`. In a real
browser this is handled automatically.

### 16.6 `SQLSTATE[42710]: Duplicate object` during migration

Cause: Same as 16.1. Drop the ENUM types first.

### 16.7 `Operation timed out after 30000ms` when loading web portal

Cause: A web controller tried to call its own API via HTTP loopback
(`Http::withToken()->get('/api/...')`). Laravel's PHP dev server can't
handle recursive requests efficiently.

Fix: Don't self-call. Use Eloquent directly. Example: the
`PortalController::assignments` method queries the DB, not the API.

### 16.8 NLP service takes 30-60 seconds to start

Cause: First-time download of the RoBERTa sentiment model from
Hugging Face (~500 MB).

Fix: Be patient. Watch the logs at
`C:\Users\irayj\AppData\Local\Temp\opencode\nlp_stderr.log` if you
served it to a file, or just wait for the
`Application startup complete.` line.

### 16.9 `500 Server Error: Unknown "user_role" type`

Cause: Postgres ENUM exists in DB, but Laravel expects the enum to be
defined via the migration. If you imported an existing DB without running
migrations fresh, the enum may not be aligned with what Laravel expects.

Fix: `migrate:fresh --seed` (after dropping ENUMs as in 16.1).

### 16.10 Port 8000 already in use

Cause: Old `php artisan serve` process still running.

Fix:
```powershell
Get-Process php -ErrorAction SilentlyContinue | Stop-Process -Force
```

### 16.11 NLP startup logs hang on model download

Cause: The transformers library downloads from `huggingface.co`.
Network/proxy can stall.

Fix: Wait (default timeout is generous). If it never completes, check your
proxy settings or run `HUGGINGFACE_HUB_DOWNLOAD_TIMEOUT=300` before
launching the service.

---

## Appendix A: Capstone Variance Log

The approved capstone document specifies certain artifacts that needed
slight extensions during implementation. All variances are documented
below with file:line pointers for a reviewer to audit.

| # | Variance | Where it lives | Why |
|---|---|---|---|
| **V1** | Added `composite_score` (float) to NLP output and `tbl_incidents.composite_score` column | `ai-nlp/app/services/nlp_service.py:30`, `database/migrations/2026_04_01_000001_create_tbl_incidents_table.php` | Capstone DFD 2.9 explicitly computes a Composite Severity Score from sentiment + days + keywords. Without persisting it, the panel will fail a doc-vs-code audit. |
| **V2** | Full `tbl_feedback` schema (15 columns) per capstone ERD | `database/migrations/2026_04_01_000005_create_tbl_feedback_table.php` | The capstone ERD lists 15 columns including `action_taken`, `rejection_reason`, `feedback_timestamp`, `review_timestamp`, `used_for_training`, etc. We persist all of them. |
| **V3** | `action_taken` Postgres ENUM (accept/reject/correct/override/reassign) used by both team-leader and engineer actions | `app/Http/Controllers/Api/AssignmentController.php` | Capstone DFD 4.0 specifies three branches for team leader (accept/reject/correct) AND three modes for engineer (Approve / Override / Reassign). All six write to the matching enum value in `tbl_feedback` plus update `tbl_assignments.action_status`. |
| **V4** | Additional `tbl_incident_attachments` table for photo proof | `database/migrations/2026_04_01_000007_create_tbl_incident_attachments_table.php` | Capstone DFD 5.6 ("Attach Photo Proof - Optional") describes file uploads, but the strict capstone ERD doesn't include the table. We added it as a faithful extension so the on-site repair workflow is testable end-to-end. |

**Approved changes vs. strict capstone ERD:**

The SQL ENUM types implement the exact role/status sets from the capstone:

- `user_role`: `('customer','administrator','engineer','offsite_staff')` matches §1.0 user types
- `incident_status`: `('open','in_progress','resolved','rejected')` matches §3.5 Likert evaluation
- `availability_status`: `('available','on_duty','unavailable','on_break')` matches §3.5 Evaluation Q5
- `feedback_action`: `('accept','reject','correct','override','reassign')` matches §4.0 three-branch + three-mode
- `assignment_action`: full enum needed for tracking each transition's outcome

---

**End of document.** For questions or corrections, file an issue or message
the maintainer.

Last updated: 2026-04 (capstone defense version)
