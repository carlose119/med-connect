# Design: agenda-core

## Technical Approach

`agenda-core` stands up the runnable Laravel 13 skeleton plus the booking core that every later change depends on. The strategy is a strict, layered hexagonal shape: Eloquent models hold state, PHP enums + state classes express the lifecycle, **pure domain services** compute slots, and **Actions** are the only places that open a DB transaction and write. Filament v5 sits on top of the same Models and Policies — it is the admin/doctor presentation, not a separate domain.

The design honours three contracts already locked in the proposal and the six delta specs: (1) DB-agnostic uniqueness for `appointments` enforced **twice** (a `lockForUpdate()` row lock and a partial unique index on Postgres / a virtual-column unique on MariaDB), (2) actor-aware state transitions implemented with custom `Transition` classes on top of `spatie/laravel-model-states` 2.13, and (3) append-only `medical_notes` and `audit_logs` (no `updated_at`, no public mutators). Time is UTC in the DB; the slot generator and booking action accept any timezone and convert at the edge.

This change delivers **no business behaviour beyond the booking core and the audit/clinical table shells** — UI for notes, prescriptions, or the audit viewer land in later changes. The only Filament surface in v1 is `UserResource` plus the two panel providers.

## Architecture Decisions

### Decision: Layered hexagonal split (Models / States / Services / Actions / Exceptions / Policies)

**Choice**: Strict directory split with one-way dependencies. `Models` know about `States` and the Eloquent layer only. `Services` are pure (no DB writes, no `auth()`). `Actions` are orchestrators: they open the `DB::transaction()`, call services for validation, write, and may dispatch notifications later. `Exceptions/Domain` carry semantic names and map to HTTP at the controller/middleware boundary. `Policies` answer yes/no for `Gate::authorize()`.
**Alternatives considered**: (a) "Laravel default" — controllers calling models directly. Rejected: untestable, leaks persistence into HTTP. (b) Full DDD with repositories over Eloquent. Rejected for v1: ceremony outweighs benefit when the domain has 5 states and 13 tables. (c) Single `Service` class for everything. Rejected: blocks TDD — pure computation must be separable from orchestration.
**Rationale**: Keeps `DoctorAvailabilityService` trivially unit-testable (no DB, no auth) and `BookAppointmentAction` the only place where the DB-agnostic unique index interacts with the application code. Makes every spec scenario a one-to-one test.

### Decision: `spatie/laravel-model-states` 2.13 with custom `Transition` classes for actor checks

**Choice**: One PHP backed enum `AppointmentState`, five state classes (`Pending`, `Confirmed`, `Completed`, `Cancelled`, `NoShow`), and one custom `Spatie\ModelStates\Transition` subclass per allowed edge. Each transition's `handle()` (or constructor) takes the actor and re-checks the policy before delegating to the state class's `transitionTo*()` helper.
**Alternatives considered**: (a) Encoded-actor checks inside the state class via `canTransitionTo()` only. Rejected: enums cannot be parameterized per call; actor changes per request. (b) Plain Laravel events on `Appointment::updated`. Rejected: events fire *after* the write; we need to reject before. (c) Custom hand-rolled state machine. Rejected: spatie already gives us the terminal-state guard, persistence of the state column, and serialization for APIs.
**Rationale**: Spatie 2.13 requires PHP ^8.4 (Laragon has 8.4.4 — verified, see `med-connect/filament-v5-status`). Custom `Transition` classes are the library-blessed extension point for "who can do this" and the only place to encode the patient-self-confirm ban cleanly.

### Decision: Driver-aware `appointments` unique index, branched at migration time

