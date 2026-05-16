# StudentsHub DIU — Project Memory

Student services form-tracking portal for Daffodil International University.
Students submit requests (academic certifications, complaints, IT support, etc.); department admins triage and resolve them with full audit trail and SLA tracking.

## Stack

- **Backend:** Laravel 11 (PHP 8.4) + Sanctum, dompdf for PDF generation, custom Authenticate middleware (returns null `redirectTo` so API requests get clean 401s)
- **Frontend:** Next.js 14 App Router + TypeScript strict, Tailwind, TanStack Query v5, Zustand (persist) for auth, react-hook-form, axios, lucide-react, date-fns. Inline SVG charts (Donut + LineChart, no third-party charting lib)
- **DB:** PostgreSQL 16 + pgvector. Heavy use of `jsonb` for dynamic form payloads, ENUM check constraints for status
- **Cache/Queue/Sessions:** Redis 7
- **Infra:** Docker Compose (nginx, php-fpm, queue worker, scheduler, postgres, redis, frontend, optional ai service)

## Repo layout

```
studenthub/                     ← git root
└── studentshuib/               ← application root, most commands run from here
    ├── backend/                ← Laravel
    ├── frontend/               ← Next.js
    ├── docker/                 ← Dockerfiles + nginx config
    └── docker-compose.yml
```

## Critical conventions

### Next.js route groups
The `(auth)`, `(student)`, `(admin)` route groups are invisible in URLs. For `/admin/X` URLs, the literal `admin/` segment MUST live inside `(admin)/`:

- `app/(admin)/admin/dashboard/page.tsx` → `/admin/dashboard` ✅
- `app/(admin)/dashboard/page.tsx` → collides with student `/dashboard` ❌

Student URLs have no `/student/` prefix. Use `/dashboard`, `/submissions`, `/forms`, `/profile` for student routes.

### TypeScript strict gotchas
- `Record<string, X>` requires actual index signatures — interfaces do NOT satisfy. Widen `body` params on api.ts to `object` when passing typed form payloads.
- `useToast()` is **positional**: `toast(msg: string, type?: ToastType)`. NOT an object form. Wrong: `toast({ title, variant })`.
- `vitest.config.ts` and `src/tests/**` are excluded from `tsconfig.json` so production `next build` doesn't typecheck the test runner config (vite plugin type identity collisions).

### Build + deploy

```bash
cd studentshuib

# Frontend code changes — image is built (not volume-mounted)
docker compose build frontend
docker compose up -d frontend
docker compose restart nginx       # nginx caches container DNS, required after recreate

# Backend code changes — OPcache has validate_timestamps=Off in prod
docker compose restart php
docker compose restart queue       # if Job classes changed
docker compose restart scheduler   # if scheduled commands changed
```

### Env files (both gitignored)
Two `.env` files must stay in sync:

- `studentshuib/.env` — docker-compose substitution (DB_PASSWORD, REDIS_PASSWORD, APP_KEY, APP_PORT)
- `studentshuib/backend/.env` — Laravel runtime (same DB_PASSWORD, REDIS_PASSWORD, APP_KEY plus app-specific vars)

`docker-compose.yml` uses `${VAR:?VAR must be set}` syntax — compose REFUSES to start without env values. No fallback defaults (they leak via git history).

`.env.example` files use `your_*_here` placeholders, never real-looking strings like `sh_secret`.

## Domain model

### Roles (5)
- `student` — submits requests, views own submissions
- `admin` — department-scoped; reviews submissions assigned to their dept
- `dept_head` — manages a department + escalation handler
- `super_admin` — full system access (settings, users, all submissions)
- `management` — read-only leadership view (dashboard, reports, no mutations) [TODO: mutation endpoints not yet locked down]

### Self-protection invariants (UserController)
- super_admin CANNOT delete / deactivate / demote themselves
- System NEVER allows operations that would leave zero active super_admins
- Enforced via shared private predicate `isLastActiveSuperAdmin(int $userId): bool`
- Frontend mirrors with disabled buttons + `(you)` label on the current user's row

### Submission status flow
```
draft → submitted → routed → in_review
                            ↓ ↑
                    action_required
                            ↓
       (approved | rejected | returned | escalated) → completed
```

Resubmit (`PUT /student/submissions/{ref}`) is allowed from: `returned`, `action_required`, `draft`.

### Notification deep links
Use `submission_reference_no` (string), not `submission_id` (int). Backend `NotificationController@index` eager-loads `submission:id,reference_no` and projects it as a top-level field, then `unsetRelation('submission')` to keep the response flat.

## Common commands

| Task | Command |
|------|---------|
| Tail logs | `docker compose logs <service> --tail 30 -f` |
| Run migration | `docker compose exec php php artisan migrate` |
| Open tinker | `docker compose exec php php artisan tinker` |
| DB shell | `docker compose exec postgres psql -U sh_user -d studentshuib` |
| Redis CLI | `docker compose exec redis redis-cli -a "$REDIS_PASSWORD"` |
| Generate APP_KEY | `docker compose exec php php artisan key:generate --show` |
| Frontend rebuild | `docker compose build frontend && docker compose up -d frontend && docker compose restart nginx` |
| Backend hot-reload | `docker compose restart php` |
| Full reset | `docker compose down && docker compose up -d` |

## Outstanding (not blocking deploy)

- **Fix 4 — Anonymous submissions:** Keep `student_id` set, hide identity in admin views when `is_anonymous=true`. Right now anonymous submissions get orphaned from the student's portal.
- **Fix 6 — Management role lockdown:** Strip `management` from mutation endpoints (`PUT status`, `PUT assign`, `POST comments`, `POST documents`, `bulk-status`). Currently they can write but shouldn't.
- **Next.js 14 viewport metadata warnings** on `/submissions` and `/admin/submissions` — move `viewport` from the `metadata` export to its own `viewport` export. Cosmetic.

## Recent decisions / scars

- Force-pushed `git filter-repo --force` once; lost work because `--force` wiped reflog and the in-repo backup was deleted with `.git`. Never run filter-repo without an out-of-repo `cp -R` backup first.
- Postgres password rotation requires `ALTER USER` SQL — editing `.env` alone won't update an already-initialized DB user's password.
- Redis password rotation: `CONFIG SET requirepass <new>` then `CONFIG REWRITE` to persist across container restart.
- Both initial leaked passwords (`sh_secret`, `sh_redis_secret`) have been rotated and scrubbed from history. APP_KEY was never committed.
