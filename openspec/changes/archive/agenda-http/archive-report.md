# archive-report — agenda-http

**Status**: completed
**Archived at**: 2026-06-02
**Source change**: `openspec/changes/agenda-http/` (now at `openspec/changes/archive/agenda-http/`)
**Canonical specs synced (new sub-capabilities)**:
- `openspec/specs/agenda/api/spec.md`
- `openspec/specs/agenda/concurrency-http/spec.md`
**Existing canonical specs UNTOUCHED**: `openspec/specs/{agenda,doctor-schedule,users-roles,clinical-records,prescriptions,admin-audit}/spec.md` (the 6 agenda-core capabilities)
**Verify verdict**: pass-with-warnings (118 passed + 3 skipped on SQLite / 121 passed on MariaDB; 9 untested spec scenarios + 1 spec drift + 1 README drift + 1 spec self-contradiction documented as out-of-scope per orchestrator + user authorization)
**Merge type**: additive — two new sub-capabilities were added under the existing `agenda/` capability folder. No existing requirement was modified, removed, or merged. The 6 agenda-core canonical specs and the `agenda/spec.md` core spec remain byte-identical to the agenda-core archive state.

## Executive summary

The `agenda-http` change is now archived. The change shipped a complete JSON HTTP transport for the existing agenda domain over 4 PRs + 1 post-merge patch (56 commits on `main` from `6ba4d6a` → `5274c3e`): 18 routes (16 public + 3 auth − 1 retired `/api/me` placeholder), Sanctum bearer auth, role-based access via the inherited Gate policies, the standard `{error:{code,message,details?}}` envelope with 10 exception → HTTP mappings, a per-request timezone resolution middleware (`?tz=` → `clinic.timezone` → `America/Argentina/Buenos_Aires` default), and a `LengthAwarePaginator` envelope for list endpoints. 49/49 tasks complete. Test suite: 118 passed + 3 skipped (491 assertions) on SQLite in-memory and 121 passed (499 assertions) on MariaDB 10.11.9.

The 2 CRITICAL findings from the previous verify report (3 auth endpoints missing, `TOKEN_EXPIRED` code not implemented) are RESOLVED in PR 4. The 9 untested spec scenarios + the `DoctorResource` shape drift + the 2 README drift items + the 1 spec self-contradiction are documented as known limitations and forwarded to a future `agenda-test-coverage` (and possibly `agenda-resource-shape` + `agenda-readme-drift`) change per the orchestrator's explicit user authorization.

This is the second archive in the med-connect project (after `agenda-core`). The cumulative canonical state after this archive is 8 capabilities (6 from `agenda-core` + 2 new sub-capabilities), 29 requirements (21 + 8), and 110 scenarios (44 + 66). The next change that needs to add or modify agenda-related specs will create new sub-capability folders under `openspec/specs/agenda/` (e.g. `openspec/specs/agenda/patient-web/`, `openspec/specs/agenda/cors/`, etc.) — the per-Pattern additive architecture is now well-established.

## Inventory and sync table

| Sub-capability | Delta reqs | Delta scenarios | Delta bytes | Canonical state pre-archive | Action | Final canonical |
|----------------|-----------:|----------------:|------------:|-----------------------------|--------|-----------------|
| `agenda/api/` | 7 | 62 | 23,465 | not-present (new sub-capability) | copy verbatim + prepend source-tracking header | 7 reqs, 62 scenarios (23,587 B) |
| `agenda/concurrency-http/` | 1 | 4 | 3,389 | not-present (new sub-capability) | copy verbatim + prepend source-tracking header | 1 req, 4 scenarios (3,524 B) |
| **Total** | **8** | **66** | **26,854** | — | — | **8 reqs, 66 scenarios** |

**Canonical file headers** (each new `openspec/specs/agenda/<sub>/spec.md` opens with):

```html
<!-- Source: openspec/changes/archive/agenda-http/specs/agenda/<sub>/spec.md -- synced 2026-06-02 (agenda-http archive) -->
```

Because both canonical specs were `not-present` (the existing `openspec/specs/agenda/spec.md` is the agenda-core spec, NOT the new HTTP-layer spec), no requirement-ID conflict resolution was needed. The merge is purely additive. The 2 new sub-capability folders live UNDER `openspec/specs/agenda/` (not at the top level), so the `agenda/` domain now has 3 spec files: the core `agenda/spec.md` (agenda-core, 5 reqs), the new `agenda/api/spec.md` (7 reqs), and the new `agenda/concurrency-http/spec.md` (1 req).