**Choice**: A single `database/migrations/..._create_appointments_table.php` that inspects `Schema::getConnection()->getDriverName()` and emits either a Postgres partial unique index or a MariaDB/MySQL 8 virtual generated column + unique index. The intent is identical: at most one non-cancelled appointment per `(doctor_id, start_time)`.
**Alternatives considered**: (a) A `slot_holds` table with TTL cleanup. Rejected: extra moving part, race conditions on TTL expiry, and write amplification. (b) Application-only enforcement. Rejected: spec mandates the persistence layer participates (see `med-connect/spec/double-book-persistence-contract`). (c) Two parallel migrations (`..._pg.php` and `..._maria.php`). Rejected: doubles maintenance; the `Schema::hasTable` branch is one column + one index, not two migrations.
**Rationale**: The migration code branches in one place; every model, factory, and test stays driver-agnostic. The partial index is the second line of defence behind `BookAppointmentAction`'s `lockForUpdate()`. The persistence scenario in `agenda/spec.md` is satisfied whichever driver is current.

### Decision: `lockForUpdate()` on `doctor_schedules` rows, not on `appointments`

**Choice**: Inside `BookAppointmentAction`, after `DB::beginTransaction()`, the action takes `lockForUpdate()` on the `doctor_schedules` rows whose `day_of_week` matches the slot's date. It does **not** take a row-level lock on the appointment row being inserted (it does not exist yet).
**Alternatives considered**: (a) `lockForUpdate()` on `appointments` after a probe read. Rejected: lock a not-yet-existing row is meaningless; the unique index is what actually serialises the insert path. (b) Advisory locks (`pg_advisory_xact_lock` / `GET_LOCK`). Rejected: adds DB-specific code, and we have the unique index. (c) No row lock at all — rely only on the index. Rejected: index is the safety net, not the primary synchronisation; the row lock prevents the action from validating "free" against stale data inside the same transaction.
**Rationale**: Locking the doctor's schedule rows for the slot's day of week serializes every concurrent booking for that doctor on that day, which is the granularity where contention actually happens. Combined with the unique index, two concurrent bookings cannot both pass the "free" check.

### Decision: Append-only enforced at model + DB (no Eloquent mutator, no `updated_at`)

**Choice**: `medical_notes` migration omits `updated_at`. The Eloquent model exposes no `update()` or `delete()` flow on the public API: only an `amend()` factory method that creates a new row with `corrects_note_id` pointing at the previous note. The same shape applies to `audit_logs`. Optionally a DB trigger to block `UPDATE`/`DELETE`; design keeps it as a `sdd-apply` decision and TDDs the rejection.
**Alternatives considered**: (a) DB trigger only. Rejected: not portable; MariaDB and PG trigger syntax diverge. (b) Eloquent `updating`/`deleting` observer that throws. Rejected: observers can be bypassed via `Model::withoutEvents()` and via raw queries. (c) JSON column with `version`. Rejected: violates PRD (normalized tables only).
**Rationale**: Two layers (model API + no `updated_at` column) satisfy the spec; the trigger is an optional belt-and-braces. `med-connect/spec/medical-notes-append-only` locks this in.

### Decision: Pest 3 with two-tier DB testing

**Choice**: Default Pest suite uses in-memory SQLite for fast unit/feature. The double-book scenario has two tests: (1) a unit test on the action using SQLite that asserts `SlotNotAvailableException`; (2) a driver-aware integration test that runs **only when the configured DB is Postgres or MariaDB** and is `skip()`'d otherwise, so CI never fails for missing drivers.
**Alternatives considered**: (a) Single Postgres-only test environment. Rejected: Laragon can be on either driver; CI must pass on both. (b) Docker testcontainers per driver. Rejected for v1: install friction on Windows. (c) No integration test of the index, only of the service. Rejected: spec scenario is explicit about the persistence layer.
**Rationale**: Per `med-connect/spec/double-book-persistence-contract`, the persistence layer is part of the contract. Two-tier testing gives fast feedback for unit work and a real-DB guard when the driver is available, without blocking greenfield CI.

## Data Flow

### Slot generation (read path)

    HTTP / Filament / Console
              │
              ▼
    DoctorAvailabilityService::slots(doctorId, date, tz)  ← pure
              │
              ├─ doctor_schedules (day_of_week = X, active = true)
              ├─ doctor_schedule_overrides (date = D)
              ├─ appointments (doctor_id, start_time in day, status != cancelled)
              │
              ▼
    array<{start: CarbonImmutable, end: CarbonImmutable}>  ← in tz

