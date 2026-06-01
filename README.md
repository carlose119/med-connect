# med-connect

A medical clinic and appointment platform built on Laravel 13, with FilamentPHP v5
panels for the admin/doctor side and a patient-facing API.

## Stack

- **Backend:** Laravel 13 (PHP 8.4+), Pest 4 for tests
- **Admin/Doctor panel:** FilamentPHP v5 (installed in PR 5)
- **API auth:** Laravel Sanctum (installed in PR 3)
- **Database:** PostgreSQL preferred, MariaDB fallback for local Laragon
- **Frontend assets:** Vite 7 + Tailwind v4

## Source of truth

The `openspec/` directory is the contract for every change.

- `openspec/AGENTS.md` — workflow, preflight values, approved architectural decisions
- `openspec/changes/<change-id>/` — proposal, specs, design, tasks for the active change
- `openspec/specs/` — cumulative specs across all archived changes

## Dev quickstart

Install everything from scratch (one-shot):

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Run the dev stack (Laravel + queue + Vite, all together):

```bash
composer dev
```

Run the test suite (Pest 4):

```bash
php artisan test
```

Run the Vite dev server on its own:

```bash
npm run dev
```

Build production assets:

```bash
npm run build
```

## Database

Local dev defaults to **MariaDB 10.11** via Laragon. `.env.example` already
points at it (`DB_CONNECTION=mariadb`, host `127.0.0.1`, db `med_connect`).
PostgreSQL remains supported at the application layer; switch
`DB_CONNECTION=pgsql` in `.env` and create a `med_connect` database to use it.

Seed the base data (one admin, one specialty, one doctor, one patient, one
pending appointment):

```bash
php artisan migrate:fresh --seed
```

Roll back the 13 agenda-core migrations:

```bash
php artisan migrate:rollback --step=13
```

## Environment

- PHP 8.4+ (the project pins to features available in 8.4 — e.g. property hooks, asymmetric visibility)
- Composer 2.8+
- Node 20.19+ (Vite 7 requires it) or 22.12+
- MariaDB 10.11+ or PostgreSQL 14+ for the agenda-core PR; greenfield before that needs no DB.