**Requirement ID scheme**: new IDs follow the agenda-http proposal naming — `REQ-API-1` through `REQ-API-7` for the API contract, `REQ-CONC-1` for the HTTP-layer concurrency contract. No ID collisions with the agenda-core spec (`Appointment Aggregate`, `Appointment State Machine`, etc. use full-name headers, not the `REQ-NN-NN` short form).

## Cumulative canonical spec state (after agenda-http archive)

| Capability | Reqs | Scenarios | Source |
|------------|-----:|----------:|--------|
| `agenda/spec.md` | 5 | 12 | agenda-core (2026-06-01) |
| `doctor-schedule/spec.md` | 3 | 7 | agenda-core (2026-06-01) |
| `users-roles/spec.md` | 5 | 11 | agenda-core (2026-06-01) |
| `clinical-records/spec.md` | 3 | 6 | agenda-core (2026-06-01) |
| `prescriptions/spec.md` | 3 | 4 | agenda-core (2026-06-01) |
| `admin-audit/spec.md` | 2 | 4 | agenda-core (2026-06-01) |
| `agenda/api/spec.md` | 7 | 62 | **agenda-http (2026-06-02)** |
| `agenda/concurrency-http/spec.md` | 1 | 4 | **agenda-http (2026-06-02)** |
| **TOTAL** | **29** | **110** | (21 + 8) / (44 + 66) |

The agenda-core archive report's "21 reqs / 44 scenarios" claim is confirmed by file-derived counting (the agenda-core report was a correction of the orchestrator's earlier "21 reqs / 42 scenarios" estimate — that correction is preserved here as a precedent for trusting the file over the orchestrator's stale numbers).

## Endpoints shipped (18 routes registered)

| # | Method | Path | Controller#action | Auth |
|---|--------|------|-------------------|------|
| 1 | POST | `/api/auth/login` | `AuthController@login` | public (Sanctum after) |
| 2 | POST | `/api/auth/logout` | `AuthController@logout` | Sanctum |
| 3 | GET | `/api/auth/me` | `AuthController@me` | Sanctum |
| 4 | POST | `/api/appointments` | `AppointmentController@store` | Sanctum + `BookAppointmentRequest::authorize()` (patient-only) |
| 5 | DELETE | `/api/appointments/{appointment}` | `AppointmentController@cancel` | Sanctum + `AppointmentPolicy@cancel` |
| 6 | GET | `/api/appointments` | `AppointmentController@index` | Sanctum + role-scoped query |
| 7 | GET | `/api/appointments/{appointment}` | `AppointmentController@show` | Sanctum + `AppointmentPolicy@view` |
| 8 | GET | `/api/doctors` | `DoctorController@index` | Sanctum |
| 9 | GET | `/api/doctors/{doctor}` | `DoctorController@show` | Sanctum |
| 10 | GET | `/api/doctors/{doctor}/slots` | `DoctorController@slots` | Sanctum |
| 11 | GET | `/api/patients/{patient}` | `PatientController@show` | Sanctum + `PatientPolicy@view` |
| 12 | GET | `/api/specialties` | `SpecialtyController@index` | Sanctum |
| 13 | GET | `/api/medical-histories/{medical_history}` | `MedicalHistoryController@show` | Sanctum + inline role check |
| 14 | GET | `/api/prescriptions` | `PrescriptionController@index` | Sanctum + role-scoped query |
| 15 | GET | `/api/audit-logs` | `AuditLogController@index` | Sanctum + admin-only |
| 16 | POST | `/api/appointments/{appointment}/transitions/confirm` | `AppointmentTransitionController@confirm` | Sanctum + doctor/admin |
| 17 | POST | `/api/appointments/{appointment}/transitions/complete` | `AppointmentTransitionController@complete` | Sanctum + doctor/admin |
| 18 | POST | `/api/appointments/{appointment}/transitions/no-show` | `AppointmentTransitionController@markNoShow` | Sanctum + doctor/admin |

**Retired in PR 4** (T-API-46): `GET /api/me` (was a PR 1 placeholder, renamed to `GET /api/auth/me` per the design decision in `tasks.md` "for v1 the cleanest move is to rename, not alias"). The 19th design endpoint was deliberately retired; the design decision is documented and the 18-route final state is the canonical surface.