### Booking (write path)

    BookAppointmentAction::__invoke(doctorId, start, patientId, notes)
              │
              ▼
    DB::transaction(function () {
        doctor_schedules  ← lockForUpdate() for the slot's DOW
              │
              ├─ DoctorAvailabilityService::slots()  ← 1st guard
              ├─ assert start >= now + 2h            ← 2nd guard
              ├─ assert no patient overlap            ← 3rd guard
              │
              ▼
        INSERT INTO appointments
              │
              ▼
        catch QueryException (unique violation)
              │
              ▼
        throw SlotNotAvailableException → HTTP 409
    })

### State machine (write path)

    Appointment::state  →  transitionTo(newState, actor)  →  AppointmentStateTransition
              │                                                  │
              │                                                  ├─ check actor policy
              │                                                  ├─ check source state allowed
              │                                                  ▼
              │                                          Appointment::state = newState
              │                                                  │
              │                                                  ▼
              │                                          Notification::send(patient)  (Notifiable wired in PR4;
              │                                                                  templates deferred to `notifications` change)

## Module Structure

Greenfield — every file below is **new**.

```
app/
  Models/
    User.php                          ← HasApiTokens (Sanctum), isAdmin/isDoctor/isPatient
    Specialty.php
    Doctor.php                        ← user_id, specialty_id, license_number
    Patient.php                       ← user_id, dni, birth_date
    DoctorSchedule.php                ← doctor_id, day_of_week, start_time, end_time, slot_duration_minutes, active
    DoctorScheduleOverride.php        ← doctor_id, date, type (block|extra_availability), start_time?, end_time?
    Appointment.php                   ← HasStates; doctor_id, patient_id, start_time (UTC), end_time (UTC), state, notes
    MedicalHistory.php                ← patient_id (unique)
    MedicalNote.php                   ← medical_history_id, doctor_id, body, corrects_note_id?  (no updated_at)
    MedicalAttachment.php             ← medical_note_id, path, mime, size
    Prescription.php                  ← appointment_id, patient_id, unique_code, issued_at
    PrescriptionItem.php              ← prescription_id, drug, dose, frequency, duration, position
    AuditLog.php                      ← actor_type, actor_id, verb, subject_type, subject_id, payload (json), occurred_at  (no updated_at)

  States/
    AppointmentState.php              ← PHP backed enum: Pending, Confirmed, Completed, Cancelled, NoShow
    Appointment/
      AppointmentState.php            ← abstract base extending spatie's AbstractState
      Pending.php                     ← allowedTransitions(): Confirmed, Cancelled
      Confirmed.php                   ← allowedTransitions(): Completed, Cancelled, NoShow
      Completed.php                   ← terminal (no outgoing)
      Cancelled.php                   ← terminal (no outgoing)
      NoShow.php                      ← terminal (no outgoing)
    Transitions/
      ConfirmAppointmentTransition.php
      CompleteAppointmentTransition.php
      CancelAppointmentTransition.php
      MarkNoShowTransition.php

  Services/
    DoctorAvailabilityService.php     ← slots(doctorId, date, tz): array  (pure)

  Actions/
    BookAppointmentAction.php         ← __invoke(doctorId, start, patientId, notes?)
    CancelAppointmentAction.php       ← __invoke(appointment, actor) — patient 24h check
    EnsureMedicalHistoryAction.php    ← firstOrCreate inside the booking tx

  Exceptions/
    Domain/
      DomainException.php             ← base, carries httpStatus()
      SlotNotAvailableException.php            ← 409
      AnticipationWindowViolationException.php ← 422
      PatientOverlapException.php              ← 422
      CancellationWindowViolationException.php ← 422 or 403
      InvalidScheduleException.php             ← 422
      InvalidStateTransitionException.php      ← 422

  Policies/
    UserPolicy.php                    ← admin manage-any
    PatientPolicy.php                 ← doctor (assigned) | patient (self) | admin
    AppointmentPolicy.php             ← patient (own, read/cancel) | doctor (own, read/update) | admin

  Filament/
    AdminPanelProvider.php            ← /admin, canAccessPanel = isAdmin
    DoctorPanelProvider.php           ← /doctor, canAccessPanel = isDoctor
    Resources/
      UserResource.php                ← create doctors (assigns Doctor profile + Specialty)

  Http/
    Controllers/                      ← only if any test routes need them in PR 4
    Middleware/EnsureConsultorioTz.php

config/
  filament.php, sanctum.php           ← Filament installer + Sanctum publish

database/
  migrations/
    2026_xx_01_000001_create_users_table.php
    2026_xx_01_000002_create_specialties_table.php
    2026_xx_01_000003_create_doctors_table.php
    2026_xx_01_000004_create_patients_table.php
    2026_xx_01_000005_create_doctor_schedules_table.php
    2026_xx_01_000006_create_doctor_schedule_overrides_table.php
    2026_xx_01_000007_create_appointments_table.php       ← driver-aware unique index
    2026_xx_01_000008_create_medical_histories_table.php
    2026_xx_01_000009_create_medical_notes_table.php      ← no updated_at
    2026_xx_01_000010_create_medical_attachments_table.php
    2026_xx_01_000011_create_prescriptions_table.php
    2026_xx_01_000012_create_prescription_items_table.php
    2026_xx_01_000013_create_audit_logs_table.php         ← no updated_at

  factories/
    UserFactory.php, SpecialtyFactory.php, DoctorFactory.php, PatientFactory.php,
    DoctorScheduleFactory.php, DoctorScheduleOverrideFactory.php, AppointmentFactory.php,
    MedicalHistoryFactory.php, MedicalNoteFactory.php, MedicalAttachmentFactory.php,
    PrescriptionFactory.php, PrescriptionItemFactory.php, AuditLogFactory.php
    (12 factories; User is Laravel-default + a `role` state, so technically 11 new + 1 customised)

  seeders/
    DatabaseSeeder.php                ← one admin + one specialty + one doctor

routes/
  api.php                             ← Sanctum-protected, only what tests need in PR 4

tests/
  Pest.php                            ← RefreshDatabase, in-memory SQLite default
  TestCase.php                        ← base
  Unit/
    States/AppointmentStateTest.php   ← terminal-state rejection, actor matrix
    Services/DoctorAvailabilityServiceTest.php   ← recurring, block, extra, inactive
    Actions/BookAppointmentActionTest.php        ← happy, 2h, overlap
  Feature/
    Agenda/BookAppointmentHttpTest.php           ← 200/409/422 mapping
    Auth/FilamentPanelAccessTest.php             ← admin OK, doctor denied on /admin, etc.
    Persistence/DoubleBookUniqueIndexTest.php    ← driver-aware, skipped when no driver
```

