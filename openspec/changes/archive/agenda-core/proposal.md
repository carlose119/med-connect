# Proposal: agenda-core

## Intent

med-connect is greenfield. This change stands up the **runnable Laravel 13 skeleton**, the **13-table schema**, and the **booking core** (state machine + slot generation + transactional reservation) every later change depends on. Unlocks: admin creates a doctor → doctor publishes recurring schedule → patient (via tests; UI later) books a hard-unique slot. 2h anticipación and 24h cancel rules enforced at booking time.

## Scope

### In Scope

1. **Skeleton** — `composer create-project laravel/laravel:^13 .` + `git init` + first conventional commit.
2. **DB probe** — PG first, MariaDB fallback; documented in `README.md`.
3. **13 migrations** — `users`, `specialties`, `doctors`, `patients`, `doctor_schedules`, `doctor_schedule_overrides`, `appointments` (unique index — see §Constraint), `medical_histories`, `medical_notes` (no `updated_at`; `corrects_note_id` FK for amendments), `medical_attachments`, `prescriptions`, `prescription_items`, `audit_logs` (no `updated_at`).
4. **Models + factories** — 11 Eloquent models; `Appointment` uses `HasStates`.
5. **Sanctum** — `install:api`; tokens on `users`.
6. **RBAC** — `User::isAdmin()/isDoctor()/isPatient()` + Gate policies + `FilamentUser::canAccessPanel()`.
7. **State machine** — `spatie/laravel-model-states` 2.13; `AppointmentState` enum + 5 state classes + transitions.
8. **Filament v5** — two panels: `/admin` (`AdminPanelProvider`), `/doctor` (`DoctorPanelProvider`); minimal `UserResource` (admin creates doctors).
9. **Pest 3 (dev)** — `tests/Unit`, `tests/Feature`, `tests/Pest.php`. Strict TDD.
10. **`DoctorAvailabilityService`** — `(doctorId, date, tz) → array<{start,end}>` from recurring rules + overrides − booked slots. Pure, no persistence.
11. **`BookAppointmentAction`** — `($doctorId, $start, $patientId)` → `Appointment` in `pending`. Validates: in published slots, free, `start >= now+2h`, no patient overlap. Transactional with `lockForUpdate()`. Domain exceptions → 409/422.
12. **6 Pest tests** — slot gen, override, happy book, double-book 409, 2h window 422, transition matrix.

### Constraint (DB-agnostic intent, DB-specific at apply)

At most one non-cancelled appointment per `(doctor_id, start_time)`.
- **Postgres**: partial unique index `WHERE status <> 'cancelled'`.
- **MariaDB/MySQL 8**: virtual generated column `cancelled_marker` (id when cancelled, NULL otherwise) + unique on `(doctor_id, start_time, cancelled_marker)`. NULLs allowed → cancelled excluded.

### Packages

`laravel/framework ^13` · `laravel/sanctum ^4` · `spatie/laravel-model-states ^2.13` · `filament/filament ^5` · `pestphp/pest ^3` + `pest-plugin-laravel ^3` (dev).

### Out of Scope (deferred)

Patient Blade portal (`patient-portal`) · Mobile API / RN+Expo (`mobile-api`) · Email templates on transitions (Notifiable + DB queue wired here; templates in `notifications`) · Clinical notes/attachments/prescriptions UI + audit-log viewer (`clinical-records`, `admin-audit-ui`) · Walk-in history (RF-3.1.b) (`clinical-records`) · Filament Shield / role-permission plugin (`rbac-advanced`).

## Approach (sdd-apply order)

1. Skeleton commit. 2. DB probe + `.env` choice in README. 3. Pest scaffold (config + one passing sanity test). 4. Migrations + models + factories (driver-aware unique index inside). 5. Sanctum + RBAC. 6. State machine (TDD, red per transition). 7. `DoctorAvailabilityService` (TDD). 8. `BookAppointmentAction` + domain exceptions (TDD, happy + each failure). 9. Filament install + two panels + `UserResource`. 10. Final smoke.

`tasks.md` MUST forecast LOC and split into chained PRs (skeleton / migrations-models / state-machine / booking / filament) per `delivery_strategy: ask-always` and the 400-line review budget.

## Affected Areas (new)

`composer.json` (4 runtime + 2 dev) · `app/Models` (11), `app/States`, `app/Services`, `app/Actions`, `app/Exceptions/Domain`, `app/Policies` · `app/Filament/{Admin,Doctor}PanelProvider`, `app/Filament/Resources/UserResource` · `database/migrations` (13), `database/factories` (11) · `tests/{Pest.php,Unit/*,Feature/*}` · `config/{filament,sanctum}.php`, `routes/api.php`, `README.md`. Greenfield → nothing breaks; later changes (`clinical-records`, `prescriptions`, `patient-portal`, `mobile-api`, `notifications`, `admin-audit-ui`) all import from here.

## Risks

| Risk | L | Mitigation |
|---|---|---|
| MariaDB workaround diverges from PG under concurrent double-book | M | Pest test reproduces concurrent double-book on **both** drivers; `lockForUpdate()` is the second line of defense. |
| DB choice deferred to apply — specs assume PG semantics | M | Specs write index requirement as DB-agnostic intent. Driver fork is impl detail. |
| Filament v5 + Livewire v4 + Tailwind v4 install quirks on Laragon/Windows | M | `tasks.md` mandates `npm install && npm run build` and a render check before first Filament commit. |
| 400-line review budget tight for 13 migrations + 11 models + 6 tests in one PR | H | Chained PRs (see Approach §10). |
| Pest scaffold lands without a red-first test (easy to skip TDD) | M | `tasks.md` mandates red→green; `sdd-verify` checks `git log` for the pattern. |
| Filament v5 third-party plugins (Shield, etc.) lag | L | v5 is near-zero-delta over v4 (GA 2026-01-16, verified). v1 uses core Filament only. |

## Rollback

- **Skeleton**: `rm -rf vendor composer.lock` → re-run `composer create-project`. No data.
- **Post-migration**: `php artisan migrate:rollback --step=13`.
- **Post-impl**: revert commits. Test fixtures never commit.
- **DB switch**: edit `.env`, `migrate:fresh`. Migration is driver-aware.

## Dependencies

Laragon PHP 8.4.4, Composer 2.8.11, Node 20.16, and **either** PG 14.5 **or** MariaDB 10.11 running. No external services (Sanctum = local tokens; DB queue driver only).

## Success Criteria

- [ ] `migrate:fresh --seed` succeeds on chosen DB.
- [ ] `php artisan test` green: 6 smoke + transition matrix.
- [ ] `/admin/login` and `/doctor/login` render Filament login.
- [ ] tinker: `Doctor::factory()->create()` + schedule → `DoctorAvailabilityService::slots(...)` returns expected array.
- [ ] Concurrent double-book in Pest throws `SlotNotAvailableException` (409).
- [ ] `git log` shows skeleton → migrations → models → pest → sanctum → state-machine → slot-service → booking → filament.

## Capabilities (contract with sdd-spec)

### New Capabilities

- `agenda` — Appointment aggregate, state machine, slot gen, booking, cancel window. Core domain.
- `doctor-schedule` — Recurring rules + overrides; sits beneath `agenda`.
- `users-roles` — User identity, 3 roles, Gate policies, Filament panel access.
- `clinical-records` — Histories, append-only notes (`corrects_note_id`), attachments (migrations only; UI later).
- `prescriptions` — Prescriptions + items (migrations only; form later).
- `admin-audit` — `audit_logs` table + logging contract (table + contract; viewer later).

### Modified Capabilities

None. Greenfield.