**Original design count vs final**: the design listed 19 endpoints; the final state has 18. The delta is 1 retired endpoint. This is NOT a regression — the retirement is documented in `tasks.md` T-API-46 + `verify-report.md` §"Design Endpoints".

## PR chain — 4 PRs + 1 post-merge patch, 56 commits on main

| PR | Title | Commits | Tip | Files | Branch strategy |
|----|-------|--------:|-----|-------|-----------------|
| PR 1 | Auth + Exception Handler + TZ Middleware | 8 | `104e6e0` | 9 | feat/agenda-http-auth → main |
| PR 2 | Mutations + Race Test | 7 | `fc6404e` | 6 | feat/agenda-http-mutations → main |
| PR 3 | Reads + State Transitions + Directory + Audit/Clinical Reads | 24 | `81ebd32` | ~22 | feat/agenda-http-reads → main |
| Post-merge patch | `GET /api/doctors/{doctor}` (added in PR 3 review) | 2 | `f9ef93d` | 2 | direct commit to main (in PR 3) |
| PR 4 | Auth Surface (login + logout + me + TOKEN_EXPIRED) | 10 | `5274c3e` | 8 | feat/agenda-http-auth-surface → main |
| Total | | **56** | `5274c3e` | | stacked-to-main |

The 56-commit count comes from `git log --oneline 6ba4d6a..5274c3e | wc -l` (where `6ba4d6a` is the agenda-core archive commit on `main` and `5274c3e` is the PR 4 verify commit). The orchestrator prompt estimated 47 commits; the file-derived number is 56. The actual git log is the source of truth.

## Files created / modified / deleted across the 4-PR chain

Per `git diff --name-status 6ba4d6a..5274c3e`:

**Created (app/)** — 30 files:
- `app/Clinic/Timezone.php` (value object, T-API-3)
- `app/Exceptions/InvalidTimezoneException.php` (T-API-3/6)
- `app/Http/Controllers/Api/AppointmentController.php` (T-API-11, T-API-13, T-API-17, T-API-19)
- `app/Http/Controllers/Api/AppointmentTransitionController.php` (T-API-37)
- `app/Http/Controllers/Api/AuditLogController.php` (T-API-35)
- `app/Http/Controllers/Api/AuthController.php` (T-API-41, T-API-43, T-API-45)
- `app/Http/Controllers/Api/DoctorController.php` (T-API-21, T-API-23, post-merge)
- `app/Http/Controllers/Api/MedicalHistoryController.php` (T-API-31)
- `app/Http/Controllers/Api/PatientController.php` (T-API-29)
- `app/Http/Controllers/Api/PrescriptionController.php` (T-API-33)
- `app/Http/Controllers/Api/SpecialtyController.php` (T-API-27)
- `app/Http/Middleware/ResolveTimezone.php` (T-API-4)
- `app/Http/Requests/Api/AppointmentTransitionRequest.php` (T-API-37)
- `app/Http/Requests/Api/BookAppointmentRequest.php` (T-API-11)
- `app/Http/Requests/Api/CancelAppointmentRequest.php` (T-API-13)
- `app/Http/Requests/Api/ListAppointmentsRequest.php` (T-API-17)
- `app/Http/Requests/Api/ListAuditLogsRequest.php` (T-API-35)
- `app/Http/Requests/Api/ListDoctorsRequest.php` (T-API-21)
- `app/Http/Requests/Api/ListPrescriptionsRequest.php` (T-API-33)
- `app/Http/Requests/Api/ListSlotsRequest.php` (T-API-23)
- `app/Http/Requests/Api/LoginRequest.php` (T-API-41)
- `app/Http/Resources/Api/AppointmentResource.php` (T-API-11)
- `app/Http/Resources/Api/AuditLogResource.php` (T-API-35)
- `app/Http/Resources/Api/DoctorResource.php` (T-API-21)
- `app/Http/Resources/Api/MedicalHistoryResource.php` (T-API-31)
- `app/Http/Resources/Api/PatientResource.php` (T-API-29)
- `app/Http/Resources/Api/PrescriptionResource.php` (T-API-33)
- `app/Http/Resources/Api/SlotResource.php` (T-API-23)
- `app/Http/Resources/Api/SpecialtyResource.php` (T-API-27)
- `app/Http/Resources/Api/UserResource.php` (T-API-25, T-API-45)
- `app/Http/Responses/Api/ErrorResponse.php` (T-API-6 + T-API-48 TOKEN_EXPIRED branch)