## Database Design

13 tables, all timestamps `created_at` only except the two that need neither (`medical_notes`, `audit_logs`).

| Table | Key columns | Indexes | Notes |
|---|---|---|---|
| `users` | id, name, email (UQ), password, role (enum: admin/doctor/patient), remember_token | UNIQUE(email) | Sanctum `personal_access_tokens` added by Sanctum's own migration |
| `specialties` | id, name (UQ), slug | UNIQUE(name) | lookup for doctors |
| `doctors` | id, user_id (UQ FK users), specialty_id (FK), license_number (UQ) | UNIQUE(user_id), UNIQUE(license_number) | 1:1 with user |
| `patients` | id, user_id (UQ FK users), dni (UQ), birth_date | UNIQUE(user_id), UNIQUE(dni) | 1:1 with user |
| `doctor_schedules` | id, doctor_id (FK), day_of_week (0–6), start_time, end_time, slot_duration_minutes (UNSIGNED SMALLINT), active (bool), timestamps | (doctor_id, day_of_week, active) | validated end_time > start_time, slot_duration > 0 |
| `doctor_schedule_overrides` | id, doctor_id (FK), date, type (enum: block, extra_availability), start_time?, end_time?, reason, timestamps | (doctor_id, date) | NULL times = full-day entry (spec scenario) |
| `appointments` | id, doctor_id (FK), patient_id (FK), start_time (UTC), end_time (UTC), state (varchar, default 'pending'), notes, timestamps | **(driver-aware unique, see below)** + (patient_id, start_time), (doctor_id, start_time, state) | `end_time > start_time` CHECK (PG) / generated (MariaDB) |
| `medical_histories` | id, patient_id (UQ FK), opened_at, timestamps | UNIQUE(patient_id) | created in booking tx if missing |
| `medical_notes` | id, medical_history_id (FK), doctor_id (FK), body (TEXT), corrects_note_id? (FK self), created_at | (medical_history_id, created_at) | **no updated_at**; amendment is a new row |
| `medical_attachments` | id, medical_note_id (FK), path, mime, size (UNSIGNED BIGINT), created_at | (medical_note_id) | normalized — no JSON |
| `prescriptions` | id, appointment_id (FK), patient_id (FK), unique_code (UQ), issued_at, timestamps | UNIQUE(unique_code) | FK to appointment enforces ownership |
| `prescription_items` | id, prescription_id (FK), drug, dose, frequency, duration, position (UNSIGNED INT), created_at | UNIQUE(prescription_id, position) | ordered, no JSON |
| `audit_logs` | id, actor_type, actor_id, verb, subject_type, subject_id, payload (JSON), occurred_at | (actor_type, actor_id), (subject_type, subject_id), (occurred_at) | **no updated_at**; no Eloquent update/delete |

