# Tasks: agenda-core

> Chain strategy: `stacked-to-main`. Each PR branch (e.g. `feat/skeleton`) cuts off `main` after the previous PR merged. No tracker branch.
> Review budget: **400 lines / PR**.
> TDD required in PRs 3 and 4 — `sdd-verify` checks `git log` for red→green pairs.
> Filament install (npm) is isolated to PR 5; PRs 1–4 run on pure PHP + SQLite.
> Migration filenames keep their `2026_xx_01_*` prefix in dependency order. Do not alphabetize.

---

## PR 1 — Skeleton

- [ ] Stash existing `openspec/` and `.atl/` into `_hold/` so `composer create-project` can run on an empty directory.
  - Files: `_hold/atl/.gitkeep`, `_hold/openspec/.gitkeep` (move)
  - Commit: `chore(skeleton): stash atl/openspec before composer create-project`
  - Test gate: `Test-Path .\_hold\openspec\AGENTS.md`

- [ ] Run `composer create-project laravel/laravel:^13 .` (PowerShell; quote the path).
  - Files: `composer.json`, `composer.lock`, `package.json`, `vite.config.js`, `tailwind.config.js`, `postcss.config.js`, `resources/views/welcome.blade.php`, `.env.example`, `phpunit.xml`, `artisan`, `app/`, `bootstrap/`, `config/`, `public/`, `routes/`, `storage/`
  - Commit: `chore(skeleton): laravel 13 baseline via composer create-project`
  - Test gate: `php artisan --version`

- [ ] Restore `_hold/atl/` and `_hold/openspec/` to the project root, then remove `_hold/`.
  - Files: `_hold/` (delete), `.atl/`, `openspec/` (restored)
  - Commit: `chore(skeleton): restore atl and openspec directories`
  - Test gate: `Test-Path .\openspec\AGENTS.md` and `Test-Path .\openspec\changes\agenda-core\proposal.md`

- [ ] `git init` and stage everything; create the first conventional commit.
  - Files: `.gitignore` (Laravel-default)
  - Commit: `chore(skeleton): initial conventional commit on greenfield repo`
  - Test gate: `git log --oneline` shows the skeleton commit; `php artisan --version` still green

- [ ] Document DB choice in `README.md` (PG preferred, MariaDB fallback) and the `migrate:fresh --seed` command.
  - Files: `README.md`
  - Commit: `docs(skeleton): document db choice and seed command`
  - Test gate: `Get-Content .\README.md | Select-String -Pattern 'migrate:fresh --seed'`

- [ ] Sanity-render the welcome page and confirm `npm run build` does not yet run (Filament install is PR 5).
  - Files: `resources/views/welcome.blade.php`
  - Commit: `chore(skeleton): confirm welcome view renders without npm`
  - Test gate: `php -S 127.0.0.1:8000 -t public` + `curl -I http://127.0.0.1:8000/` returns `200`

---

## PR 2 — Migrations + Models + Factories

> Migrations keep the `2026_xx_01_*` filename order shown below. Do not alphabetize; do not change prefix. The default `users` migration from the skeleton is renamed to `2026_xx_01_000001_create_users_table.php` and the `role` enum column is added in this PR.

- [ ] Add the `role` enum column (`admin | doctor | patient`) and an index on `users.email` to the renamed users migration.
  - Files: `database/migrations/2026_xx_01_000001_create_users_table.php` (renamed)
  - Commit: `feat(migrations): add role enum and email index to users`
  - Test gate: `php artisan migrate:status` lists the migration

- [ ] Create `specialties` table: `id`, `name` (UQ), `slug` (UQ), timestamps.
  - Files: `database/migrations/2026_xx_01_000002_create_specialties_table.php`
  - Commit: `feat(migrations): create specialties lookup table`
  - Test gate: `php artisan migrate:status`

- [ ] Create `doctors` table: `id`, `user_id` (UQ FK), `specialty_id` (FK), `license_number` (UQ), timestamps. Indexes: `UNIQUE(user_id)`, `UNIQUE(license_number)`.
  - Files: `database/migrations/2026_xx_01_000003_create_doctors_table.php`
  - Commit: `feat(migrations): create doctors table with 1:1 user link`
  - Test gate: `php artisan migrate:status`

