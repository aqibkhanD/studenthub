# StudentsHub — Setup Guide

Student Services Form Tracking System for Daffodil International University (DIU).  
Stack: Laravel 11 · Next.js 14 · PostgreSQL 16 · Redis · Docker Compose

---

## Project Structure

```
studentshuib/
├── docker-compose.yml          Main Docker stack
├── .env.example                Copy to .env and configure
├── backend/                    Laravel 11 API
│   ├── app/
│   │   ├── Http/Controllers/Api/   API controllers
│   │   ├── Models/                 Eloquent models
│   │   ├── Services/               Business logic (Submission, File, SLA, SMS, Audit)
│   │   ├── Jobs/                   Queue jobs (notifications, SLA monitor)
│   │   └── Http/Middleware/        Auth, role-based access
│   ├── database/
│   │   ├── migrations/             PostgreSQL migrations (ordered 000001–000010)
│   │   └── seeders/                Departments, form types, form fields, admin users, SLA rules
│   └── routes/api.php              All API routes
├── frontend/                   Next.js 14 App Router + TypeScript
│   ├── src/app/(auth)/         Login, Register, Forgot/Reset Password
│   ├── src/app/(student)/      Student dashboard, forms, submissions, profile
│   ├── src/app/(admin)/        Admin inbox, review, analytics, settings
│   └── Dockerfile              Multi-stage Docker build (standalone output)
├── docker/
│   ├── nginx/default.conf      Reverse proxy (API + frontend)
│   ├── php/Dockerfile          PHP-FPM 8.3 image
│   ├── php/php.ini             PHP configuration
│   └── postgres/init.sql       pgvector / pgcrypto / pg_trgm extensions
└── ai-service/                 FastAPI stub (Phase 5+)
```

---

## Prerequisites

- Docker Desktop (Windows / Mac) or Docker Engine + Compose plugin (Linux)
- Git

---

## Quick Start

### Step 1 — Clone and configure

```bash
git clone <your-repo-url> studentshuib
cd studentshuib
cp .env.example .env
```

Open `.env` and set:
- `DB_PASSWORD` — choose a strong password
- `REDIS_PASSWORD` — choose a strong password  
- `APP_KEY` — leave blank for now; generated in Step 3

### Step 2 — Build and start containers

```bash
# First build takes ~3–5 minutes (downloads PHP extensions, npm packages)
docker compose up -d --build

# Check all services are healthy
docker compose ps
```

Expected services: `sh_nginx`, `sh_php`, `sh_queue`, `sh_scheduler`, `sh_postgres`, `sh_redis`, `sh_frontend`

### Step 3 — Initialise the application

```bash
# Generate the Laravel application key (updates APP_KEY in the running container)
docker compose exec php php artisan key:generate

# Install PHP packages (including dompdf for PDF reports)
docker compose exec php composer install --no-dev --optimize-autoloader

# Run all database migrations
docker compose exec php php artisan migrate

# Seed initial data (departments, form types, fields, admin users, SLA rules)
docker compose exec php php artisan db:seed

# Create the public storage symlink
docker compose exec php php artisan storage:link
```

### Step 4 — Verify

```bash
# API health check
curl http://localhost/api/v1/health
# Expected: {"status":"ok","version":"1.0.0"}

# Test admin login
curl -s -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"superadmin@diu.edu.bd","password":"Admin@1234!"}' | jq .token
```

Open http://localhost in your browser. The Next.js frontend is served through Nginx.

---

## Default Credentials — Change Before Production

| Role        | Email                        | Password      |
|-------------|------------------------------|---------------|
| Super Admin | superadmin@diu.edu.bd        | Admin@1234!   |
| Dept Admin  | registrar.staff@diu.edu.bd   | Admin@1234!   |
| Test Student| test.student@diu.edu.bd      | Student@1234! |

---

## Optional Services

### Email (development)

Start Mailpit to capture outgoing emails locally:

```bash
docker compose --profile dev up -d mailpit
```

View captured emails at http://localhost:8025.  
Update `.env`: `MAIL_HOST=mailpit` (already the default).

### AI Service (Phase 5+)

```bash
docker compose --profile ai up -d ai
```

---

## API Reference

All endpoints prefixed with `/api/v1/`. Authentication via `Authorization: Bearer {token}`.

| Group | Prefix | Access |
|---|---|---|
| Auth | `/auth/*` | Public / Authenticated |
| Student | `/student/*` | `student` role |
| Admin | `/admin/*` | `admin`, `dept_head`, `super_admin`, `management` |
| Super Admin | `/super/*` | `super_admin` only |