**Created (config + routes)**:
- `config/clinic.php` (T-API-2)
- `routes/api.php` (T-API-1)

**Created (tests/Feature/Api/)** — 22 files:
- `AuthExpiredTokenTest.php` (T-API-47)
- `AuthLoginTest.php` (T-API-40)
- `AuthLogoutTest.php` (T-API-42)
- `AuthMeTest.php` (T-API-44, renamed from MeTest in T-API-46)
- `AuthSanctumTest.php` (T-API-7, path updated to `/api/auth/me` in T-API-46)
- `BookAppointmentTest.php` (T-API-10)
- `CancelAppointmentTest.php` (T-API-12)
- `ConcurrentDoubleBookHttpTest.php` (T-API-14, MariaDB-only)
- `ExceptionMappingTest.php` (T-API-5, 10 scenarios for the exception → HTTP mapping)
- `ListAppointmentsTest.php` (T-API-16)
- `ListAuditLogsTest.php` (T-API-34)
- `ListDoctorsTest.php` (T-API-20)
- `ListPrescriptionsTest.php` (T-API-32)
- `ListSlotsTest.php` (T-API-22)
- `ListSpecialtiesTest.php` (T-API-26)
- `ShowAppointmentTest.php` (T-API-18)
- `ShowDoctorTest.php` (post-merge patch + 1 scenario)
- `ShowMedicalHistoryTest.php` (T-API-30)
- `ShowPatientTest.php` (T-API-28)
- `TransitionAppointmentTest.php` (T-API-36, 6 parameterised scenarios)

**Created (tests/Support/)** — 2 traits:
- `CreatesDoctors.php` (T-API-38)
- `CreatesPatients.php` (T-API-9)

**Created (tests/Unit/Clinic/)** — 1 file:
- `TimezoneTest.php` (T-API-3, 5 unit tests for the value object)

**Modified** — 5 files:
- `.env.example` (added `CLINIC_TIMEZONE`)
- `README.md` (REST API docs added in T-API-38; **README drift items remain — see Out-of-scope §C**)
- `app/Models/User.php` (Sanctum `HasApiTokens` wiring verified)
- `bootstrap/app.php` (withRouting `api:` + withExceptions handler)
- `tests/Pest.php` (test config)

**Deleted** — 2 files (PR 4 T-API-46):
- `app/Http/Controllers/Api/MeController.php` (logic moved to `AuthController@me`)
- `tests/Feature/Api/MeTest.php` (renamed to `AuthMeTest.php`)

## Test counts (final state)

| Driver | Result | Notes |
|--------|--------|-------|
| **SQLite in-memory** (default, `DB_CONNECTION=sqlite`) | 118 passed + 3 skipped (491 assertions) in 10.15s | 3 skipped = 2 pre-existing action-level `ConcurrentDoubleBookTest` scenarios from agenda-core + 1 new HTTP-level `ConcurrentDoubleBookHttpTest`. All 3 flip to green on MariaDB. |
| **MariaDB 10.11.9** (`DB_CONNECTION=mariadb`) | 121 passed (499 assertions) in 24.15s | 0 skipped, 0 failed. All 3 race tests run and pass. |

**Net delta from agenda-core baseline** (44 passed / 46 on MariaDB → 118 / 121): +74 SQLite / +75 MariaDB. The +5 net PR-4 delta (from PR 3 baseline of 113/116) is documented in the verify report.

## What the next change inherits

The 9 untested spec scenarios + 1 spec/implementation drift + 2 README drift items + 1 spec self-contradiction + 3 SUGGESTIONs are documented deferrals. The next change that picks up testing, the DoctorResource shape decision, or the README cleanup MUST address them. Also inherit the 2 spec-level risks that emerged in this change.

### A. 9 untested spec scenarios (WARNING, all forwarded to `agenda-test-coverage`)