- [ ] Create `patients` table: `id`, `user_id` (UQ FK), `dni` (UQ), `birth_date`, timestamps. Indexes: `UNIQUE(user_id)`, `UNIQUE(dni)`.
  - Files: `database/migrations/2026_xx_01_000004_create_patients_table.php`
  - Commit: `feat(migrations): create patients table with 1:1 user link`
  - Test gate: `php artisan migrate:status`

- [ ] Create `doctor_schedules` table: `id`, `doctor_id` (FK), `day_of_week` (0–6), `start_time`, `end_time`, `slot_duration_minutes` (UNSIGNED SMALLINT), `active` (bool), timestamps. Index: `(doctor_id, day_of_week, active)`. Validation rule: `end_time > start_time` and `slot_duration_minutes > 0` enforced at model layer.
  - Files: `database/migrations/2026_xx_01_000005_create_doctor_schedules_table.php`
  - Commit: `feat(migrations): create doctor_schedules recurring rules`
  - Test gate: `php artisan migrate:status`

- [ ] Create `doctor_schedule_overrides` table: `id`, `doctor_id` (FK), `date`, `type` (enum `block | extra_availability`), `start_time?`, `end_time?`, `reason`, timestamps. Index: `(doctor_id, date)`. NULL times = full-day entry.
  - Files: `database/migrations/2026_xx_01_000006_create_doctor_schedule_overrides_table.php`
  - Commit: `feat(migrations): create doctor_schedule_overrides`
  - Test gate: `php artisan migrate:status`

- [ ] Create `appointments` table — **driver-aware unique index** branching on `Schema::getConnection()->getDriverName()`. Columns: `id`, `doctor_id` (FK), `patient_id` (FK), `start_time` (UTC), `end_time` (UTC), `state` (varchar, default `'pending'`), `notes`, timestamps. Indexes: `(patient_id, start_time)`, `(doctor_id, start_time, state)`, and the **driver-aware** unique: PG partial index `WHERE state <> 'cancelled'`, OR MariaDB virtual column `cancelled_marker` (`CASE WHEN state='cancelled' THEN id ELSE NULL END`) + unique `(doctor_id, start_time, cancelled_marker)`. CHECK `end_time > start_time` (PG native, MariaDB generated).
  - Files: `database/migrations/2026_xx_01_000007_create_appointments_table.php`
  - Commit: `feat(migrations): create appointments with driver-aware unique index`
  - Test gate: `php artisan migrate:status`

- [ ] Create `medical_histories` table: `id`, `patient_id` (UQ FK), `opened_at`, timestamps. UNIQUE(`patient_id`).
  - Files: `database/migrations/2026_xx_01_000008_create_medical_histories_table.php`
  - Commit: `feat(migrations): create medical_histories with unique patient_id`
  - Test gate: `php artisan migrate:status`

- [ ] Create `medical_notes` table: `id`, `medical_history_id` (FK), `doctor_id` (FK), `body` (TEXT), `corrects_note_id?` (FK self, nullable), `created_at` only — **no `updated_at`**. Index: `(medical_history_id, created_at)`.
  - Files: `database/migrations/2026_xx_01_000009_create_medical_notes_table.php`
  - Commit: `feat(migrations): create medical_notes without updated_at`
  - Test gate: `php artisan migrate:status`

- [ ] Create `medical_attachments` table: `id`, `medical_note_id` (FK), `path`, `mime`, `size` (UNSIGNED BIGINT), `created_at`. Index: `(medical_note_id)`.
  - Files: `database/migrations/2026_xx_01_000010_create_medical_attachments_table.php`
  - Commit: `feat(migrations): create medical_attachments normalized rows`
  - Test gate: `php artisan migrate:status`

- [ ] Create `prescriptions` table: `id`, `appointment_id` (FK), `patient_id` (FK), `unique_code` (UQ), `issued_at`, timestamps.
  - Files: `database/migrations/2026_xx_01_000011_create_prescriptions_table.php`
  - Commit: `feat(migrations): create prescriptions with unique_code`
  - Test gate: `php artisan migrate:status`

- [ ] Create `prescription_items` table: `id`, `prescription_id` (FK), `drug`, `dose`, `frequency`, `duration`, `position` (UNSIGNED INT), `created_at`. UNIQUE(`prescription_id`, `position`).
  - Files: `database/migrations/2026_xx_01_000012_create_prescription_items_table.php`
  - Commit: `feat(migrations): create prescription_items with position`
  - Test gate: `php artisan migrate:status`