Key admin endpoints added in Phase 4:

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/admin/submissions/bulk-status` | Bulk change status (up to 100 submissions) |
| `GET`  | `/admin/submissions/export` | CSV download with current filters applied |
| `POST` | `/auth/forgot-password` | Request 6-digit OTP reset code |
| `POST` | `/auth/reset-password` | Reset password with OTP |

---

## Common Commands

```bash
# Tail logs
docker compose logs -f php
docker compose logs -f queue

# Run a specific migration
docker compose exec php php artisan migrate --path=database/migrations/2026_04_08_000010_create_password_reset_tokens_table.php

# Open Laravel Tinker (interactive REPL)
docker compose exec php php artisan tinker

# Clear all caches
docker compose exec php php artisan optimize:clear

# Run backend tests
docker compose exec php php artisan test

# Rebuild a single service after code changes
docker compose up -d --build php

# Stop all services
docker compose down

# Stop and wipe all data (destructive)
docker compose down -v
```

---

## Running Tests

### Backend (PHPUnit)

Tests use a **separate PostgreSQL database** (`studentshuib_test`) so they never touch production data.

**Step 1 — Create the test database (one-time setup)**

```bash
docker compose exec postgres psql -U sh_user -c "CREATE DATABASE studentshuib_test;"
docker compose exec php php artisan migrate --env=testing
```

**Step 2 — Run the full test suite**

```bash
docker compose exec php php artisan test
# or directly with phpunit:
docker compose exec php vendor/bin/phpunit
```

**Run a single test file**

```bash
docker compose exec php php artisan test tests/Feature/Auth/AuthTest.php
```

**Run a single test method**

```bash
docker compose exec php php artisan test --filter test_student_can_login_with_correct_credentials
```

**Run a named test suite**

```bash
docker compose exec php php artisan test --testsuite Feature
docker compose exec php php artisan test --testsuite Unit
```

**Notes**
- `QUEUE_CONNECTION=sync` in `phpunit.xml` means queued jobs (notifications) run inline — no separate worker needed for tests.
- `BCRYPT_ROUNDS=4` keeps password hashing fast in tests.
- `RefreshDatabase` wraps each test in a transaction rolled back after completion — tests are isolated.
- The test database requires the same connection credentials as the main database (reads from `.env` / Docker environment).

---

### Frontend (Vitest)

Tests live in `frontend/src/tests/` and use Vitest + Testing Library.

**Install test dependencies (one-time — already in package.json)**

```bash
docker compose exec frontend npm install
# or from the host:
cd frontend && npm install
```

**Run all frontend tests**

```bash
cd frontend && npm test
# or inside the container:
docker compose exec frontend npm test
```

**Watch mode (re-runs on file change)**

```bash
cd frontend && npm run test:watch
```

**Coverage report**

```bash
cd frontend && npm run test:coverage
# HTML report written to frontend/coverage/
```

**Test structure**

```
frontend/src/tests/
├── setup.ts                   # @testing-library/jest-dom setup
├── utils/
│   └── utils.test.ts          # toFieldKey, formatStatus, truncate, relativeTime
└── components/
    └── StatusBadge.test.tsx   # StatusBadge component renders correctly
