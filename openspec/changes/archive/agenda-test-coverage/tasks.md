# Tasks: agenda-test-coverage

> Chain strategy: `stacked-to-main`. Cut `feat/test-coverage` from `main` (post-`agenda-http` archive at `41d90a3`); FF-merge when the verification gate is green. No tracker branch.
> Review budget: **400 lines / PR**.
> TDD required: every `feat` task is preceded by a `test` commit that fails. The 8 sub-groups are independent of each other; only the inner RED→GREEN pairs are dependent. `sdd-verify` greps `git log` for the red→green pair order.
> Test runner: `vendor/bin/pest` (Pest). No DB migrations (transport / policy / test-only change).
> Strict TDD exceptions: 5 of the 8 sub-groups have RED+GREEN where the GREEN is "no impl change needed — the test trivially passes against existing behavior". These are documented inline at T-COV-2, T-COV-6, T-COV-8, T-COV-12, T-COV-14. The 3 non-trivial sub-groups (PatientPolicy, BookAppointmentRequest, ErrorResponse for `conflicting_appointment_id`) have a real GREEN commit.
> Pre-existing baseline (from `agenda-http` archive): SQLite 118 passed + 3 skipped / MariaDB 121 passed. This change adds 12 scenarios: expected 130 passed + 3 skipped (SQLite) and 133 passed (MariaDB).
> Out-of-scope (deferred to later changes): `agenda-resource-shape` (DoctorResource shape drift), `agenda-readme-drift` (5 README lines), 3 cosmetic SUGGESTIONs. See `proposal.md §Out of scope`.

---

## Review Workload Forecast

| Field | Value |
|---|---|
| Estimated changed lines (hand-written) | ~235 LOC (11 test scenarios across 7 modified files + 1 new file + 1 policy mod + 2 possible impl mods) |
| 400-line budget risk | **Low** — comfortably under 400 |
| Chained PRs recommended | **No** — single PR, all sub-groups are independent |
| Suggested split | Single PR (`feat/test-coverage` → `main`) |
| Delivery strategy | `ask-always` (per `openspec/AGENTS.md` preflight) |
| Chain strategy | `stacked-to-main` (locked in proposal + design) |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: stacked-to-main
400-line budget risk: Low

### Per-PR forecast

| PR | Title | Est. LOC (hand-written) | 400-line budget? | RED / GREEN / VERIFY commits |
|---|---|---|---|---|
| PR 1 | Test Coverage (12 ADDED scenarios + 1 policy mod) | ~235 | **Yes** (well under) | 8 red / 8 green / 1 verify = 17 commits |

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|---|---|---|---|
| 1 | Lock 12 spec scenarios in code; close 1 spec/implementation drift on `PatientPolicy@view` | PR 1 | base: `main` (post-`agenda-http` archive); 7 test files MOD, 1 NEW (`TimezoneResolutionTest.php`), 1 policy MOD, 2 possible impl mods (`BookAppointmentRequest`, `ErrorResponse`+`SlotNotAvailableException`) |

---

## PR 1 — Test Coverage (12 items, 1 PR, 17 commits, ~235 LOC)

> **TDD mandatory.** The first commit is a red test for the spec/implementation drift on `PatientPolicy@view` (item 3). Every other sub-group follows the red→green pattern. The 8 sub-groups are independent and may be committed in any order; recommended order is bottom-to-top of the commit log shown at the end of this PR section.
>
> **Three sub-groups require real impl changes** (T-COV-4, T-COV-10, T-COV-16). The other 5 sub-groups have green commits that observe the test passing against existing behavior. The 3 non-trivial greens are the only commits that touch `app/`; everything else is test-only.

### Sub-group A: PatientPolicy@view drift (item 3) — 2 tasks, 2 commits, ~50 LOC

- [x] **T-COV-3 [RED]** — `test(api): ShowPatientTest 403 FORBIDDEN for a doctor with no shared appointments`
  - File: `tests/Feature/Api/ShowPatientTest.php` (MOD, +1 scenario, ~25 LOC)
  - Scenario: `it('returns 403 FORBIDDEN for a doctor with no shared appointments')` — creates a doctor `D` (no schedule) + a patient `Q` (no appointment with `D`), acts as `D`, calls `GET /api/patients/{Q.id}`, asserts `403` + `error.code = 'FORBIDDEN'`
  - **MUST FAIL** at this commit: current `PatientPolicy@view` returns true for any doctor (line 26-28 of `app/Policies/PatientPolicy.php`), so the response is `200` and the assertion fails
  - Commit: `test(api): ShowPatientTest 403 FORBIDDEN for a doctor with no shared appointments (red)`
  - Test gate: `vendor/bin/pest --filter='ShowPatientTest.*403 FORBIDDEN for a doctor with no shared'` exits non-zero
  - Dependency: T-API-29 (the `GET /api/patients/{id}` route + `PatientController@show` are already in place from agenda-http PR 3)