- [ ] Create `audit_logs` table: `id`, `actor_type`, `actor_id`, `verb`, `subject_type`, `subject_id`, `payload` (JSON), `occurred_at` only — **no `updated_at`**. Indexes: `(actor_type, actor_id)`, `(subject_type, subject_id)`, `(occurred_at)`.
  - Files: `database/migrations/2026_xx_01_000013_create_audit_logs_table.php`
  - Commit: `feat(migrations): create audit_logs without updated_at`
  - Test gate: `php artisan migrate:status`

- [ ] Add the 13 new Eloquent models: `Specialty`, `Doctor`, `Patient`, `DoctorSchedule`, `DoctorScheduleOverride`, `Appointment`, `MedicalHistory`, `MedicalNote`, `MedicalAttachment`, `Prescription`, `PrescriptionItem`, `AuditLog` (12 new) plus modify the default `User` model to add a `role` cast and the role predicates (`isAdmin/isDoctor/isPatient`) — kept minimal here, `HasApiTokens` is added in PR 3. `Appointment` declares `use HasStates;` even though the trait is wired in PR 3.
  - Files: `app/Models/User.php` (mod), `app/Models/Specialty.php`, `app/Models/Doctor.php`, `app/Models/Patient.php`, `app/Models/DoctorSchedule.php`, `app/Models/DoctorScheduleOverride.php`, `app/Models/Appointment.php`, `app/Models/MedicalHistory.php`, `app/Models/MedicalNote.php`, `app/Models/MedicalAttachment.php`, `app/Models/Prescription.php`, `app/Models/PrescriptionItem.php`, `app/Models/AuditLog.php`
  - Commit: `feat(migrations): add 12 eloquent models + user role predicates`
  - Test gate: `php artisan tinker --execute="echo class_exists(App\Models\Doctor::class);"`

- [ ] Add factories: extend the default `UserFactory` with a `role()` state (`admin`/`doctor`/`patient`); add `SpecialtyFactory`, `DoctorFactory`, `PatientFactory`, `DoctorScheduleFactory`, `DoctorScheduleOverrideFactory`, `AppointmentFactory`, `MedicalHistoryFactory`, `MedicalNoteFactory`, `MedicalAttachmentFactory`, `PrescriptionFactory`, `PrescriptionItemFactory`, `AuditLogFactory`.
  - Files: `database/factories/UserFactory.php` (mod), `database/factories/SpecialtyFactory.php`, `database/factories/DoctorFactory.php`, `database/factories/PatientFactory.php`, `database/factories/DoctorScheduleFactory.php`, `database/factories/DoctorScheduleOverrideFactory.php`, `database/factories/AppointmentFactory.php`, `database/factories/MedicalHistoryFactory.php`, `database/factories/MedicalNoteFactory.php`, `database/factories/MedicalAttachmentFactory.php`, `database/factories/PrescriptionFactory.php`, `database/factories/PrescriptionItemFactory.php`, `database/factories/AuditLogFactory.php`
  - Commit: `feat(migrations): add 13 factories including user role state`
  - Test gate: `php artisan tinker --execute="App\Models\Doctor::factory()->create();"`

- [ ] Add `DatabaseSeeder` that creates 1 admin, 1 specialty, 1 doctor (with linked `User` + `Doctor`).
  - Files: `database/seeders/DatabaseSeeder.php`
  - Commit: `feat(migrations): add base seeder admin + specialty + doctor`
  - Test gate: `php artisan migrate:fresh --seed` exits 0; `php artisan tinker --execute="echo App\Models\Doctor::count();"` prints `1`

