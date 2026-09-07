# IMRAWS-NLP

**Incident Management and Routing App and Web System for Water Services
Using Natural Language Processing (NLP)**

A capstone project for the Bachelor of Science in Information Technology,
Global Reciprocal Colleges, Caloocan City. The system automates the
classification, severity assessment, and routing of water-service
customer complaints using a custom-trained ML model, with role-based
access for four stakeholder types (Customer, Offsite Staff, Engineer,
Administrator).

---

## Repository Layout

```
imraws/
├── ai-nlp/                FastAPI NLP microservice (Python 3.11+)
│   ├── app/               Source code (routers, services, models)
│   ├── data/              Training data (508 Taglish complaints)
│   ├── models/            Trained TF-IDF + SVM + pickled (96.1% acc)
│   ├── SETUP.md           How to install + run the NLP service
│   ├── Dockerfile         Container build (for production)
│   └── requirements.txt   Python dependencies
│
├── imraws-backend/        Laravel 12 backend + Blade web portal
│   ├── app/               PHP source (controllers, models, services)
│   ├── database/          Migrations + seeders
│   ├── docs/              FLUTTER_API_SPEC.md + other internals
│   ├── resources/views/   Blade templates
│   ├── routes/            api.php (JWT) + web.php (session)
│   └── SETUP.md           How to install + run the Laravel backend
│
├── website-ui-only/       Reference design mockups (gitignored)
│   └── .gitkeep           (these were converted to Blade views)
│
└── IMRAWS-NLP-CAPSTONE-DOCUMENT-...docx   Approved capstone document
```

---

## Architecture (one glance)

```
┌────────────────────────┐        ┌──────────────────────────────┐
│  Flutter Mobile App    │  HTTP  │  Laravel Backend API         │
│  (Customers +          │  JWT   │  (PHP / PostgreSQL)         │
│   Offsite Staff)       │◄──────►│  Port 8000                  │
│  parking lot:          │        │  - Auth (JWT) + RBAC         │
│  docs/FLUTTER_API_SPEC │        │  - Incidents, assignments   │
└────────────────────────┘        │  - Reports (DFD 6.0)         │
                                   │  - Web portal (Blade)        │
┌────────────────────────┐        │                              │
│  Web Portal (Blade)    │ session│                              │
│  (Engineers + Admins)  │◄──────►│                              │
│  URL: /login           │        └─────┬──────────────┬────────┘
└────────────────────────┘              │              │
                                       │              │
                              ┌────────▼─────┐ ┌──────▼───────┐
                              │ FastAPI NLP  │ │  PostgreSQL   │
                              │ Service      │ │  16.15        │
                              │ Port 8001    │ │  Database:    │
                              │ TF-IDF + SVM │ │  imraws_db    │
                              │ + RoBERTa    │ │  8 capstone   │
                              │ sentiment    │ │  tables +     │
                              │ + composite  │ │  5 ENUMs      │
                              │ score        │ └───────────────┘
                              └──────────────┘
```

---

## Quick Start (Assumes SETUP.md has been followed)

Two terminals, two services.

### Terminal 1 - NLP Service

```powershell
cd ai-nlp
ai_nlp_env\Scripts\python -m app.main
```

Wait for `Application startup complete.` (30-60s first run).

### Terminal 2 - Laravel Backend

```powershell
cd imraws-backend
php artisan serve --host=127.0.0.1 --port=8000
```

### Open the Web Portal

http://127.0.0.1:8000/login

| Email | Password | Role |
|---|---|---|
| `admin@imraws.local` | `admin123` | Administrator |
| `engineer.axel@imraws.local` | `engineer123` | Engineer |
| `tl.metering@imraws.local` | `staff123` | Offsite Staff (Team Leader) |
| `robert.j@example.com` | `customer123` | Customer |

---

## Full Setup Guides

If you are installing from scratch on a new Windows machine:

| Component | Guide |
|---|---|
| PHP 8.2 (XAMPP) | see [§1 of `imraws-backend/SETUP.md`](imraws-backend/SETUP.md#1-install-php-82-via-xampp) |
| Postgres PHP extensions | see [§2](imraws-backend/SETUP.md#2-enable-postgres-php-extensions) |
| Composer | see [§3](imraws-backend/SETUP.md#3-install-composer) |
| PostgreSQL 16 | see [§4-6](imraws-backend/SETUP.md#4-install-postgresql-16) |
| Laravel (full) | [`imraws-backend/SETUP.md`](imraws-backend/SETUP.md) |
| NLP microservice | [`ai-nlp/SETUP.md`](ai-nlp/SETUP.md) |

---

## Documentation Map

| Doc | Purpose |
|---|---|
| `IMRAWS-NLP-CAPSTONE-DOCUMENT-...docx` | **Approved capstone document** (read this for system design intent) |
| `imraws-backend/SETUP.md` | Full Laravel + Postgres + PHP + Composer installation walkthrough |
| `ai-nlp/SETUP.md` | NLP service installation (short) |
| `imraws-backend/docs/FLUTTER_API_SPEC.md` | Frozen API spec for the Flutter mobile app (parked, not implemented) |
| `imraws-backend/SETUP.md` §15 | API quick reference (all endpoints) |
| `imraws-backend/SETUP.md` §16 | Common gotchas |

---

## Roadmap

Status of each major component:

| Phase | Component | Status |
|---|---|---|
| 0 | Git repo + .gitignore | DONE |
| 1 | Laravel skeleton + Postgres | DONE |
| 2 | 8 capstone database tables | DONE |
| 3 | Eloquent models | DONE |
| 4 | JWT auth + 4-role RBAC | DONE |
| 5 | NLP integration (with `composite_score`) | DONE |
| 6 | AI routing + 3-action team leader + 3-mode engineer | DONE |
| 7 | 5 admin/support controllers + photo proof | DONE |
| 8 | Blade web portal (6 mockup screens) | DONE |
| 9 | Demo seeder + end-to-end smoke test | DONE |
| 10 | Flutter mobile app | **PARKED** - spec frozen, no impl |
| -- | Production deploy (Oracle Cloud VPS) | OUT OF SCOPE - dev only |

---

## License & Attribution

Capstone research, College of Computer Studies, Global Reciprocal
Colleges, Caloocan City.

Team:

- Cabalse, John Lowie
- Iray, Jojit Rhey D.
- Labutap, Christine Zairah
- Miguel, Pamela Joy D.
- Susi, Jade Zyrhel

Adviser: Eduardo S. Rodrigo

April 2026.