### The `appointments` unique index — both branches

Postgres (idiomatic):

```php
if (Schema::getConnection()->getDriverName() === 'pgsql') {
    DB::statement(
        'CREATE UNIQUE INDEX appointments_doctor_id_start_time_active_uniq '
        . 'ON appointments (doctor_id, start_time) '
        . "WHERE state <> 'cancelled'"
    );
}
```

MariaDB / MySQL 8 (virtual generated column):

```php
if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
    Schema::table('appointments', function (Blueprint $t) {
        $t->bigInteger('cancelled_marker')
            ->virtualAs("CASE WHEN state = 'cancelled' THEN id ELSE NULL END")
            ->nullable();
        $t->unique(['doctor_id', 'start_time', 'cancelled_marker'], 'appointments_doctor_id_start_time_marker_uniq');
    });
}
```

Both expressions implement the same intent: at most one non-cancelled row per `(doctor_id, start_time)`. Multiple NULLs in the MariaDB index are allowed, so cancelled rows are excluded.

### Actor state-transition matrix

| From \ To | Pending | Confirmed | Completed | Cancelled | NoShow |
|---|---|---|---|---|---|
| **(none)** → Pending | patient/doctor/admin (via `BookAppointmentAction`) | — | — | — | — |
| Pending | — | **doctor (assigned)** | — | patient (own, ≥24h), doctor, admin | doctor (assigned) |
| Confirmed | — | — | **doctor (assigned)** | patient (own, ≥24h), doctor, admin | doctor (assigned) |
| Completed / Cancelled / NoShow | rejected by spatie (terminal) | rejected | rejected | rejected | rejected |

`Patient cannot self-confirm` is enforced in `ConfirmAppointmentTransition::handle()` by checking `actor->isPatient() === true` and returning early before the parent call. Same shape in `Complete*` and `MarkNoShow*`.

## Interfaces / Contracts