- [ ] Tweak `config/database.php` so the default `sqlite` connection in `phpunit.xml` is the in-memory `:memory:` (Laravel default — verify only).
  - Files: `config/database.php`, `phpunit.xml`
  - Commit: `feat(migrations): confirm in-memory sqlite for test env`
  - Test gate: `php artisan test --filter=ExampleTest` (Laravel's bundled example)

---

## PR 3 — Sanctum + RBAC + State Machine

> **TDD mandatory** — every `feat` task in this PR is preceded by a `test` commit that fails. `sdd-verify` checks `git log` for the red→green pair.

- [x] Install Sanctum and publish its config; add `HasApiTokens` to the `User` model and the `role` predicates; mount Sanctum in `routes/api.php` (auth-only group, no business routes yet).
  - Files: `composer.json` (mod), `config/sanctum.php`, `app/Models/User.php` (mod), `routes/api.php` (mod)
  - Commit: `feat(state-machine): wire sanctum and hasapitokens on user`
  - Test gate: `php artisan vendor:publish --tag=sanctum-config`; `php artisan route:list --path=api`

- [x] **TDD: red** — Write `tests/Feature/Auth/RolePredicatesTest.php` asserting `isAdmin/isDoctor/isPatient` are each true for exactly one role and false for the other two. Commit it. It must fail before the next task.
  - Files: `tests/Feature/Auth/RolePredicatesTest.php`
  - Commit: `test(state-machine): red — role predicates test (fails until next commit)`
  - Test gate: `php artisan test --filter=RolePredicatesTest` exits non-zero

- [x] **TDD: green** — Implement (or finalise) the three predicates on `User`. Test must pass.
  - Files: `app/Models/User.php` (mod)
  - Commit: `feat(state-machine): green — role predicates pass`
  - Test gate: `php artisan test --filter=RolePredicatesTest` exits 0

- [x] Create the `AppointmentState` PHP backed enum with the 5 cases `Pending | Confirmed | Completed | Cancelled | NoShow`.
  - Files: `app/States/AppointmentState.php`
  - Commit: `feat(state-machine): add AppointmentState backed enum`
  - Test gate: `php -r "require 'vendor/autoload.php'; echo enum_exists('App\\States\\AppointmentState');"`

- [x] Create the 5 state classes: abstract base `Appointment\AppointmentState` extending `Spatie\ModelStates\State`; `Pending` (allowed → `Confirmed`, `Cancelled`); `Confirmed` (allowed → `Completed`, `Cancelled`, `NoShow`); `Completed`, `Cancelled`, `NoShow` (terminal — empty `allowedTransitions()`).
  - Files: `app/States/Appointment/AppointmentState.php` (abstract), `app/States/Appointment/Pending.php`, `App/States/Appointment/Confirmed.php`, `App/States/Appointment/Completed.php`, `App/States/Appointment/Cancelled.php`, `App/States/Appointment/NoShow.php`
  - Commit: `feat(state-machine): add 5 appointment state classes`
  - Test gate: `php -r "require 'vendor/autoload.php'; echo class_exists('App\\States\\Appointment\\Pending');"`

- [x] Wire `HasStates` on `Appointment` (the `state` column casts to the enum) and register the 3 Gate policies: `UserPolicy` (admin manage-any), `PatientPolicy` (doctor-assigned or patient-self or admin), `AppointmentPolicy` (patient own, doctor own, admin any).
  - Files: `app/Models/Appointment.php` (mod), `app/Policies/UserPolicy.php`, `app/Policies/PatientPolicy.php`, `app/Policies/AppointmentPolicy.php`
  - Commit: `feat(state-machine): wire hasstates and 3 gate policies`
  - Test gate: `php artisan test --filter=RolePredicatesTest` (regression) + `php -r "require 'vendor/autoload.php'; echo class_exists('App\\Policies\\AppointmentPolicy');"`

- [x] Create the domain exception base `DomainException` (carries `httpStatus()`) and `InvalidStateTransitionException` (422). Both live in `app/Exceptions/Domain/`. The base class is used by PR 4's exceptions; the state-transition one is exercised in this PR.
  - Files: `app/Exceptions/Domain/DomainException.php`, `app/Exceptions/Domain/InvalidStateTransitionException.php`
  - Commit: `feat(state-machine): add DomainException base + InvalidStateTransition`
  - Test gate: `php -r "require 'vendor/autoload.php'; echo class_exists('App\\Exceptions\\Domain\\InvalidStateTransitionException');"`

- [x] Create the 4 custom `Transition` classes extending `Spatie\ModelStates\Transition`: `ConfirmAppointmentTransition` (rejects patient actor), `CompleteAppointmentTransition` (doctor assigned only), `CancelAppointmentTransition` (24h patient window applied later in `CancelAppointmentAction`), `MarkNoShowTransition` (doctor assigned only). Each transition's `handle()` re-checks the policy before delegating to the state class's `transitionTo*()` helper.
  - Files: `app/States/Transitions/ConfirmAppointmentTransition.php`, `app/States/Transitions/CompleteAppointmentTransition.php`, `app/States/Transitions/CancelAppointmentTransition.php`, `app/States/Transitions/MarkNoShowTransition.php`
  - Commit: `feat(state-machine): add 4 actor-aware transition classes`
  - Test gate: `php -r "require 'vendor/autoload.php'; echo class_exists('App\\States\\Transitions\\ConfirmAppointmentTransition');"`

- [x] **TDD: red** — Write `tests/Unit/States/AppointmentStateTest.php` with the 4 unit tests: (a) terminal states reject outgoing transitions; (b) `Pending → Confirmed` allowed when actor is the assigned doctor; (c) `Pending → Confirmed` rejected when actor is the patient (the "patient cannot self-confirm" scenario); (d) `Pending → Cancelled` allowed for patient/doctor/admin. Commit the test; it must fail.
  - Files: `tests/Unit/States/AppointmentStateTest.php`
  - Commit: `test(state-machine): red — appointment state matrix (4 tests, fails)`
  - Test gate: `php artisan test --filter=AppointmentStateTest` exits non-zero

- [x] **TDD: green** — Wire the transitions on the model (typically `Appointment::state->transitionTo(newState, transition)` helpers) so the matrix passes. Test must pass.
  - Files: `app/Models/Appointment.php` (mod), `app/States/Appointment/Pending.php` (mod), `app/States/Appointment/Confirmed.php` (mod)
  - Commit: `feat(state-machine): green — appointment state matrix passes`
  - Test gate: `php artisan test --filter=AppointmentStateTest` exits 0

---

## PR 4 — Agenda services + actions

> **TDD mandatory** — every `feat` task in this PR is preceded by a `test` commit. `sdd-verify` checks `git log` for red→green pairs.

- [ ] Add the 4 booking-related domain exceptions in `app/Exceptions/Domain/`: `SlotNotAvailableException` (409), `AnticipationWindowViolationException` (422), `PatientOverlapException` (422), `CancellationWindowViolationException` (422 default; switchable to 403 via a const). Plus `InvalidScheduleException` (422) for the recurring-rule validation in `DoctorSchedule`.
  - Files: `app/Exceptions/Domain/SlotNotAvailableException.php`, `app/Exceptions/Domain/AnticipationWindowViolationException.php`, `app/Exceptions/Domain/PatientOverlapException.php`, `app/Exceptions/Domain/CancellationWindowViolationException.php`, `app/Exceptions/Domain/InvalidScheduleException.php`
  - Commit: `feat(agenda): add 5 domain exceptions`
  - Test gate: `php -r "require 'vendor/autoload.php'; foreach (['Slot','Anticipation','PatientOverlap','Cancellation','InvalidSchedule'] as \$n) { echo class_exists('App\\Exceptions\\Domain\\'.\$n.'Exception'); }"`

- [ ] **TDD: red** — Write `tests/Unit/Services/DoctorAvailabilityServiceTest.php` covering 4 scenarios: active recurring rule produces slots, inactive rule produces none, block override excludes a time range, extra_availability override adds a slot. Commit the test; it must fail.
  - Files: `tests/Unit/Services/DoctorAvailabilityServiceTest.php`
  - Commit: `test(agenda): red — doctor availability service (4 scenarios)`
  - Test gate: `php artisan test --filter=DoctorAvailabilityServiceTest` exits non-zero

- [ ] **TDD: green** — Implement `app/Services/DoctorAvailabilityService.php` with the signature `public function slots(int $doctorId, CarbonInterface $date, ?string $tz = null): array` returning `array<int, array{start: CarbonImmutable, end: CarbonImmutable}>` in the requested tz. Pure (no DB writes, no `auth()`). Test must pass.
  - Files: `app/Services/DoctorAvailabilityService.php`
  - Commit: `feat(agenda): green — doctor availability service passes`
  - Test gate: `php artisan test --filter=DoctorAvailabilityServiceTest` exits 0

- [ ] **TDD: red** — Write `tests/Unit/Actions/BookAppointmentActionTest.php` covering 3 scenarios: happy path produces a `pending` appointment, 2h window raises `AnticipationWindowViolationException`, patient overlap raises `PatientOverlapException`. Commit; must fail.
  - Files: `tests/Unit/Actions/BookAppointmentActionTest.php`
  - Commit: `test(agenda): red — book appointment action (3 scenarios)`
  - Test gate: `php artisan test --filter=BookAppointmentActionTest` exits non-zero

- [ ] **TDD: green** — Implement `app/Actions/EnsureMedicalHistoryAction.php` (idempotent firstOrCreate on `medical_histories` keyed by `patient_id`) and `app/Actions/BookAppointmentAction.php` with signature `public function __invoke(int $doctorId, CarbonInterface $start, int $patientId, ?string $notes = null): Appointment`. Wraps everything in `DB::transaction()`, takes `lockForUpdate()` on the `doctor_schedules` rows for the slot's DOW, calls `DoctorAvailabilityService::slots()` for the 1st guard, asserts `start >= now + 2h` (2nd guard), asserts no patient overlap (3rd guard), inserts the appointment, and inside the same transaction calls `EnsureMedicalHistoryAction` so the history exists for first-time patients. Catches `QueryException` for the unique-index violation and re-throws `SlotNotAvailableException`. Test must pass.
  - Files: `app/Actions/EnsureMedicalHistoryAction.php`, `app/Actions/BookAppointmentAction.php`
  - Commit: `feat(agenda): green — book appointment action + history helper pass`
  - Test gate: `php artisan test --filter=BookAppointmentActionTest` exits 0

- [ ] **TDD: red** — Write `tests/Unit/Actions/CancelAppointmentActionTest.php` covering 2 scenarios: patient cancels at `now + 48h` succeeds, patient cancels at `now + 12h` raises `CancellationWindowViolationException`. Commit; must fail.
  - Files: `tests/Unit/Actions/CancelAppointmentActionTest.php`
  - Commit: `test(agenda): red — cancel appointment action (2 scenarios)`
  - Test gate: `php artisan test --filter=CancelAppointmentActionTest` exits non-zero

- [ ] **TDD: green** — Implement `app/Actions/CancelAppointmentAction.php` as a **stub** with signature `public function __invoke(Appointment $appointment, User $actor): Appointment` that runs the 24h window check **only when `$actor->isPatient()`** (doctors/admins bypass), then dispatches the `CancelAppointmentTransition`. The full cancellation lifecycle (notifications, audit log wiring) is out of scope — it lands in a later change (`agenda-cancel`).
  - Files: `app/Actions/CancelAppointmentAction.php`
  - Commit: `feat(agenda): green — cancel appointment action stub (24h patient check)`
  - Test gate: `php artisan test --filter=CancelAppointmentActionTest` exits 0

- [ ] **TDD: red** — Write `tests/Feature/Agenda/BookAppointmentHttpTest.php` covering 3 HTTP mappings: happy path returns 201/200 with an `Appointment`, double-book returns 409 with `SlotNotAvailableException`, 2h window returns 422 with `AnticipationWindowViolationException`. Wire a temporary test route in `routes/api.php` (behind Sanctum) that invokes `BookAppointmentAction`. Commit; must fail.
  - Files: `tests/Feature/Agenda/BookAppointmentHttpTest.php`, `routes/api.php` (mod)
  - Commit: `test(agenda): red — booking http mapping (3 status codes)`
  - Test gate: `php artisan test --filter=BookAppointmentHttpTest` exits non-zero

- [ ] **TDD: green** — Wire the `DomainException::render()` (via a custom exception handler closure) so each exception class maps to the right HTTP status. Test must pass.
  - Files: `bootstrap/app.php` (mod)
  - Commit: `feat(agenda): green — domain exception to http mapping`
  - Test gate: `php artisan test --filter=BookAppointmentHttpTest` exits 0

- [ ] **TDD: red** — Write `tests/Feature/Persistence/DoubleBookUniqueIndexTest.php` as the **driver-aware** integration test: it `skip()`s when `DB::connection()->getDriverName()` is not in `['pgsql','mariadb','mysql']`, and otherwise inserts two appointments and asserts the second insert raises a `QueryException` (which the action maps to `SlotNotAvailableException`). Commit; it must skip on SQLite.
  - Files: `tests/Feature/Persistence/DoubleBookUniqueIndexTest.php`
  - Commit: `test(agenda): red — driver-aware double-book persistence test`
  - Test gate: `php artisan test --filter=DoubleBookUniqueIndexTest` reports skipped (not failed) on SQLite

- [ ] **TDD: green** — No code change expected (the migration was wired in PR 2). Run with the real PG or MariaDB driver and confirm green. On the chosen dev driver, the test must pass.
  - Files: none
  - Commit: `feat(agenda): green — double-book persistence passes on real driver`
  - Test gate: with `DB_CONNECTION=pgsql` (or `mariadb`) the test exits 0

- [ ] Run the full suite — the **6 smoke tests** from the proposal's success criteria (slot gen, override, happy book, double-book 409, 2h window 422, transition matrix) must be green. Also verify the **2h window** and **24h cancel** are exercised.
  - Files: none
  - Commit: `feat(agenda): all 6 smoke tests green`
  - Test gate: `php artisan test` exits 0; `git log --oneline` shows the red→green pairs in PR 3 and PR 4

---

## PR 5 — Filament v5 panels + UserResource

> Filament install is isolated to this PR. `npm install && npm run build` is a mandatory gate before any Filament commit.

- [ ] Install Filament v5 via Composer and run the panels installer.
  - Files: `composer.json` (mod), `composer.lock`, `config/filament.php`, `app/Providers/Filament/`
  - Commit: `feat(filament): composer require filament/filament ^5`
  - Test gate: `php artisan filament:install --panels --no-interaction` exits 0; `Test-Path .\config\filament.php`

- [ ] Install npm dependencies and build the Vite assets (Tailwind v4 + Filament theme). Confirm a render check before the next commit.
  - Files: `package.json` (mod), `package-lock.json`, `tailwind.config.js` (mod), `resources/css/filament/`, `public/css/filament/`
  - Commit: `feat(filament): npm install and vite build for filament v5`
  - Test gate: `npm run build` exits 0; `php artisan serve` + `curl -I http://127.0.0.1:8000/admin/login` returns 200

- [ ] Customise `AdminPanelProvider` (path `/admin`, `canAccessPanel = isAdmin`) and create `DoctorPanelProvider` (path `/doctor`, `canAccessPanel = isDoctor`). Register both in `bootstrap/providers.php`.
  - Files: `app/Providers/Filament/AdminPanelProvider.php`, `app/Providers/Filament/DoctorPanelProvider.php`, `bootstrap/providers.php` (mod)
  - Commit: `feat(filament): add admin and doctor panel providers`
  - Test gate: `php artisan route:list --path=admin/login` and `--path=doctor/login` each return 1 row

- [ ] Implement `UserResource` under `app/Filament/Resources/UserResource.php` with a `createDoctor` form action that, on submit, creates a `User` (role `doctor`), a `Doctor` profile (with `Specialty` selection), and writes an `AuditLog` row with verb `create_doctor`, actor = current admin, subject = the new doctor.
  - Files: `app/Filament/Resources/UserResource.php`, `app/Filament/Resources/UserResource/Pages/CreateUser.php`
  - Commit: `feat(filament): UserResource createDoctor form action + audit log`
  - Test gate: `php -r "require 'vendor/autoload.php'; echo class_exists('App\\Filament\\Resources\\UserResource');"`

- [ ] **TDD: red** — Write `tests/Feature/Auth/FilamentPanelAccessTest.php` covering 4 scenarios (mapped to `users-roles/spec.md`): admin reaches `/admin`, doctor/patient is 403 on `/admin`, doctor reaches `/doctor`, admin/patient is 403 on `/doctor`. Commit; must fail.
  - Files: `tests/Feature/Auth/FilamentPanelAccessTest.php`
  - Commit: `test(filament): red — panel access matrix (4 scenarios)`
  - Test gate: `php artisan test --filter=FilamentPanelAccessTest` exits non-zero

- [ ] **TDD: green** — Confirm `User::isAdmin/isDoctor` predicates (already implemented in PR 3) feed `canAccessPanel` correctly. The test must pass without changing any model code beyond a wiring fix if needed.
  - Files: `app/Providers/Filament/AdminPanelProvider.php` (mod if needed), `app/Providers/Filament/DoctorPanelProvider.php` (mod if needed)
  - Commit: `feat(filament): green — panel access matrix passes`
  - Test gate: `php artisan test --filter=FilamentPanelAccessTest` exits 0

- [ ] Run the full suite end-to-end and confirm `npm run build` is green.
  - Files: none
  - Commit: `feat(filament): all tests green + vite build green`
  - Test gate: `php artisan test` exits 0; `npm run build` exits 0; `git log --oneline` shows skeleton → migrations → state-machine → agenda → filament

---

## Open questions for sdd-apply

- [ ] Confirm the consultorio default timezone with the user (default `America/Argentina/Buenos_Aires`; this affects display only — slot generator and booking accept any tz).
- [ ] Confirm the chosen DB driver at apply time (default PostgreSQL; fallback MariaDB 10.11 via Laragon if PG is not running).
- [ ] UX of "admin creates doctor" in `UserResource`: single form (User + Doctor fields in one page) vs multi-step wizard. The design defers to `sdd-apply`.
- [ ] Decide whether to add a DB trigger to enforce `medical_notes` / `audit_logs` immutability (the design marked it as an optional belt-and-braces; spec is already satisfied by the no-`updated_at` column + missing public mutator).
- [ ] Confirm `migrate:fresh` is acceptable on MariaDB during local testing (default: yes, on a `med_connect_test` database only).

---

## Review workload forecast

| PR | Title | Estimated LOC (additions) | Confidence | Within 400-line budget? |
|---|---|---|---|---|
| PR 1 | Skeleton | 80–150 (mostly composer/lock auto-generated) | High | **Yes** |
| PR 2 | Migrations + Models + Factories | 550–850 (13 migrations + 12 models + 13 factories + 1 seeder) | Medium | **Likely NO** — flag below |
| PR 3 | Sanctum + RBAC + State Machine | 250–400 (3 policies + 1 enum + 5 state classes + 4 transitions + 2 exceptions + 2 tests) | High | **Yes** |
| PR 4 | Agenda services + actions | 350–500 (1 service + 3 actions + 5 exceptions + 5 tests + 1 route wiring) | Medium | **Borderline** |
| PR 5 | Filament v5 panels + UserResource | 300–450 (2 panel providers + 1 user resource + 1 test + npm configs) | Medium | **Borderline** |

### Verdict

**PR 2 is forecast to exceed the 400-line review budget.** The Chained PR Split table in the design also estimates PR 2 at 300–500 LOC; my honest re-estimate puts it at **550–850 LOC** because the design tree has 13 migrations, 12 new models, and 13 factories — not 11 of each. Even with compact class-based factories and minimal migrations, the raw count is too high.

**PRs 3, 4, 5 are within or borderline-within budget** — they are at risk only if implementation drifts from the locked design (e.g. extra model accessors, verbose Filament forms).

### Proposed re-split (orchestrator decision)

The user prompt locks the PR count at 5 and forbids renumbering. The forecast nonetheless triggers `delivery_strategy: ask-always` (per `openspec/AGENTS.md` § Preflight). The orchestrator **MUST** halt before `sdd-apply` PR 2 starts and ask the user one of:

- **A. Stack as-is and accept `size:exception`** for PR 2 (single PR, maintainer approves the over-budget diff).
- **B. Split PR 2 into PR 2a (migrations only) + PR 2b (models + factories + seeder)**, becoming a 6-PR chain. **This violates the "no renumber" rule** but respects the 400-line cap.
- **C. Move factories + seeder out of PR 2 into a new PR 2.5 between PR 2 and PR 3**, becoming a 6-PR chain. Same caveat as B.

**Recommendation**: A (stack as-is, accept `size:exception`) — the 13 migrations are interlocked and splitting them across two PRs risks merge conflicts in the `2026_xx_01_*` filename order. The user can override.

### Loc evidence per locked design

- PR 1 actual scope: composer/lockfiles are auto-generated, so net hand-written code is small (`README.md`, `.env.example` tweak). 80–150 LOC is realistic.
- PR 2 actual scope: 13 migrations × 20 LOC + 12 new models × 30 LOC + 13 factories × 25 LOC + 1 seeder × 20 LOC ≈ **905 LOC raw**. Even at 60% of raw, that is 540 LOC. The design's 300–500 estimate appears optimistic.
- PR 3 actual scope: 3 policies × 35 LOC + 1 enum × 10 LOC + 5 state classes × 15 LOC + 4 transitions × 25 LOC + 2 exceptions × 12 LOC + 1 test × 80 LOC ≈ **350 LOC**.
- PR 4 actual scope: 1 service × 60 LOC + 3 actions × 70 LOC + 5 exceptions × 12 LOC + 5 tests × 50 LOC + 1 route × 10 LOC ≈ **510 LOC**.
- PR 5 actual scope: 2 panel providers × 50 LOC + 1 user resource × 150 LOC + 1 test × 80 LOC + npm configs ≈ **330 LOC** (excluding auto-generated `package-lock.json`).

### Forecast contract lines (for `sdd-verify` / chained-pr guards)

```
Decision needed before apply: Yes
Chained PRs recommended: No (already chained; the question is whether PR 2 itself stays single)
Chain strategy: stacked-to-main
400-line budget risk: High (PR 2)
```

`Decision needed before apply: Yes` because `delivery_strategy: ask-always` mandates a user checkpoint when the forecast exceeds the budget.
