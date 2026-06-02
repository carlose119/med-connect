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

## Agenda services and actions (PR 4)

PR 4 lands the booking write path. The layered split from the design is:

- **`App\Services\DoctorAvailabilityService`** — pure slot generator.
  Signature: `slots(int $doctorId, CarbonInterface $date, ?string $tz = null): array`.
  Reads the doctor's recurring rules and same-day overrides, subtracts
  slots already consumed by non-cancelled appointments, filters slots
  inside the 2h anticipación window, and returns
  `array<int, array{start: CarbonImmutable, end: CarbonImmutable}>`
  in the requested timezone (default = `config('app.timezone')`).
  No DB writes, no `auth()`.

- **`App\Actions\BookAppointmentAction`** — the orchestrator. Signature:
  `__invoke(int $doctorId, CarbonInterface $start, int $patientId, ?string $notes = null): Appointment`.
  Wraps everything in `DB::transaction`, takes `lockForUpdate()` on
  the `doctor_schedules` rows for the slot's day-of-week, then runs
  three guards in order:
  1. **Slot exists and is not booked** → `DoctorAvailabilityService::slots()`
  2. **2h anticipación** → `start >= now() + 2h`
  3. **No patient overlap** → no non-cancelled appointment intersects `[start, end)`
  The DB unique index `(doctor_id, start_time, cancelled_marker)` is
  the second line of defense; if a race slips past `lockForUpdate()`,
  the INSERT raises a `QueryException` and the action re-throws as
  `SlotNotAvailableException`.

- **`App\Actions\CancelAppointmentAction`** — full implementation
  (was a stub in PR 3). Signature:
  `__invoke(int $appointmentId, User $actor, ?string $reason = null): Appointment`.
  Runs the 24h anticipación check for patient actors (doctors and
  admins bypass), records `cancellation_reason`, and dispatches the
  PR 3 `CancelAppointmentTransition` for the actor check + state change.

- **`App\Actions\EnsureMedicalHistoryAction`** — idempotent helper.
  Signature: `__invoke(int $patientId): MedicalHistory`. Returns the
  patient's `MedicalHistory`, creating one if missing. Called from
  `BookAppointmentAction` after the appointment is inserted so a
  first-time patient always has a history committed with their first
  appointment.

Three new domain exceptions in `App\Exceptions\Domain\`, each
implementing `httpStatus(): int`:

| Exception                              | HTTP | Triggered by                                        |
|----------------------------------------|------|-----------------------------------------------------|
| `SlotNotAvailableException`            | 409  | Slot not in published list, or unique-index rejection |
| `AnticipationWindowViolationException` | 422  | `start < now() + 2h`                                |
| `PatientOverlapException`              | 422  | Patient has overlapping non-cancelled appointment    |

`CancellationWindowViolationException` (422) is reused from PR 3 for
the patient cancel path. The full list lives in
`openspec/changes/agenda-core/specs/agenda/spec.md`.

### Running the PR 4 tests

Run the full suite (the PR 4 slice + everything from PR 1+2+3):

```bash
vendor/bin/pest
```

Run only the PR 4 service / exceptions / booking / cancel slice:

```bash
vendor/bin/pest --filter='DoctorAvailabilityServiceTest|DomainExceptionTest|BookAppointment|CancelAppointment|EnsureMedicalHistory'
```

Run the concurrent double-book persistence test (requires MariaDB/MySQL;
skipped on SQLite):

```bash
DB_CONNECTION=mariadb vendor/bin/pest --filter=ConcurrentDoubleBookTest
```

### Smoke testing the booking pipeline on the dev MariaDB

After `migrate:fresh --seed`, exercise the new actions from tinker:

```php
// Slot generation (the seeded doctor publishes Monday 09:00-12:00, 30-min slots)
$svc = app(\App\Services\DoctorAvailabilityService::class);
$svc->slots(1, now()->next(\Carbon\Carbon::MONDAY));