```php
// app/Services/DoctorAvailabilityService.php — signature
public function slots(int $doctorId, CarbonInterface $date, ?string $tz = null): array;
// returns: array<int, array{start: CarbonImmutable, end: CarbonImmutable}>
// pure: no DB writes, no auth()->user() access. Takes DB reads only.

// app/Actions/BookAppointmentAction.php — signature
public function __invoke(
    int $doctorId,
    CarbonInterface $start,         // any TZ; converted to UTC for storage
    int $patientId,
    ?string $notes = null,
): Appointment;

// app/Actions/CancelAppointmentAction.php — signature
public function __invoke(Appointment $appointment, User $actor): Appointment;
// applies the 24h patient window only when $actor->isPatient(); doctors/admins bypass.

// app/States/Transitions/*Transition.php — base contract
abstract class AppointmentTransition extends Transition
{
    public function __construct(public Appointment $appointment, public ?User $actor = null) {}
    public function handle(): Appointment { /* policy check + transitionTo(...) */ }
}
```

Domain exception → HTTP mapping (handled by `DomainException::render()` in v1 or a single exception handler):

| Exception | HTTP |
|---|---|
| `SlotNotAvailableException` | 409 |
| `AnticipationWindowViolationException` | 422 |
| `PatientOverlapException` | 422 |
| `InvalidScheduleException` | 422 |
| `InvalidStateTransitionException` | 422 |
| `CancellationWindowViolationException` | 422 or 403 (per `med-connect/spec/agenda-cancellation-window`) |

## Testing Strategy

| Layer | What to test | Approach | Driver |
|---|---|---|---|
| Unit | `DoctorAvailabilityService`: recurring rule, block override, extra_availability override, inactive rule, `start <= now + 2h` filter | Pest + in-memory SQLite (factory seeds) | SQLite |
| Unit | `AppointmentState`: terminal states reject outgoing; `Patient → Confirmed` rejected when actor is patient | Pest, no DB needed (model factory) | SQLite |
| Unit | `BookAppointmentAction`: happy path, double-book raises `SlotNotAvailableException`, 2h window raises `AnticipationWindowViolationException`, patient overlap raises `PatientOverlapException` | Pest, factories, two `Bus::fake()`-style concurrent calls via `DB::transaction()` simulation (nested call) | SQLite |
| Feature | `BookAppointmentAction` HTTP mapping: 200/409/422 status codes | Pest + Laravel HTTP helpers + Sanctum `actingAs($user)` | SQLite |
| Feature | Filament panel access: admin reaches `/admin`, doctor gets 403 on `/admin`, etc. | Pest + `get('/admin')` with `actingAs` | SQLite |
| Integration | `appointments` unique index actually rejects a second insert | Pest, **parametrized** over `'pgsql'` and `'mariadb'`/`'mysql'`; `skip()` when the current driver is not in that set | Postgres or MariaDB |
| Integration | `medical_notes` insert with no `updated_at` column; `update()` attempt on a note throws; `amend()` creates a new row with `corrects_note_id` | Pest | SQLite |
| Integration | `audit_logs` insert with no `updated_at`; public update/delete throws | Pest | SQLite |