```

---

## Phase Completion Checklist

### Phase 0 — Backend Infrastructure (complete)
- [x] Docker Compose stack (Nginx, PHP-FPM, PostgreSQL 16 + pgvector, Redis)
- [x] Laravel 11 project structure (hand-crafted — no Sail/Nova)
- [x] 10 database migrations (15+ tables, custom PostgreSQL ENUMs)
- [x] All 14 Eloquent models with relationships and appended accessors
- [x] RoleMiddleware (5 roles: student, admin, dept_head, super_admin, management)
- [x] AuditService (append-only log)
- [x] SendNotificationJob (in-app + SMS via SSL Wireless BD gateway)
- [x] FileUploadService (PDF, image, DOCX — 20 MB cap, UUID filenames)
- [x] SLA monitor command (escalation at configurable hour thresholds)
- [x] FastAPI AI scaffold (Phase 5+)

### Phase 1 — Full API (complete)
- [x] AuthController (login, register, profile, password change)
- [x] SubmissionController (student CRUD + comments)
- [x] AdminSubmissionController (review, approve, reject, return, assign, upload)
- [x] FormTypeController, DepartmentController, UserController
- [x] NotificationController (in-app feed, mark read)
- [x] DashboardController (summary stats)
- [x] AuditLogController (paginated log)
- [x] SmsService (SSL Wireless normalisation + retry)
- [x] SubmissionService (create, resubmit, cancel, status update, SLA calculation)
- [x] bootstrap/app.php (JSON error handling for all 4xx/5xx on API routes)

### Phase 2 — Frontend (complete)
- [x] Next.js 14 App Router scaffold with TypeScript, TanStack Query, Zustand
- [x] Login, Register pages
- [x] Student: dashboard, form catalogue, dynamic form renderer, submission list/detail
- [x] Student: notifications, profile, sidebar navigation
- [x] Admin: dashboard, submissions inbox, submission detail/review workflow
- [x] Admin: notifications, Analytics page (overview + department + SLA)
- [x] Super admin: departments, form types, users, audit logs settings pages
- [x] Frontend multi-stage Dockerfile; Nginx proxy updated
- [x] 404 and error boundary pages

### Phase 3 — Completeness (complete)
- [x] FormFieldSeeder (75 fields across 15 form types — makes forms functional)
- [x] SlaEscalationRuleSeeder (2 levels per department)
- [x] Student document upload endpoint (POST /student/submissions/{ref}/documents)
- [x] Password reset implementation (6-digit OTP, 15-minute expiry, email delivery)
- [x] Admin Analytics page with period selector (7/30/90 days)
- [x] Student profile page (update name/phone/program/semester + change password)
- [x] Global error boundary (global-error.tsx + error.tsx)

### Phase 4 — Production Readiness (complete)
- [x] Forgot password UI (/forgot-password with success state + link to reset)
- [x] Reset password UI (/reset-password with OTP input + auto-redirect)
- [x] "Forgot password?" link on login page
- [x] Admin bulk status change (checkboxes, floating action bar, up to 100 at once)
- [x] Admin CSV export (streamed from backend, respects all active filters)
- [x] Docker build context fixed (PHP Dockerfile now copies from project root)
- [x] PHP Dockerfile: added `composer dump-autoload --optimize`
- [x] docker-compose.yml: MAIL_* env vars added; shared storage volume; Mailpit dev service
- [x] Root `.env.example` with all required variables

### Phase 5 — Testing (complete)
- [x] `phpunit.xml` — PostgreSQL test database, fast bcrypt, sync queue, array mailer
- [x] `tests/TestCase.php` — base class with RefreshDatabase + helper factories (makeStudent, makeAdmin, makeSuperAdmin, makeManagement, makeDepartment, makeFormType, makeSubmission, submitForm)
- [x] `database/factories/UserFactory.php` — student (default), admin(dept), superAdmin, management, inactive, withPassword states
- [x] `tests/Feature/Auth/AuthTest.php` — login, register, profile, change password, logout
- [x] `tests/Feature/Student/SubmissionTest.php` — list form types, create/draft, view own vs other, comments, cancel draft, resubmit returned
- [x] `tests/Feature/Admin/AdminSubmissionTest.php` — inbox, dept scoping, filter by status, approve/reject/return (with comment requirements), CSV export
- [x] `tests/Feature/Admin/BulkStatusTest.php` — bulk approve, comment requirements, cross-dept skip, max-100 validation
- [x] `tests/Feature/Super/FormFieldTest.php` — CRUD, unique field_key per form type, same key across form types, reorder, access control
- [x] `tests/Feature/RoleAccessTest.php` — 401 for unauth, 403 for wrong role, inactive user blocked, super admin full access
- [x] `frontend/vitest.config.ts` + `src/tests/setup.ts` — Vitest + Testing Library + jsdom
- [x] `src/lib/utils.ts` — toFieldKey, formatStatus, truncate, relativeTime (extracted from page components)
- [x] `src/tests/utils/utils.test.ts` — 20 utility function tests
- [x] `src/tests/components/StatusBadge.test.tsx` — component renders all 11 statuses correctly

### Phase 6 — AI Integration (planned)
- [ ] FastAPI full implementation
- [ ] pgvector knowledge base with DIU policy documents
- [ ] Intent classification for auto-routing
- [ ] RAG search for student self-service

---

## Troubleshooting

**Container won't start / DB connection refused**  
Check `docker compose logs sh_postgres` — the database must pass its healthcheck before Laravel boots.

**`php artisan migrate` fails**  
Make sure `APP_KEY` is set (`php artisan key:generate`). Then retry migrate.

**Frontend shows blank page**  
Check `docker compose logs sh_frontend`. The Next.js standalone server must be running. Rebuild: `docker compose up -d --build frontend`.

**Emails not sending**  
In development, start Mailpit (`docker compose --profile dev up -d mailpit`) and set `MAIL_HOST=mailpit` and `MAIL_PORT=1025` in `.env`. All emails appear at http://localhost:8025.

**File uploads fail**  
Ensure `php artisan storage:link` has been run and the `sh_storage` Docker volume is mounted on both `php` and `queue` containers (already configured in docker-compose.yml).
