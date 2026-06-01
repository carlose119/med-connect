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

Roll back the 16 agenda-core migrations (15 from PR 1+2 + Sanctum personal_access_tokens):

```bash
php artisan migrate:rollback --step=16
```

## RBAC, Sanctum, and the Appointment State Machine (PR 3)

PR 3 adds three things on top of the PR 1+2 domain:

- **Laravel Sanctum** for API token auth. The `User` model now uses
  the `HasApiTokens` trait and the `personal_access_tokens` table is
  published. Personal access tokens are issued in PR 4 (API routes).
- **RBAC predicates on `User`**: `isAdmin()`, `isDoctor()`, `isPatient()`.
  The `role` column is fillable; the `UserFactory` exposes `admin()`,
  `doctor()`, `patient()` states.
- **Appointment state machine** on top of `spatie/laravel-model-states` 2.13.
  The `state` column is cast to the abstract state class
  `App\States\Appointment\AppointmentState` (a PHP backed enum lives next
  to it for reference / morph values). The five concrete states are
  `Pending`, `Confirmed`, `Completed`, `Cancelled`, `NoShow`. Allowed
  transitions:

  | From      | To         | Actor                              | Transition class                       |
  |-----------|------------|------------------------------------|----------------------------------------|
  | Pending   | Confirmed  | assigned doctor                    | `App\States\Transitions\ConfirmAppointmentTransition`        |
  | Pending   | Cancelled  | assigned patient (≥24h before) / assigned doctor / admin | `App\States\Transitions\CancelAppointmentTransition` |
  | Confirmed | Completed  | assigned doctor                    | `App\States\Transitions\CompleteAppointmentTransition`       |
  | Confirmed | Cancelled  | assigned patient (≥24h before) / assigned doctor / admin | `App\States\Transitions\CancelAppointmentTransition` |
  | Confirmed | NoShow     | assigned doctor or admin           | `App\States\Transitions\MarkNoShowAppointmentTransition`     |

  Custom `Transition` classes receive the actor as the second positional
  argument to `$appointment->state->transitionTo(NextState::class, $user)`
  and enforce both the actor identity and (for patient cancellations)
  the 24h window. Domain exceptions thrown on rejection:

  - `App\Exceptions\Domain\UnauthorizedActorException` (HTTP 403)
  - `App\Exceptions\Domain\CancellationWindowViolationException` (HTTP 422)
  - `App\Exceptions\Domain\InvalidStateTransitionException` (HTTP 422, raised when transitioning out of a terminal state)

  Three policies (registered via `Gate::policy()` in `AppServiceProvider::boot()`)
  gate the resource layer:

  - `UserPolicy` — admin full; users view their own record.
  - `PatientPolicy` — admin full; doctor view; patient view/update their own.
  - `AppointmentPolicy` — admin full; assigned doctor view/update; assigned patient view and cancel (the 24h window is enforced by the transition, not the policy).

Run the PR 3 test slice on its own:

```bash
vendor/bin/pest --filter='AppointmentStateTest|UserRolesTest|PolicyTest'
```

## Environment

- PHP 8.4+ (the project pins to features available in 8.4 — e.g. property hooks, asymmetric visibility)
- Composer 2.8+
- Node 20.19+ (Vite 7 requires it) or 22.12+
- MariaDB 10.11+ or PostgreSQL 14+ for the agenda-core PR; greenfield before that needs no DB.