TDD discipline (per `openspec/AGENTS.md` "no auto-complete" and the proposal's TDD requirement): every `### Requirement` in the 6 spec files maps to at least one Pest test in `tests/Unit` or `tests/Feature`. `sdd-verify` will check `git log` for red→green commit pairs.

## File Changes

All new (greenfield). Summary count: **11 Eloquent models, 5 state classes + 1 enum + 4 transitions, 2 services-or-actions + 1 helper action, 7 domain exceptions, 3 policies, 2 panel providers + 1 resource, 13 migrations, 12 factories, ~10 Pest test files**.

| Path | Action | Description |
|---|---|---|
| `composer.json`, `composer.lock`, `package.json` | Create | Laravel 13 + Sanctum 4 + spatie-model-states 2.13 + Filament 5 + Pest 3 |
| `app/Models/*.php` (11) | Create | Eloquent models, see tree above |
| `app/States/AppointmentState.php` + 5 state classes | Create | Enum + state classes |
| `app/States/Transitions/*.php` (4) | Create | Actor-checking transitions |
| `app/Services/DoctorAvailabilityService.php` | Create | Pure slot generator |
| `app/Actions/BookAppointmentAction.php` | Create | Transactional booking |
| `app/Actions/CancelAppointmentAction.php` | Create | Patient 24h window check |
| `app/Actions/EnsureMedicalHistoryAction.php` | Create | Idempotent first-appointment history |
| `app/Exceptions/Domain/*.php` (7) | Create | Domain exceptions |
| `app/Policies/*.php` (3) | Create | Gate policies |
| `app/Filament/{Admin,Doctor}PanelProvider.php` | Create | Filament v5 panels |
| `app/Filament/Resources/UserResource.php` | Create | Admin creates doctors |
| `database/migrations/..._create_*_table.php` (13) | Create | Migrations, including driver-aware unique index |
| `database/factories/*.php` (12) | Create | Test fixtures |
| `tests/Pest.php`, `tests/TestCase.php` | Create | Pest bootstrap |
| `tests/Unit/...` (3), `tests/Feature/...` (3) | Create | Spec-driven test suite |
| `config/{filament,sanctum}.php` | Create | Published by their installers |
| `routes/api.php` | Modify | Sanctum routes mounted |
| `README.md` | Create | DB choice (PG preferred), `migrate:fresh --seed`, test command |

## Chained PR Split (review budget: 400 lines / PR)

| # | Title | Deliverable | Files (new) | LOC est. | Test gate before push |
|---|---|---|---|---|---|
| PR 1 | Skeleton | Laravel 13 running, `/` returns welcome, `git log` shows the conventional commit | `composer.json`, `composer.lock`, `package.json`, `vite.config.js`, `tailwind.config.js`, `postcss.config.js`, `resources/views/welcome.blade.php`, `.env.example`, `README.md` | 50–100 | `php artisan --version` + `npm run build` succeeds |
| PR 2 | Migrations + Models + Factories | `migrate:fresh --seed` succeeds on chosen driver; all 13 tables present with the driver-aware unique index | 13 migrations, 11 models, 11 factories, 1 seeder, `config/database.php` tweak | 300–500 | `php artisan migrate:fresh --seed` green; `tinker` can `Doctor::factory()->create()` |
| PR 3 | Sanctum + RBAC + State Machine | `User::isAdmin/Doctor/Patient` work; policies gate correctly; state machine rejects terminals and patient self-confirm | `app/Models/User.php` (mod), 3 policies, `config/sanctum.php`, `routes/api.php`, enum, 5 state classes, 4 transitions, `tests/Unit/States/AppointmentStateTest.php` | 300–500 | `php artisan test --testsuite=Unit` green (state matrix + RBAC predicates) |
| PR 4 | Agenda services + actions | `DoctorAvailabilityService` and `BookAppointmentAction` TDD'd; the 4 domain exceptions map to HTTP; the 6 smoke tests from the proposal's success criteria are green | `DoctorAvailabilityService`, `BookAppointmentAction`, `CancelAppointmentAction`, `EnsureMedicalHistoryAction`, 7 exceptions, 4 unit tests + 2 feature tests | 300–500 | `php artisan test` green; concurrent double-book raises 409; 2h and 24h windows raise 422 |
| PR 5 | Filament v5 panels + UserResource | `/admin/login` and `/doctor/login` render; `UserResource` lets an admin create a doctor (with `Specialty` and `User` row written); the 4 panel-access scenarios from `users-roles/spec.md` are green | `app/Filament/{Admin,Doctor}PanelProvider.php`, `UserResource`, `SpecialtyResource` (minimal), `tailwind.config.js` update, 1 feature test, `npm` build artifact | 200–400 | `npm run build` succeeds; visiting `/admin` as a doctor returns 403 |

**Boundary discipline**: PR 1 ends with `php artisan --version` working. PR 2 ends with `migrate:fresh --seed` green. PR 3 ends with the state matrix unit-tested. PR 4 ends with all 6 smoke tests from the proposal's Success Criteria green. PR 5 ends with both Filament panels rendering. **No PR writes production code in a later PR that is required to make an earlier PR test pass.** Filament install (npm) is isolated to PR 5 so the earlier PRs are pure PHP + SQLite.

## Risks & Mitigations

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| MariaDB vs Postgres diverge on the unique-index semantics under concurrent load | M | H (data integrity) | Two-tier testing: service-level concurrency on SQLite + driver-aware integration test that runs only when the matching driver is up; `lockForUpdate()` is the synchronisation, the index is the safety net. |
| DB choice deferred to apply time; specs assume Postgres semantics | M | M | Spec is written DB-agnostic; the migration is the only place that branches. The `agenda` spec scenario is "persistence layer rejects the write" — satisfied by either branch. |
| Filament v5 + Livewire v4 + Tailwind v4 install quirks on Laragon/Windows | M | M (PR 5) | PR 5 is dedicated to the install; tasks.md must include `npm install && npm run build` and a render check; first commit is a working Vite build, then panels. |
| `composer create-project laravel/laravel:^13 .` on a non-empty directory fails | M | L (PR 1) | `.` is empty (only `.atl/` and `openspec/` exist). Document the move (`xcopy .atl .\_hold\atl`, `composer create-project`, then move back) in the PR 1 task. |
| 400-line review budget breached if a PR mixes migrations + models + tests in one go | H | M | Chained PRs (above). Forecast per PR is given; if a PR drifts over 400, `sdd-apply` halts and re-splits before continuing. |
| Pest scaffold lands without a red-first test | M | M | `tasks.md` mandates red→green; `sdd-verify` checks `git log` for `test:` (red) → `feat:` (green) commit pairs in PRs 3 and 4. |
| `spatie/laravel-model-states` 2.13 actor restriction is not idiomatic and gets reverted to a global guard | L | M | Custom `Transition` subclasses are the library-blessed extension point (verified in `med-connect/filament-v5-status`). One state class per state keeps the matrix explicit. |
| Concurrent double-book: SQLite test passes but the real DB has a different race window | M | H | Driver-aware integration test in `tests/Feature/Persistence/` runs against the actual chosen driver in CI; `lockForUpdate()` on the schedule row is the primary guard, the unique index is the safety net. |
| Patient cancels inside 24h and the API returns the wrong status (403 vs 422) | L | L | Spec (`med-connect/spec/agenda-cancellation-window`) says either is acceptable. `CancellationWindowViolationException::httpStatus()` returns 422 by default; the test asserts that, and the message is descriptive enough that switching to 403 is a one-line change. |
| Laragon's MariaDB 10.11 may not accept `VIRTUAL` columns with the same syntax MySQL 8 does | L | M | Both MariaDB 10.2+ and MySQL 5.7+ accept `VIRTUAL AS (...)`. If the apply phase hits a syntax error, fall back to `STORED` and document the trade-off; behaviour is identical for our use case. |
| Migration ordering when `appointments` references `doctors` and `patients` | L | L | Filename prefixes enforce order: `doctors` (003) and `patients` (004) come before `appointments` (007). `sdd-apply` must not edit the `2026_xx_01_*` ordering. |

## Open Questions

- [ ] None blocking. The PR 5 admin-vs-doctor "create doctor" UX (whether `UserResource` opens a modal with both `User` and `Doctor` fields, or whether it's a wizard) is a UX detail that the design defers to `sdd-apply`/`sdd-tasks`.
- [ ] Worth noting for `sdd-tasks`: confirm whether `migrate:fresh` should drop the DB schema on MariaDB during local testing. (Default: yes, on the `med_connect_test` DB only.)
- [ ] Confirm at apply time whether the consultorio default timezone is `America/Argentina/Buenos_Aires` (the most likely PRD value) or another zone. The slot generator accepts any timezone; this only affects display defaults.

## Migration / Rollout

No production data — greenfield. Rollout is `migrate:fresh` (or `migrate` from empty) on whichever driver is chosen. Rollback per the proposal: `migrate:rollback --step=13` or `rm -rf vendor composer.lock` at the skeleton stage. DB switch is `.env`-only, then `migrate:fresh` — the driver-aware migration handles either target.
