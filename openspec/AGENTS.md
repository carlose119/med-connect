# OpenSpec — med-connect

This directory is the source of truth for Spec-Driven Development (SDD) on med-connect.
No application code lives here. Code is generated under `app/`, `database/`, `routes/`, etc. by the `sdd-apply` phase.

## SDD workflow (contract)

Every change goes through these phases, in order. Do not skip. Do not start `apply` without a fully approved `tasks.md`.

1. **proposal** (`/sdd-new`, `sdd-propose`) — Intent, scope, and approach.
2. **specs** (`/sdd-spec`) — Delta specs with requirements and scenarios (ADDED / MODIFIED / REMOVED).
3. **design** (`/sdd-design`) — Technical design and architecture approach.
4. **tasks** (`/sdd-tasks`) — Ordered, checkable implementation tasks.
5. **apply** (`/sdd-apply`) — Code is implemented strictly from tasks.
6. **verify** (`/sdd-verify`) — Tests executed to prove implementation matches specs, design, and tasks.
7. **archive** (`/sdd-archive`) — Delta specs are synced into `openspec/specs/` and the change folder is moved to `openspec/changes/archive/`.

## Preflight values (session-wide)

These values are global for the entire med-connect session. Pass them verbatim to every future sub-agent.

- **execution_mode**: `interactive` — phases stop and ask for confirmation before moving on.
- **artifact_store**: `openspec` — all SDD artifacts live under `openspec/`.
- **delivery_strategy**: `ask-always` — stop and ask the user if the forecast exceeds the review budget.
- **review_budget_lines**: `400` — soft cap per PR / per apply slice.

## Layout

```
openspec/
├── AGENTS.md              # this file — workflow contract
├── changes/               # active and proposed changes
│   └── archive/           # completed/archived changes
├── specs/                 # cumulative specs (source of truth across changes)
└── config.yaml            # project config (testing, conventions) — created on first sdd-init run
```

## Change convention

Each change under `openspec/changes/<change-id>/` follows this shape:

```
<change-id>/
├── proposal.md            # why, scope, approach, affected modules
├── specs/                 # delta specs (ADDED / MODIFIED / REMOVED)
├── design.md              # technical design
└── tasks.md               # ordered, checkable tasks with TDD steps
```

`<change-id>` uses kebab-case, prefixed by domain area when useful, e.g. `agenda-core`, `auth-rbac`, `medical-notes`.

## Approved architectural decisions (PRD)

These are global and must be respected by every change:

- **Appointments state machine** — `pending | confirmed | completed | cancelled | no_show` (terminals: `completed`, `cancelled`, `no_show`). Implemented with `spatie/laravel-model-states` backed by a PHP Enum.
- **Doctor schedule** — `doctor_schedules` (recurring rules per `day_of_week`) + `doctor_schedule_overrides` (point-in-time exceptions: `block` or `extra_availability`). Slots are generated on the fly, never persisted.
- **`appointments`** — `start_time` and `end_time` (both stored as UTC). Unique partial index `(doctor_id, start_time) WHERE status != 'cancelled'` to prevent double booking.
- **Timezones** — UTC in DB; consultorio timezone for display; mobile app timezone resolved later.
- **`medical_notes`** — append-only. The table has no `updated_at`; amendments are modeled via a `corrects_note_id` FK pointing at the previous note.
- **`audit_logs`** — dedicated table for admin actions.
- **`medical_attachments`** and **`prescription_items`** — normalized tables. NOT JSON columns.
- **Anticipación mínima para reservar** — 2 hours.
- **Ventana de cancelación del paciente** — 24 hours.
- **No auto-complete in v1** — no scheduler runs the state machine forward. Emails fire on every state transition that affects the patient.

## Stack (from approved PRD)

- **Backend**: Laravel 13 (PHP 8.4+)
- **Admin/Doctor panel**: FilamentPHP v5 (Livewire + Alpine + Tailwind)
- **API auth**: Laravel Sanctum
- **DB**: PostgreSQL (preferred). MariaDB is the local fallback available via Laragon if Postgres is not running.
- **Patient web**: Blade + Tailwind consuming the API (or a chosen JS framework later)
- **Mobile**: React Native + Expo (separate codebase, out of this repo for v1)
- **Testing**: Pest 3 (modern Laravel default)

## Local environment (Laragon, Windows)

Detected on 2026-06-01:

- PHP 8.4.4 NTS (Laragon: `C:\laragon\bin\php\php-8.4.4-nts-Win32-vs17-x64\php.exe`) with Xdebug 3.4.1
- Composer 2.8.11
- Node 20.16.0 / npm 10.8.1
- PostgreSQL 14.5 (psql client) and MariaDB 10.11.9 (mysql client) — both available via Laragon
- Laravel installer: **not** on PATH. Use `composer create-project laravel/laravel:^13 .` instead of `laravel new`.

## Sub-agent contract

When launching any sub-agent for med-connect work, always pass:

1. This file's path (`openspec/AGENTS.md`) so the sub-agent reads the workflow contract.
2. The current `change-id` folder path (e.g. `openspec/changes/agenda-core/`).
3. The preflight values from above.
4. Relevant skills from `.atl/skill-registry.md` (only if a real match exists for the task; default to `skill_resolution: none` for greenfield work).