// Booking (creates a pending appointment, idempotent on the medical history)
$action = app(\App\Actions\BookAppointmentAction::class);
$appt = $action(1, now()->addDays(8)->next(\Carbon\Carbon::MONDAY)->setTime(11, 0), 1);
echo $appt->state::$name; // 'pending'
```

## Filament v5 panels (PR 5)

PR 5 wires the admin and doctor back-offices on top of the PR 1–4 domain.
Two `PanelProvider` classes mount two distinct Filament panels; both
share the same `User` model and the same `canAccessPanel(Panel $panel)`
method that consults the role predicates from PR 3.

| Path           | Panel id  | Role required | `UserResource` / `SpecialtyResource` mounted? | Login              |
|----------------|-----------|---------------|------------------------------------------------|--------------------|
| `/admin`       | `admin`   | `admin`       | yes (full CRUD on users + specialties)         | Filament built-in  |
| `/doctor`      | `doctor`  | `doctor`      | no (empty dashboard for v1)                    | Filament built-in  |

### What lives on the admin panel

- **Dashboard** — Filament default + `AccountWidget` + `FilamentInfoWidget`.
- **`App\Filament\Resources\Users\UserResource`** — list / create / edit users.
  The create form has `name`, `email`, `password` (required on create,
  hidden on edit), `role` (`admin | doctor | patient`), and **conditional**
  `specialty_id` and `license_number` that only appear when
  `role === 'doctor'`. The CreateUser page wraps the user + doctor
  inserts in a single `DB::transaction` so a half-created doctor never
  leaks an orphan user account.
- **`App\Filament\Resources\Specialties\SpecialtyResource`** — minimal
  list / create / edit for the `specialties` lookup. The slug is
  auto-generated from the name on create; `is_active` toggles the row
  out of the doctor-creation `Select`.

### What lives on the doctor panel

- **Dashboard** only. The doctor "view my appointments" UI is a future
  change (`agenda-doctor-ui`); for PR 5 the doctor just logs in and
  lands on an empty dashboard. Login still runs through the same
  `canAccessPanel` gate so non-doctors get a 403.

### How `canAccessPanel` is wired

`App\Models\User` implements `Filament\Models\Contracts\FilamentUser`.
The method is a `match` on `$panel->getId()`:

```php
public function canAccessPanel(Panel $panel): bool
{
    return match ($panel->getId()) {
        'admin'  => $this->isAdmin(),
        'doctor' => $this->isDoctor(),
        default  => false,
    };
}
```

The four scenarios from `users-roles/spec.md` § Filament Panel Access
are covered by `tests/Feature/Auth/FilamentPanelAccessTest.php`
(4 passing cases on both SQLite in-memory and MariaDB 10.11).

### Visiting the panels locally

After `php artisan migrate:fresh --seed`, the seeder creates one
admin user. Boot the dev server and open both logins:

```bash
# 1) Make sure the assets are built (one-shot)
npm install
npm run build

# 2) Boot Laravel
php artisan serve --host=127.0.0.1 --port=8000

# 3) Open in a browser
#    http://127.0.0.1:8000/admin   →  log in as the seeded admin
#    http://127.0.0.1:8000/doctor  →  log in as a user with role=doctor
#                                    (the seeded `doctor@med-connect.local`
#                                    is the doctor, NOT the admin)
```

Expected behaviour:

- Admin → `/admin` → 200, dashboard with the Users + Specialties nav.
- Doctor → `/admin` → 403 (denied by `canAccessPanel`).
- Admin → `/doctor` → 403 (denied by `canAccessPanel`).
- Doctor → `/doctor` → 200, empty dashboard.
- Patient → both panels → 403.
- Unauthenticated → both panels → redirected to `/admin/login` (or `/doctor/login`).

If the admin creates a new user with `role = doctor` and fills the
specialty + license number, the User row and the Doctor row land in
the same transaction. The new doctor can then log into `/doctor`
immediately with the password the admin typed in.

### Run the PR 5 test slice

```bash
# All 42 cases on SQLite (default), 44 on MariaDB
vendor/bin/pest

# Just the panel-access matrix
vendor/bin/pest --filter=FilamentPanelAccessTest
```

### Known PR 5 caveats

- **Vite 7 + Node 20.16** prints a non-blocking warning that Node 20.19+
  is required; the build still succeeds. Upgrade Node at your leisure.
- **`Specialty::where('is_active', true)`** in `UserForm` requires the
  PR 2 migration to have actually been applied. If you see "no
  specialties in the dropdown" after seeding, run
  `php artisan migrate:fresh --seed` to repopulate.
- **Filament v5 third-party plugins** (e.g. `filament/shield`) are NOT
  installed. The `canAccessPanel` gate is sufficient for PR 5; richer
  per-resource authorization lands in a future `rbac-advanced` change.

## Status — agenda-core

**Feature-complete pending `sdd-verify`.** All five PRs of the
chained split are landed on `feat/filament-panels` (the final branch
in the chain) and the test suite is green:

| PR  | Scope                                                          | Branch              | Review |
|-----|----------------------------------------------------------------|---------------------|--------|
| 1   | Skeleton (`composer create-project` + `.atl/` + `openspec/`)   | (merged to main)    | 0 LOC  |
| 2   | 13 migrations + 12 Eloquent models + 13 factories + seeder     | (merged to main)    | ~1500  |
| 3   | Sanctum + RBAC + state machine + 3 Gate policies               | (merged to main)    | ~600   |
| 4   | 1 service + 3 actions + 3 domain exceptions                   | (merged to main)    | ~1230  |
| 5   | Filament v5 panels + `UserResource` + `SpecialtyResource`      | `feat/filament-panels` | ~700 (excl. `composer.lock` / fonts) |

The 6 proposal smoke tests + the 4 panel-access scenarios from
`users-roles/spec.md` are all passing. Next step is
`sdd-verify` (a separate agent audits the red→green pairs in
`git log`, the test count, the route map, and the manual-visit
checklist above), then `sdd-archive` to sync the delta specs into
`openspec/specs/` and move the change folder to
`openspec/changes/archive/`.

## Environment

- PHP 8.4+ (the project pins to features available in 8.4 — e.g. property hooks, asymmetric visibility)
- Composer 2.8+
- Node 20.19+ (Vite 7 requires it) or 22.12+
- MariaDB 10.11+ or PostgreSQL 14+ for the agenda-core PR; greenfield before that needs no DB.