- [x] **T-COV-4 [GREEN]** — `feat(policy): PatientPolicy@view enforces shared-appointment check for doctors`
  - File: `app/Policies/PatientPolicy.php` (MOD, ~+10 LOC, +2 assertions on tests)
  - Logic: in `view(User $actor, Patient $patient): bool`, the doctor branch (currently `$actor->isDoctor() return true;`) becomes:
    ```php
    if ($actor->isDoctor()) {
        return $actor->doctor?->appointments()
            ->where('patient_id', $patient->id)
            ->exists() ?? false;
    }
    ```
    The query is a single `exists()` call (no N+1); mirrors the `AppointmentPolicy@view` pattern from `agenda-core PR 3`. The `?->` nullsafe handles the edge case where a doctor `User` has no `Doctor` profile (defensive; shouldn't happen but kept for parity with `AppointmentPolicy@view`).
  - **Side effect verified**: this change also re-affirms item 3's *positive* scenario (doctor with an appointment) in `ShowPatientTest` (line 46-65) which already creates an appointment between doctor and patient — that test continues to pass.
  - **MUST PASS** at this commit: the new scenario passes; the 3 pre-existing `ShowPatientTest` scenarios continue to pass (admin 200, self 200, assigned doctor 200, other patient 403).
  - Commit: `feat(policy): PatientPolicy@view enforces shared-appointment check for doctors (green)`
  - Test gate: `vendor/bin/pest --filter=ShowPatientTest` exits 0 (4 scenarios: the 3 pre-existing + the 1 new)
  - Dependency: T-COV-3

### Sub-group B: ShowAppointmentTest cross-coverage + admin (items 1+2) — 2 tasks, 2 commits, ~40 LOC

- [x] **T-COV-1 [RED]** — `test(api): ShowAppointmentTest 403 for another doctor + 200 for admin (red)`
  - File: `tests/Feature/Api/ShowAppointmentTest.php` (MOD, +2 scenarios, ~40 LOC)
  - Scenarios:
    - `it('returns 403 FORBIDDEN for a doctor from a different coverage')` — creates a SECOND doctor `D2` + a SECOND patient, books an appointment for `D2` + patient2, logs in as `D` (the first doctor), calls `GET /api/appointments/{A2.id}`, asserts `403` + `error.code = 'FORBIDDEN'` (this exercises the `AppointmentPolicy@view` branch where doctor is not the assigned doctor)
    - `it('returns 200 for an admin reading any appointment')` — creates an admin via `\App\Models\User::factory()->admin()->create()`, calls `GET /api/appointments/{A.id}`, asserts `200` + `data.id = A.id` (exercises the admin branch of `AppointmentPolicy@view`)
  - **MUST PASS** at this commit: the current `AppointmentPolicy@view` (lines 33-48 of `app/Policies/AppointmentPolicy.php`) already implements the cross-coverage 403 and admin 200 correctly. The tests are new but the impl is correct. RED is "test does not exist yet" → adding the tests makes them pass on first run. The convention from `agenda-http` PR 1 T-API-7 (test+impl in one commit when trivial) is the precedent; here we keep red+green separate to preserve the audit trail from `agenda-core`.
  - Commit: `test(api): ShowAppointmentTest 403 for another doctor + 200 for admin (red)`
  - Test gate: `vendor/bin/pest --filter=ShowAppointmentTest` exits 0 on first run (5 scenarios: the 3 pre-existing + the 2 new)
  - Dependency: T-API-19 (route + controller are in place from `agenda-http` PR 3)

- [x] **T-COV-2 [GREEN]** — `test(api): ShowAppointmentTest 403 for another doctor + 200 for admin (green)`
  - File: none
  - Commit: `test(api): ShowAppointmentTest 403 for another doctor + 200 for admin (green)` — a trivial "tests pass" observation commit (no `app/` changes). The commit body MUST cite the impl files that make the tests pass (`app/Policies/AppointmentPolicy.php` lines 33-48) for auditability.
  - **TDD exception documented**: the red and green are nominally separate commits to preserve the agenda-core red→green audit trail, but the green is a "test runs successfully" observation — no impl change. The agenda-http PR 1 T-API-7 (test+impl in one commit because route registration is trivial) is the precedent; here the test addition is equally trivial.
  - Test gate: `vendor/bin/pest --filter=ShowAppointmentTest` exits 0
  - Dependency: T-COV-1

### Sub-group C: ExceptionMappingTest ROUTE_NOT_FOUND (item 4) — 2 tasks, 2 commits, ~15 LOC

- [x] **T-COV-5 [RED]** — `test(api): ExceptionMappingTest 404 ROUTE_NOT_FOUND for an unknown route (red)`
  - File: `tests/Feature/Api/ExceptionMappingTest.php` (MOD, +1 scenario, ~15 LOC)
  - Scenario: `it('returns 404 ROUTE_NOT_FOUND for an unknown route')` — uses a valid bearer token via `$this->actingAs($this->user)`, calls `GET /api/this-route-does-not-exist`, asserts `404` + `error.code = 'ROUTE_NOT_FOUND'` (NOT `NOT_FOUND`, which is reserved for missing resources)
  - **MUST PASS** at this commit: the `ErrorResponse::resolve()` arm for `NotFoundHttpException` (line 107-109 of `app/Http/Responses/Api/ErrorResponse.php`) already maps to `[404, 'ROUTE_NOT_FOUND', ...]`. RED is "test does not exist yet".
  - Commit: `test(api): ExceptionMappingTest 404 ROUTE_NOT_FOUND for an unknown route (red)`
  - Test gate: `vendor/bin/pest --filter='ExceptionMappingTest.*ROUTE_NOT_FOUND'` exits 0 on first run
  - Dependency: T-API-6 (exception handler is in place from `agenda-http` PR 1)

- [x] **T-COV-6 [GREEN]** — `test(api): ExceptionMappingTest 404 ROUTE_NOT_FOUND for an unknown route (green)`
  - File: none
  - Commit: `test(api): ExceptionMappingTest 404 ROUTE_NOT_FOUND for an unknown route (green)` — trivial "tests pass" observation commit. Commit body cites `app/Http/Responses/Api/ErrorResponse.php` line 107-109.
  - **TDD exception documented**: same as T-COV-2 — the red→green split is for auditability; no impl change.
  - Test gate: `vendor/bin/pest --filter=ExceptionMappingTest` exits 0 (11 scenarios: the 10 pre-existing + the 1 new)
  - Dependency: T-COV-5

### Sub-group D: TimezoneResolutionTest default (item 5) — 2 tasks, 2 commits, ~30 LOC

- [x] **T-COV-7 [RED]** — `test(api): TimezoneResolutionTest defaults to clinic.timezone when ?tz= is omitted (red)`
  - File: `tests/Feature/Api/TimezoneResolutionTest.php` (NEW, ~30 LOC, 1 scenario)
  - Scenario: `it('defaults to clinic.timezone when ?tz= is omitted')` — calls `config(['clinic.timezone' => 'America/Argentina/Buenos_Aires'])`, creates a doctor + schedule for 3 days from now (same pattern as `ListSlotsTest`), logs in as the doctor, calls `GET /api/doctors/{id}/slots?date=YYYY-MM-DD` (NO `?tz=` query), asserts `200` + the first slot's `start_time` ends with `-03:00` (the resolved TZ offset for `America/Argentina/Buenos_Aires`)
  - **Endpoint choice**: `/api/doctors/{id}/slots` is used (NOT `/api/auth/me` which doesn't render datetimes). The `SlotResource` formats datetimes with `$request->attributes->get('tz')` (line 81 of `DoctorController::slots`), so the resolved TZ is exposed in the response payload.
  - **MUST PASS** at this commit: the `ResolveTimezone` middleware (from `agenda-http` PR 1, `app/Http/Middleware/ResolveTimezone.php`) already falls back to `config('clinic.timezone')` when `?tz=` is absent. RED is "test does not exist yet".
  - Commit: `test(api): TimezoneResolutionTest defaults to clinic.timezone when ?tz= is omitted (red)`
  - Test gate: `vendor/bin/pest --filter=TimezoneResolutionTest` exits 0 on first run
  - Dependency: T-API-23 (`/api/doctors/{id}/slots` route + controller are in place from `agenda-http` PR 3)

- [x] **T-COV-8 [GREEN]** — `test(api): TimezoneResolutionTest defaults to clinic.timezone when ?tz= is omitted (green)`
  - File: none
  - Commit: `test(api): TimezoneResolutionTest defaults to clinic.timezone when ?tz= is omitted (green)` — trivial "tests pass" observation commit. Commit body cites `app/Http/Middleware/ResolveTimezone.php` (fallback logic) + `app/Http/Resources/Api/SlotResource.php` (TZ formatting).
  - **TDD exception documented**: same as T-COV-2 — red→green split is for auditability; no impl change. The new test file IS the "structural" deliverable of this sub-group.
  - Test gate: `vendor/bin/pest --filter=TimezoneResolutionTest` exits 0
  - Dependency: T-COV-7

### Sub-group E: BookAppointmentTest TZ interpretation + non-patient 403 (items 6+9) — 2 tasks, 2 commits, ~55 LOC

- [x] **T-COV-9 [RED]** — `test(api): BookAppointmentTest TZ interpretation in write body + non-patient 403 (red)`
  - File: `tests/Feature/Api/BookAppointmentTest.php` (MOD, +3 scenarios, ~55 LOC)
  - Scenarios:
    - `it('interprets the write body in the resolved TZ and stores UTC')` (item 6) — sends `POST /api/appointments?tz=America/Argentina/Buenos_Aires` with body `doctor_id + start_time='2026-06-15T10:00:00-03:00'`, asserts `201` + `data.start_time` ends with `-03:00`, then asserts the DB column `appointments.start_time` is `'2026-06-15 13:00:00'` UTC (via `$appointment->getAttributes()['start_time']` or a raw `DB::table('appointments')->where('id', ...)->value('start_time')`)
    - `it('returns 403 FORBIDDEN for a non-patient actor (admin)')` (item 9, admin) — creates an admin via `User::factory()->admin()->create()`, calls `POST /api/appointments` with a valid body, asserts `403` + `error.code = 'FORBIDDEN'`
    - `it('returns 403 FORBIDDEN for a non-patient actor (doctor)')` (item 9, doctor) — creates a doctor, logs in as the doctor, calls `POST /api/appointments`, asserts `403` + `error.code = 'FORBIDDEN'`
  - **Item 6 MUST PASS** at this commit: the controller's `store()` method (line 116-119 of `app/Http/Controllers/Api/AppointmentController.php`) already does the `tz->toUtc(...)` conversion. RED is "test does not exist yet".
  - **Item 9 MUST FAIL** at this commit: the current `BookAppointmentRequest::authorize()` (line 31-43 of `app/Http/Requests/Api/BookAppointmentRequest.php`) returns true for admin + doctor + patient — the request flows through to the controller, the `patient_id` validation is satisfied (the rules make it required for non-patient actors), and the appointment is created → `201`. The new test expects `403`. **The red is REAL for item 9.**
  - Commit: `test(api): BookAppointmentTest TZ interpretation in write body + non-patient 403 (red)`
  - Test gate: `vendor/bin/pest --filter=BookAppointmentTest` exits non-zero on this commit only (item 9 fails)
  - Dependency: T-API-11 (`POST /api/appointments` route + controller are in place from `agenda-http` PR 2)

- [x] **T-COV-10 [GREEN]** — `feat(api): BookAppointmentRequest::authorize() requires isPatient() (green)`
  - File: `app/Http/Requests/Api/BookAppointmentRequest.php` (MOD, ~3 LOC change)
  - Logic: in `authorize(): bool`, the `return $user->isAdmin() || $user->isDoctor() || $user->isPatient();` becomes `return $user?->isPatient() ?? false;`. The `patient_id` field requirement in `rules()` (line 59-61) becomes dead code (no non-patient actor will reach the rules), but kept as defense-in-depth (a request that bypassed `authorize()` would still 422 on `patient_id`).
  - **Spec alignment**: this matches the spec scenario "POST /api/appointments with a non-patient actor returns 403 FORBIDDEN" (REQ-API-7 §5, line 223-226 of `openspec/specs/agenda/api/spec.md`) which explicitly states "(only patients book)". The current impl was a spec/implementation drift, similar in shape to item 3.
  - **Side effect verified**: the 4 pre-existing `BookAppointmentTest` scenarios all use a patient actor, so they continue to pass. The 422 `doctor_id` validation scenario continues to pass (patient actors still go through the form request validation).
  - **MUST PASS** at this commit: all 3 new scenarios pass + the 4 pre-existing scenarios continue to pass.
  - Commit: `feat(api): BookAppointmentRequest::authorize() requires isPatient() (green)`
  - Test gate: `vendor/bin/pest --filter=BookAppointmentTest` exits 0 (7 scenarios: the 4 pre-existing + the 3 new)
  - Dependency: T-COV-9

### Sub-group F: ListAppointmentsTest per_page bounds + no-auth (items 7+8+10) — 2 tasks, 2 commits, ~40 LOC

- [x] **T-COV-11 [RED]** — `test(api): ListAppointmentsTest per_page bounds + no-auth 401 (red)`
  - File: `tests/Feature/Api/ListAppointmentsTest.php` (MOD, +3 scenarios, ~40 LOC)
  - Scenarios:
    - `it('rejects per_page above the maximum with 422 VALIDATION_ERROR')` (item 7) — calls `GET /api/appointments?per_page=200` (max is 100 per `ListAppointmentsRequest::rules()` line 46), asserts `422` + `error.code = 'VALIDATION_ERROR'` + `error.details.per_page` is an array
    - `it('rejects per_page below 1 with 422 VALIDATION_ERROR')` (item 8) — calls `GET /api/appointments?per_page=0` (min is 1 per the same rules line), asserts `422` + `error.code = 'VALIDATION_ERROR'`
    - `it('returns 401 UNAUTHENTICATED for an unauthenticated request')` (item 10) — calls `GET /api/appointments` WITHOUT `actingAs(...)`, asserts `401` + `error.code = 'UNAUTHENTICATED'`
  - **MUST PASS** at this commit: all 3 are existing behavior. The `per_page` rules (`min:1`, `max:100` on line 46) trigger `ValidationException` → 422 VALIDATION_ERROR via `ErrorResponse::resolve()` line 62-64. The `auth:sanctum` middleware on `/api/*` (registered in `routes/api.php`) rejects unauthenticated requests with 401 UNAUTHENTICATED via `ErrorResponse::resolve()` line 66-83. RED is "test does not exist yet".
  - Commit: `test(api): ListAppointmentsTest per_page bounds + no-auth 401 (red)`
  - Test gate: `vendor/bin/pest --filter=ListAppointmentsTest` exits 0 on first run (7 scenarios: the 4 pre-existing + the 3 new)
  - Dependency: T-API-17 (route + controller are in place from `agenda-http` PR 3)

- [x] **T-COV-12 [GREEN]** — `test(api): ListAppointmentsTest per_page bounds + no-auth 401 (green)`
  - File: none
  - Commit: `test(api): ListAppointmentsTest per_page bounds + no-auth 401 (green)` — trivial "tests pass" observation commit. Commit body cites `app/Http/Requests/Api/ListAppointmentsRequest.php` line 46 (`per_page` rule) + `routes/api.php` (`auth:sanctum` middleware) + `app/Http/Responses/Api/ErrorResponse.php` lines 62-83 (envelope mapping).
  - **TDD exception documented**: same as T-COV-2 — red→green split is for auditability; no impl change.
  - Test gate: `vendor/bin/pest --filter=ListAppointmentsTest` exits 0
  - Dependency: T-COV-11

### Sub-group G: ShowDoctorTest 404 NOT_FOUND (item 11) — 2 tasks, 2 commits, ~15 LOC

- [x] **T-COV-13 [RED]** — `test(api): ShowDoctorTest 404 NOT_FOUND for a missing doctor (red)`
  - File: `tests/Feature/Api/ShowDoctorTest.php` (MOD, +1 scenario, ~15 LOC)
  - Scenario: `it('returns 404 NOT_FOUND for a missing doctor')` — uses a valid bearer token (the patient from `beforeEach`), calls `GET /api/doctors/999999` (a non-existent ID), asserts `404` + `error.code = 'NOT_FOUND'` (NOT `ROUTE_NOT_FOUND`, because the route `/api/doctors/{doctor}` exists — only the resource is missing)
  - **MUST PASS** at this commit: the `ModelNotFoundException` arm in `ErrorResponse::resolve()` (line 89-91) and the Laravel-prepareException recovery arm (line 99-101) both map to `[404, 'NOT_FOUND', ...]`. RED is "test does not exist yet".
  - Commit: `test(api): ShowDoctorTest 404 NOT_FOUND for a missing doctor (red)`
  - Test gate: `vendor/bin/pest --filter='ShowDoctorTest.*NOT_FOUND'` exits 0 on first run
  - Dependency: T-API-23 (`/api/doctors/{id}` route + controller are in place from `agenda-http` PR 3)

- [x] **T-COV-14 [GREEN]** — `test(api): ShowDoctorTest 404 NOT_FOUND for a missing doctor (green)`
  - File: none
  - Commit: `test(api): ShowDoctorTest 404 NOT_FOUND for a missing doctor (green)` — trivial "tests pass" observation commit. Commit body cites `app/Http/Responses/Api/ErrorResponse.php` lines 89-101.
  - **TDD exception documented**: same as T-COV-2 — red→green split is for auditability; no impl change.
  - Test gate: `vendor/bin/pest --filter=ShowDoctorTest` exits 0 (3 scenarios: the 2 pre-existing + the 1 new)
  - Dependency: T-COV-13

### Sub-group H: ConcurrentDoubleBookHttpTest conflicting_appointment_id (item 12) — 2 tasks, 2 commits, ~30 LOC

- [x] **T-COV-15 [RED]** — `test(api): ConcurrentDoubleBookHttpTest includes conflicting_appointment_id in 409 details (red)`
  - File: `tests/Feature/Api/ConcurrentDoubleBookHttpTest.php` (MOD, +1 scenario, ~30 LOC)
  - Scenario: `it('includes conflicting_appointment_id in the 409 details')` — extends the existing race test (line 39-96 of the current file): after the 409 response is captured, asserts `error.code = 'SLOT_NOT_AVAILABLE'` (already asserted, kept) + `error.details.conflicting_appointment_id` equals the winning id captured from the 201 response. MariaDB-only (`->skipOnSqlite()` preserved via the existing `markTestSkipped` guard).
  - **MUST FAIL** at this commit: the current `ErrorResponse::resolve()` for `DomainException` (line 111-118) returns `null` for details. The new assertion `error.details.conflicting_appointment_id = $winningId` will fail because `error.details` is missing entirely from the payload (the `if ($details !== null)` guard on line 42 of `ErrorResponse::fromException` drops the field). **The red is REAL.**
  - Commit: `test(api): ConcurrentDoubleBookHttpTest includes conflicting_appointment_id in 409 details (red)`
  - Test gate: `DB_CONNECTION=mariadb vendor/bin/pest --filter='ConcurrentDoubleBookHttpTest.*conflicting_appointment_id'` exits non-zero on this commit only
  - Dependency: T-API-14 (the existing race test is in place from `agenda-http` PR 2)

- [x] **T-COV-16 [GREEN]** — `feat(api): ErrorResponse includes conflicting_appointment_id for SlotNotAvailableException (green)`
  - Files:
    - `app/Exceptions/Domain/SlotNotAvailableException.php` (MOD, ~+8 LOC: add `public function getConflictingAppointmentId(): ?int { return $this->conflictingAppointmentId; }` + constructor injection `$conflictingAppointmentId` + a named static constructor `static withConflict(int $id): self`)
    - `app/Http/Responses/Api/ErrorResponse.php` (MOD, ~+10 LOC: in the `DomainException` arm, check if the exception is a `SlotNotAvailableException` AND the conflicting id is non-null; if so, return `[..., [..., 'conflicting_appointment_id' => $e->getConflictingAppointmentId()]]`. The non-SlotNotAvailable path remains `null` details.)
    - `app/Actions/BookAppointmentAction.php` (MOD, ~+3 LOC: when the slot-lookup guard finds the conflicting row, throw `SlotNotAvailableException::withConflict($existing->id)` instead of `new SlotNotAvailableException(...)`).
  - **Why this is the right fix**: the spec scenario explicitly states "The losing response includes the conflicting appointment id" (REQ-CONC-HTTP-1 §2 in `openspec/specs/agenda/concurrency-http/spec.md`). The exception carries the conflicting id; the error envelope surfaces it under `error.details`. The HTTP race test asserts the full chain.
  - **MUST PASS** at this commit: the new scenario passes + the existing race test scenario continues to pass.
  - Commit: `feat(api): ErrorResponse includes conflicting_appointment_id for SlotNotAvailableException (green)`
  - Test gate: `DB_CONNECTION=mariadb vendor/bin/pest --filter=ConcurrentDoubleBookHttpTest` exits 0 (2 scenarios: the 1 pre-existing + the 1 new)
  - Dependency: T-COV-15

### Verification

- [x] **T-COV-17 [VERIFICATION]** — `chore(test): verify agenda-test-coverage test suite + route:list gates on MariaDB`
  - File: none
  - Commit: `chore(test): verify agenda-test-coverage test suite + route:list gates on MariaDB`
  - The PR 1 acceptance gate (all must pass before merge):
    1. `vendor/bin/pest` (SQLite in-memory) → 130 passed + 3 skipped (was 118 passed + 3 skipped; +12 new scenarios)
    2. `DB_CONNECTION=mariadb php artisan migrate:fresh --env=testing` → exits 0 (no schema changes)
    3. `DB_CONNECTION=mariadb vendor/bin/pest` → 133 passed (was 121; +12 new scenarios; the new `conflicting_appointment_id` scenario joins the existing `ConcurrentDoubleBookHttpTest` race test on MariaDB)
    4. `php artisan route:list --path=api` → 18 routes (unchanged from `agenda-http` archive; this change adds no routes)
    5. `php artisan tinker --execute="echo App\Models\Patient::first()?->user->createToken('manual-test')->plainTextToken;"` → mints a valid token (sanity check the auth flow + the PatientPolicy change end-to-end)
    6. `git log --oneline -17` shows the expected commit shape (see below) with red→green pair order preserved
  - Test gate: all 6 above pass
  - Dependency: T-COV-16

---

## PR 1 expected commit log (bottom-to-top, 17 commits)

```
<hash> chore(test): verify agenda-test-coverage test suite + route:list gates on MariaDB
<hash> feat(api): ErrorResponse includes conflicting_appointment_id for SlotNotAvailableException (green)        [T-COV-16]
<hash> test(api): ConcurrentDoubleBookHttpTest includes conflicting_appointment_id in 409 details (red)          [T-COV-15]
<hash> test(api): ShowDoctorTest 404 NOT_FOUND for a missing doctor (green)                                    [T-COV-14]
<hash> test(api): ShowDoctorTest 404 NOT_FOUND for a missing doctor (red)                                      [T-COV-13]
<hash> test(api): ListAppointmentsTest per_page bounds + no-auth 401 (green)                                    [T-COV-12]
<hash> test(api): ListAppointmentsTest per_page bounds + no-auth 401 (red)                                      [T-COV-11]
<hash> feat(api): BookAppointmentRequest::authorize() requires isPatient() (green)                              [T-COV-10]
<hash> test(api): BookAppointmentTest TZ interpretation in write body + non-patient 403 (red)                    [T-COV-9]
<hash> test(api): TimezoneResolutionTest defaults to clinic.timezone when ?tz= is omitted (green)               [T-COV-8]
<hash> test(api): TimezoneResolutionTest defaults to clinic.timezone when ?tz= is omitted (red)                 [T-COV-7]
<hash> test(api): ExceptionMappingTest 404 ROUTE_NOT_FOUND for an unknown route (green)                         [T-COV-6]
<hash> test(api): ExceptionMappingTest 404 ROUTE_NOT_FOUND for an unknown route (red)                           [T-COV-5]
<hash> feat(policy): PatientPolicy@view enforces shared-appointment check for doctors (green)                  [T-COV-4]
<hash> test(api): ShowPatientTest 403 FORBIDDEN for a doctor with no shared appointments (red)                  [T-COV-3]
<hash> test(api): ShowAppointmentTest 403 for another doctor + 200 for admin (green)                            [T-COV-2]
<hash> test(api): ShowAppointmentTest 403 for another doctor + 200 for admin (red)                              [T-COV-1]
```

**Auditability check**: the red→green pair order is preserved within every sub-group. The 5 trivial sub-groups (B, C, D, F, G) have a red and a green that are both test-only — the green is a "tests pass" observation. The 3 non-trivial sub-groups (A, E, H) have a red that is a test commit and a green that is a real impl change.

---

## PR 1 verification (must all pass before merge)

1. `vendor/bin/pest` on SQLite → **130 passed + 3 skipped** (was 118+3; +12 new scenarios: 2 from ShowAppointmentTest, 1 from ShowPatientTest, 1 from ExceptionMappingTest, 1 from TimezoneResolutionTest, 3 from BookAppointmentTest, 3 from ListAppointmentsTest, 1 from ShowDoctorTest)
2. `DB_CONNECTION=mariadb vendor/bin/pest` → **133 passed** (was 121; +12 new scenarios; the new `conflicting_appointment_id` assertion joins the existing `ConcurrentDoubleBookHttpTest` race test on MariaDB and passes)
3. `php artisan migrate:fresh --env=testing` → exits 0 (no schema changes; transport/policy/test-only)
4. `php artisan route:list --path=api` → 18 routes (unchanged from `agenda-http` archive)
5. `php artisan tinker --execute="echo App\Models\Patient::first()?->user->createToken('manual-test')->plainTextToken;"` → mints a valid token (sanity check the auth flow + the PatientPolicy change end-to-end)
6. `git log --oneline -17` shows the expected commit shape (above) with red→green pair order preserved
7. **Re-run `sdd-verify` after merge**: should return `pass` (the 12 ADDED scenarios are now covered; the 3 cosmetic SUGGESTIONs remain informational and are not blocking)

---

## PR 1 known risks to verify

- **R-COV-1** (Med, security): `PatientPolicy@view` change affects ONLY the `GET /api/patients/{id}` endpoint. The grep `PatientPolicy` across `app/` returns 4 matches: `app/Policies/PatientPolicy.php` (the policy itself), `app/Providers/AppServiceProvider.php` (the `Gate::policy()` registration), `app/Http/Controllers/Api/PatientController.php` (the only consumer via `$this->authorize('view', $patient)`). No other code path uses this policy. **Verified** by grep.
- **R-COV-2** (Med, contract): the `conflicting_appointment_id` field name in the spec (`error.details.conflicting_appointment_id`, REQ-CONC-HTTP-1 §2) MUST match the implementation. The current `ErrorResponse::resolve()` for `DomainException` (line 111-118) returns `null` details; the GREEN commit (T-COV-16) adds the field by enhancing `SlotNotAvailableException` with `withConflict(int $id)` + a getter. If the spec's field name ever changes (e.g. to `existing_appointment_id`), the test + impl + spec MUST be aligned before archive.
- **R-COV-3** (Low): the `per_page` max value is **100** (confirmed in `app/Http/Requests/Api/ListAppointmentsRequest.php` line 46: `'max:100'`). The test uses `per_page=200` to exercise the rejection. If the max is changed in a future change, the test should be updated.
- **R-COV-4** (Low): the `TimezoneResolutionTest` (item 5) uses `/api/doctors/{id}/slots` as the endpoint that exposes the resolved TZ. `/api/auth/me` was rejected because it doesn't render datetimes. The chosen endpoint renders `start_time` + `end_time` datetimes via `SlotResource`, which formats with the resolved TZ. Alternative: assert on an `X-Timezone` response header if the middleware ever sets one. The current choice is the minimum change with the highest signal.
- **R-COV-5** (Med, security boundary): `BookAppointmentRequest::authorize()` change (T-COV-10) tightens the booking surface to patient-only. The current impl allowed admin + doctor to book on behalf of patients via a `patient_id` field in the body. The spec says "only patients book" (REQ-API-7 §5). This is a SPEC/IMPLEMENTATION DRIFT (in addition to item 3's PatientPolicy drift) — the orchestrator should surface this to the user during `sdd-apply` for confirmation before the GREEN commit lands.
- **R-COV-6** (Med, contract): the `ErrorResponse` change for `conflicting_appointment_id` (T-COV-16) requires modifying `SlotNotAvailableException` to carry the conflicting id. The exception's constructor is currently parameterless; the new `withConflict(int $id)` named constructor is the additive change. **Verify** that no other call site constructs `SlotNotAvailableException` directly with a custom message that would be lost — the message is preserved as the second arg to `withConflict($id, $message = 'Slot not available.')`.
- **R-COV-7** (Low): the `PatientPolicy@view` check `$actor->doctor?->appointments()->where('patient_id', $patient->id)->exists()` is a single `exists()` query (no N+1). For a doctor with many appointments, this remains O(1) — Eloquent compiles it to a `SELECT EXISTS(SELECT * FROM appointments WHERE doctor_id = ? AND patient_id = ? LIMIT 1)` query. **Verified** by reading the existing `AppointmentPolicy@view` pattern which uses the same idiom.

---

## Open questions for sdd-apply (3 from the proposal + 1 NEW)

1. **`PatientPolicy@view` semantics** (proposal §Open questions #1, scenario #3) — default: "at least one appointment (any state, past or future) between the doctor and the patient". If a temporal qualifier is needed (e.g. "non-cancelled", "future", "within the last 12 months"), the canonical spec MUST be amended and the policy tightened accordingly. **sdd-apply should confirm with the user before T-COV-4 lands.**
2. **`TimezoneResolutionTest` location** (proposal §Open questions #2, scenario #5) — default: new file at `tests/Feature/Api/TimezoneResolutionTest.php`. Alternative: extend the existing `ListSlotsTest` with a `?tz=` assertion. **sdd-apply should confirm; new file is the recommended default.**
3. **`conflicting_appointment_id` field name** (proposal §Open questions #3, scenario #12) — default: match the impl. If the impl uses a different field name (it doesn't — confirmed via `ErrorResponse::resolve()` line 111-118), align the spec + impl + test. **sdd-apply should confirm the field name BEFORE T-COV-15 lands to avoid a rename mid-PR.**
4. **(NEW) `BookAppointmentRequest::authorize()` tightening** — discovered during context load: the current `authorize()` (line 31-43) returns `true` for admin + doctor + patient, but the spec (REQ-API-7 §5) says "only patients book". This is a second spec/implementation drift, parallel in shape to item 3. The fix is a 1-line change in T-COV-10; the orchestrator MUST surface this to the user for confirmation BEFORE the GREEN commit lands, because it changes the API surface (admin + doctor actors can no longer book on behalf of patients via `POST /api/appointments`).

---

## Cross-task dependency graph

```
T-COV-1 → T-COV-2   (sub-group B: ShowAppointmentTest)
T-COV-3 → T-COV-4   (sub-group A: PatientPolicy change — NON-TRIVIAL)
T-COV-5 → T-COV-6   (sub-group C: ExceptionMappingTest)
T-COV-7 → T-COV-8   (sub-group D: TimezoneResolutionTest — new file)
T-COV-9 → T-COV-10  (sub-group E: BookAppointmentTest — NON-TRIVIAL, item 9)
T-COV-11 → T-COV-12 (sub-group F: ListAppointmentsTest)
T-COV-13 → T-COV-14 (sub-group G: ShowDoctorTest)
T-COV-15 → T-COV-16 (sub-group H: ErrorResponse change — NON-TRIVIAL, item 12)
T-COV-16 → T-COV-17 (verification)
```

The 8 sub-groups are INDEPENDENT of each other (no cross-dependencies). The only chain is the inner RED→GREEN pair within each sub-group, plus the final VERIFY commit. **Recommended commit order** (bottom-to-top of the commit log, matches the agenda-http precedent): A, B, C, D, E, F, G, H, VERIFY. The 3 non-trivial sub-groups (A, E, H) are spread evenly through the commit log so the impl changes are not clustered.

---

## Verification gates (per PR, all must pass before merge)

1. `vendor/bin/pest` (SQLite in-memory) → 130 passed + 3 skipped
2. `DB_CONNECTION=mariadb php artisan migrate:fresh --env=testing` → exits 0 (no schema changes)
3. `DB_CONNECTION=mariadb vendor/bin/pest` → 133 passed
4. `php artisan route:list --path=api` → 18 routes (unchanged from `agenda-http` archive)
5. `php artisan tinker --execute="echo App\Models\Patient::first()?->user->createToken('manual-test')->plainTextToken;"` → mints a valid token
6. `git log --oneline -17` shows the expected commit shape with red→green pair order preserved
7. **Re-run `sdd-verify`**: should return `pass` (the 12 ADDED scenarios are now covered; the 3 cosmetic SUGGESTIONs are informational, not blocking)

---

## Aggregate metrics

| Metric | PR 1 | Total |
|---|---|---|
| Tasks | 17 | **17** |
| RED commits (test files only) | 8 | **8** |
| GREEN commits (impl + observation) | 8 | **8** |
| STRUCTURAL commits | 0 | **0** |
| VERIFY commits | 1 | **1** |
| RED+GREEN single commits (TDD exception) | 0 (kept separate for auditability) | **0** |
| Trivial green commits (no impl change) | 5 (T-COV-2, T-COV-6, T-COV-8, T-COV-12, T-COV-14) | **5** |
| Non-trivial green commits (impl change) | 3 (T-COV-4, T-COV-10, T-COV-16) | **3** |
| Total commits | 17 | **17** |
| LOC estimate (hand-written) | ~235 | **~235** |
| New files (tests/) | 1 (`TimezoneResolutionTest.php`) | **1** |
| Modified files (tests/) | 7 (ShowAppointmentTest, ShowPatientTest, ExceptionMappingTest, BookAppointmentTest, ListAppointmentsTest, ShowDoctorTest, ConcurrentDoubleBookHttpTest) | **7** |
| Modified files (app/) | 4 (PatientPolicy, BookAppointmentRequest, ErrorResponse, SlotNotAvailableException) + 1 (BookAppointmentAction if T-COV-16 needs it) | **4-5** |
| New routes | 0 | **0** |
| Test scenario delta | +12 (2+1+1+1+3+3+1) | **+12** |
| TDD exceptions documented | 5 (trivial greens at T-COV-2, T-COV-6, T-COV-8, T-COV-12, T-COV-14) | **5** |
| Spec/implementation drifts closed | 2 (item 3 PatientPolicy, item 9 BookAppointmentRequest) | **2** |

**Note on commit math**: the orchestrator's brief said "25 commits" (12 items × 2 + 1 verify). The actual task list (T-COV-1..T-COV-17) groups items by test file: 7 test files × 1 RED+GREEN pair + 1 new file (T-COV-7+8) + 1 verify = 8 RED+GREEN pairs + 1 verify = **17 commits**. The "12 items" count in the proposal is the per-scenario count; the "17 commits" count is the per-test-file count. The discrepancy is resolved by the file grouping in the brief.