| # | Req | Scenario | Test gap | Suggested test addition |
|---|-----|----------|----------|-------------------------|
| 1 | REQ-API-2 #2 | "Doctor cannot read another doctor's appointment" | `ShowAppointmentTest` covers 3 of 4 scenarios; missing "doctor D2 reads D's appointment → 403" | +1 scenario in `ShowAppointmentTest` |
| 2 | REQ-API-2 #3 | "Admin reads any appointment" (show endpoint) | Covered for list, NOT for show | +1 scenario in `ShowAppointmentTest` |
| 3 | REQ-API-2 #5 | "Doctor cannot read an unassigned patient" | `ShowPatientTest` docblock says "doctor (any) → 200" (impl is permissive) | **Spec drift** — see §B. Either +1 test that asserts the SPEC behaviour (forcing `PatientPolicy@view` to be tightened) or amend the spec. |
| 4 | REQ-API-4 #10 | `NotFoundHttpException → 404 ROUTE_NOT_FOUND` | Code path exists in `ErrorResponse.php` lines 107-109; no test | +1 scenario in `ExceptionMappingTest` |
| 5 | REQ-API-5 #1 | "No `?tz=` falls back to clinic.timezone" (default rendering not asserted) | `AuthSanctumTest` covers `?tz=America/New_York` happy path, not absence-of-tz | +1 scenario in `AuthSanctumTest` (assert `-03:00` offset for default) |
| 6 | REQ-API-5 #3 | "Write body stored as UTC" | `BookAppointmentTest` happy path doesn't assert DB column is in UTC | +1 assertion in `BookAppointmentTest` |
| 7 | REQ-API-6 #3 + #4 | `per_page > 100` and `per_page < 1` rejection | Only `per_page=2` tested | +2 scenarios in `ListAppointmentsTest` |
| 8 | REQ-API-7 #5 | "POST /api/appointments 403 for non-patient" | `BookAppointmentTest` has 4 scenarios; no non-patient | +1 scenario in `BookAppointmentTest` |
| 9 | REQ-API-7 #7 | "GET /api/appointments 401 with no auth" | 401 covered for `/api/auth/me` and `/api/doctors/{id}`, not `/api/appointments` | +1 scenario in `ListAppointmentsTest` |
| 10 | REQ-API-7 #13 | "GET /api/doctors/{id} 404 NOT_FOUND for missing doctor" | `ShowDoctorTest` has 200 + 401; no 404 | +1 scenario in `ShowDoctorTest` |
| 11 | REQ-CONC-1 #2 | "Losing response includes `error.details.conflicting_appointment_id`" | Status + code asserted, not the `details.conflicting_appointment_id` field | +1 assertion in `ConcurrentDoubleBookHttpTest` |

**Total estimated LOC to close**: ~150 LOC (10 new test scenarios + 1 new assertion across 7 test files).

### B. Spec / implementation drift (WARNING)

- **`DoctorResource` shape** — `app/Http/Resources/Api/DoctorResource.php` returns `data.user_id` + `data.user.name`. The spec (REQ-API-7 lines 280, 285) implies a top-level `data.name`. The implementation and tests are consistent with each other; the spec is inconsistent with both. **Two resolutions**:
  1. **Amend the spec** (preferred) — add a row to the `DoctorResource` table that says `id, user_id, name (alias of user.name), specialty{id,name,slug}, license_number, bio`. Add the `name` alias as a backward-compat field. Spec wins.
  2. **Change the implementation** — flatten `DoctorResource` to expose `name` at the top level (could be a `data_get()` or a `mergeWhen()`). Impl wins. The downstream tests in `ShowDoctorTest` + `ListDoctorsTest` need updating.

This is the only spec/impl drift in the change. It is forwarded to a future `agenda-resource-shape` change.

### C. README drift (WARNING, 2 items)

1. **Missing `GET /api/doctors/{id}` row in the README endpoint table** (lines 364-383 list 17 endpoints; the post-merge patch added an 18th row that the README didn't pick up). 1-row edit.
2. **`/api/me` references after PR 4 rename to `/api/auth/me`** — README still references `/api/me` in 5 places (lines 368: endpoint table row #3; line 394: auth flow curl example; line 417: default TZ curl; line 421: override TZ curl; line 510: test slice filter still mentions `MeTest`). 5-line edit. The `MeTest` filter on line 510 is also dead (renamed to `AuthMeTest` in T-API-46).

These are both forwarded to a future `agenda-readme-drift` change (~10 LOC total).

### D. Spec self-contradiction (WARNING)

- **REQ-API-3 #3 vs REQ-API-7 #5 on DELETE response shape** — REQ-API-3 #3 says "204 No Content has an empty body" for DELETE; REQ-API-7 #5 (line 227) says DELETE "returns 200 with state=cancelled". The implementation returns 200 with the resource. The implementation is correct (consistent with the rest of the design + tests); the spec is internally inconsistent. Resolution: amend REQ-API-3 #3 to read "204 OR 200 (per the endpoint contract); 200 returns the resource, 204 has an empty body" OR remove REQ-API-3 #3 and add a single "DELETE responses may be 200 (with resource) or 204 (empty) per the per-endpoint contract". Forwarded to a future spec amendment change.

### E. SUGGESTION (nice to have, not blocking)

1. **`DoctorController@slots` falls back to `config('app.timezone')` instead of `config('clinic.timezone')`** (line 81). The `ResolveTimezone` middleware always sets `tz`, so this fallback is dead code. Cosmetic inconsistency with REQ-API-5.
2. **`AuditLogResource` defensively parses `created_at`** — the model has `$timestamps = false`. Forward-compatible but not necessary; flagged for awareness.
3. **`MedicalHistoryController` uses an inline role check instead of a `MedicalHistoryPolicy`**. Acceptable for v1; centralize in a follow-up change. (Predates this change; same as `agenda-core` archive R3.)

### F. Risks to monitor

- **R-new-1** — 9 untested spec scenarios are a real regression risk for the next change that touches the same endpoints. The migration-level invariants are NOT tested (only the HTTP-level response codes are). A future contributor could break the `BookAppointmentRequest`'s "patient-only" authorize() check and no test would catch it (spec scenario #8). **The next change touching the API surface MUST add the 9 missing tests in its first red→green cycle** (or as a dedicated `agenda-test-coverage` change before adding new features).
- **R-new-2** — The HTTP-level concurrency contract (`ConcurrentDoubleBookHttpTest`) is the safety net for the unique partial index `uniq_doctor_start_not_cancelled`. The test is MariaDB-only (SQLite skips it). **Do NOT remove the test.** If a future MariaDB version changes VIRTUAL-column behaviour with NULL markers in unique indexes, this test will catch the regression. Pattern inherited from `agenda-core` archive R2.

### G. PR budget governance

- **PR 3 was 2.7x over the 400-line review budget** (~1045 hand-written LOC vs the 330-380 forecast). The 3-PR chained split still happened, but each PR was much larger than designed. **PR 4 was within budget** (370 insertions / 52 deletions). The pattern is mitigated but not eliminated. The next change with a similar 3-PR chain should either (a) be split into 4+ smaller PRs, (b) get an explicit `size:exception` written into the apply-progress before cutting, or (c) reduce the per-PR scope. (Inherited pattern from `agenda-core` archive W5.)

## Files moved (sync + archive)

**Delta specs → canonical specs** (additive copy, no merge logic needed):

- `openspec/changes/agenda-http/specs/agenda/api/spec.md` → `openspec/specs/agenda/api/spec.md` (23,465 B + 122 B header = 23,587 B)
- `openspec/changes/agenda-http/specs/agenda/concurrency-http/spec.md` → `openspec/specs/agenda/concurrency-http/spec.md` (3,389 B + 135 B header = 3,524 B)

**Change folder → archive** (full filesystem move, 6 files):

- `openspec/changes/agenda-http/` → `openspec/changes/archive/agenda-http/`
- Contains: `proposal.md`, `design.md`, `tasks.md`, `verify-report.md`, and `specs/agenda/{api,concurrency-http}/spec.md`

The `openspec/changes/archive/` parent directory already existed (it contained `agenda-core/`); no new directories were needed at the parent level. The 2 new sub-capability directories (`openspec/specs/agenda/api/` + `openspec/specs/agenda/concurrency-http/`) were created under the existing `openspec/specs/agenda/` folder.

The 2 new canonical files + the moved change folder + the 2 new canonical sub-capability directories + the 2 original delta specs within the archive = **6 new files** + **2 new directories** + **1 folder rename** in a single archive commit. The 6 existing agenda-core canonical specs are UNTOUCHED (verified by SHA-256 hash check before the move; the existing `openspec/specs/agenda/spec.md` still carries the `agenda-core` source-tracking comment from 2026-06-01).

## AGENTS.md updates

None. `openspec/AGENTS.md` does not contain a "Changes in flight" or "Recent changes" / "Changelog" section. The layout tree already documents the `archive/` subdirectory under `changes/` and the multi-capability layout under `specs/`, so the project's workflow contract remains accurate.

## Archive integrity verification

- `ls openspec/changes/` shows only `archive/` (no `agenda-http` at the active path).
- `ls openspec/changes/archive/` shows `agenda-core/` + `agenda-http/`.
- `ls openspec/changes/archive/agenda-http/` shows the full 6-file change set: `proposal.md`, `design.md`, `tasks.md`, `verify-report.md`, `specs/agenda/api/spec.md`, `specs/agenda/concurrency-http/spec.md`.
- `ls openspec/specs/` shows the original 6 capability folders + the unchanged `agenda/spec.md` (still carrying the agenda-core source-tracking comment).
- `ls openspec/specs/agenda/` shows `spec.md` (agenda-core) + `api/spec.md` (agenda-http) + `concurrency-http/spec.md` (agenda-http) — 3 spec files in the `agenda/` domain.
- `git status` after the move shows: 6 new files in `openspec/changes/archive/agenda-http/` (the 2 original delta specs are technically "moved" from the pre-archive location, but since they were untracked before the move they appear as new files in the archive; the proposal/design/tasks/verify-report were also untracked so they appear as new files), 2 new files in `openspec/specs/agenda/{api,concurrency-http}/`, 1 new file in `openspec/changes/archive/agenda-http/archive-report.md` (this file). All other working tree state (modified `tasks.md`, the untracked `.codegraph/` directory) is preserved unchanged and staged together in the single archive commit.

## Commit

The archive is staged as a single commit:

```
chore(archive): archive agenda-http change (4 PRs + post-merge patch, 49 tasks, 18 routes)

Closed the 3-PR agenda-http chain plus a follow-up PR 4 that closed the
2 CRITICAL findings from sdd-verify. Final state:
  - 56 commits on main (P1: 8 + P2: 7 + P3: 24 + post-merge patch: 2 + P4: 10)
  - 18 routes (16 public + 3 auth - 1 retired /api/me)
  - 118 passed + 3 skipped on SQLite / 121 passed on MariaDB
  - 49/49 tasks complete
  - sdd-verify verdict: pass-with-warnings (9 untested scenarios + 1 spec
    drift + 1 README drift documented as out-of-scope)

Synced 2 delta specs to canonical (8 reqs, 66 scenarios):
  - specs/agenda/api/spec.md          (7 reqs, 62 scenarios) -> openspec/specs/agenda/api/spec.md
  - specs/agenda/concurrency-http/spec.md (1 req, 4 scenarios) -> openspec/specs/agenda/concurrency-http/spec.md

See openspec/changes/archive/agenda-http/archive-report.md for the full
out-of-scope items list (forwarded to a future agenda-test-coverage change).
```

No Co-Authored-By trailer. No AI attribution. Conventional Commits style.

## skill_resolution

paths-injected (sdd-archive via orchestrator; `_shared` reference loaded for protocol context; engram-protocol from system prompt)

## Next recommended

The orchestrator should:

1. **`/sdd-new` to start the next change** — candidates ranked by impact:
   - **`agenda-test-coverage`** (~150 LOC) — closes the 9 untested spec scenarios + 1 new assertion. **Highest priority** because the regressions in the API surface are currently invisible. This is the change the agenda-http archive explicitly defers to.
   - **`agenda-readme-drift`** (~10 LOC) — closes the 2 README drift items. **Low-risk cleanup**, can ship in 1 PR.
   - **`agenda-resource-shape`** (~30 LOC) — resolves the `DoctorResource` spec drift. **Spec or impl decision required** before starting.
   - **`agenda-patient-web`** — the future Blade + Tailwind patient portal that consumes the API. This is the next big feature slice; benefits from the `SANCTUM_STATEFUL_DOMAINS` + CORS deferred items in `design.md` §10.
   - **`clinical-records` writes + `prescriptions` writes** — the deferred wire-up from `agenda-core` (see W1 from `agenda-core` archive). Not blocked by `agenda-http`; can start in parallel.
2. **Surface §A–§E to the user** as documented context — the user explicitly accepted these as out-of-scope in the agenda-http archive authorization, but a future `agenda-test-coverage` change is the natural follow-up.
3. **Capture the audit decisions in Engram** — the `Sanctum` + `TOKEN_EXPIRED` integration pattern (`PersonalAccessToken::findToken($request->bearerToken())` with `expires_at` check), the per-request timezone middleware shape (`ResolveTimezone` mounted BEFORE `auth:sanctum` so 401 responses are TZ-aware), and the `LengthAwarePaginator` envelope are reusable patterns for the next changes.
