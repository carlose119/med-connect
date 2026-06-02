# Proposal: agenda-test-coverage

**Change ID**: agenda-test-coverage
**Status**: proposed
**Date**: 2026-06-02
**Author**: orchestrator (SDD cycle, user-initiated)
**Reviewers**: user (approval gate)
**Depends on**: agenda-http (archived at `41d90a3`; canonical specs at `openspec/specs/agenda/{api,concurrency-http}/spec.md`)

## Why

The `sdd-verify` re-run on `agenda-http` (PASS WITH WARNINGS, archived at `openspec/changes/archive/agenda-http/verify-report.md`) flagged **11 untested spec scenarios** + **1 spec/implementation drift** as out-of-scope. The change is contractually complete, but the test coverage is 77% (50/65 scenarios). This change closes the gap, bringing the agenda-http surface to ~95% scenario coverage.

The 11 scenarios are real risks: any future change to the auth path, RBAC, error envelope, timezone resolution, pagination, or booking gate could regress them silently. Adding the tests locks the contract in code.

## What changes

Add 11 test scenarios (10 in `agenda/api` + 1 in `agenda/concurrency-http`) that exercise spec scenarios previously only documented, not tested. Resolve 1 spec/implementation drift on doctor-patient visibility.

### In scope (12 items)

**9 untested scenarios in `openspec/specs/agenda/api/spec.md`** (all WARNING from the verify re-run):

| # | Req | Scenario | Test file (target) |
|---|---|---|---|
| 1 | REQ-API-2 §2 | Doctor cannot read another doctor's appointment (403 FORBIDDEN on cross-coverage show) | `tests/Feature/Api/ShowAppointmentTest.php` (1 new scenario) |
| 2 | REQ-API-2 §3 | Admin reads any appointment (200 on show for admin role) | `tests/Feature/Api/ShowAppointmentTest.php` (1 new scenario) |
| 3 | REQ-API-2 §5 | **Doctor cannot read an unassigned patient (403) — also a spec/implementation drift** | `tests/Feature/Api/ShowPatientTest.php` (1 new scenario) + `app/Policies/PatientPolicy.php` (MOD) OR `app/Http/Controllers/Api/PatientController.php` (MOD) |
| 4 | REQ-API-4 §10 | `NotFoundHttpException` surfaces as `404 ROUTE_NOT_FOUND` (route 404, not resource 404) | `tests/Feature/Api/ExceptionMappingTest.php` (1 new scenario) |
| 5 | REQ-API-5 §1 | No `?tz=` falls back to `clinic.timezone` (default rendering test) | `tests/Feature/Api/TimezoneResolutionTest.php` (1 new scenario, NEW test file) OR extend an existing TZ test |
| 6 | REQ-API-5 §3 | Write body is interpreted in the resolved timezone and stored as UTC (assertion on DB column type/value) | `tests/Feature/Api/BookAppointmentTest.php` (1 new scenario) |
| 7 | REQ-API-6 §3 | `per_page` above the maximum is rejected (`422 VALIDATION_ERROR`) | `tests/Feature/Api/ListAppointmentsTest.php` (1 new scenario) — extend with `per_page=200` |
| 8 | REQ-API-6 §4 | `per_page` below 1 is rejected (`422 VALIDATION_ERROR`) | `tests/Feature/Api/ListAppointmentsTest.php` (1 new scenario) — extend with `per_page=0` |
| 9 | REQ-API-7 §5 | Non-patient actor returns 403 on `POST /api/appointments` (admin + doctor both 403) | `tests/Feature/Api/BookAppointmentTest.php` (1 new scenario) |
| 10 | REQ-API-7 §7 | `GET /api/appointments` with no auth returns 401 | `tests/Feature/Api/ListAppointmentsTest.php` (1 new scenario) |
| 11 | REQ-API-7 §13 | `GET /api/doctors/{id}` for a missing doctor returns `404 NOT_FOUND` (not `ROUTE_NOT_FOUND`) | `tests/Feature/Api/ShowDoctorTest.php` (1 new scenario) |

**1 untested scenario in `openspec/specs/agenda/concurrency-http/spec.md`**:

| # | Req | Scenario | Test file (target) |
|---|---|---|---|
| 12 | REQ-CONC-HTTP-1 §2 | The losing 409 response includes the `conflicting_appointment_id` under `error.details` | `tests/Feature/Api/ConcurrentDoubleBookHttpTest.php` (1 new scenario extending the existing race test) |

**1 spec/implementation drift** (item #3 above): `PatientPolicy@view` currently allows any doctor to view any patient. The spec says doctors can only view patients they share an appointment with. **Resolution**: modify `PatientPolicy@view` (or the controller's explicit check) to enforce "doctor must have at least one appointment with the patient". This is a security boundary tightening — no other code path is affected because the only entry point is `GET /api/patients/{id}`.

### Out of scope (deferred to later changes)

- **DoctorResource shape drift** (`data.user.name` vs top-level `data.name`) — separate change `agenda-resource-shape` (~30 LOC)
- **README `/api/me` references** (5 lines) + missing `/api/doctors/{id}` row — separate change `agenda-readme-drift` (~10 LOC)
- **Spec self-contradiction on DELETE 204 vs 200** — misread by the verify sub-agent; the spec is consistent (204 is for `POST /api/auth/logout`, 200 is for `DELETE /api/appointments/{id}` with a resource body). No spec change needed.
- **3 cosmetic SUGGESTIONs** (`DoctorController@slots` TZ fallback, `AuditLogResource` defensive parse, `MedicalHistoryController` inline role check) — informational, can wait
- **PR 3 LOC budget overrun** (informational only) — historical record, no action

## Approach (locked)

**Strict TDD per item**: every test scenario is a red test commit followed by a green commit. For items 1, 2, 4, 5, 6, 7, 8, 9, 10, 11, 12, the implementation already exists — the red test fails (because the test asserts behavior the spec demands but the test does not exist), the green test passes (because the implementation is already correct). For item 3, the red test fails (because `PatientPolicy@view` allows any doctor), the green commit modifies the policy to make the test pass.

**Test pattern**: use the existing `CreatesDoctors` + `CreatesPatients` traits. The new `TimezoneResolutionTest` (item 5) may need a `withAppTimezone('UTC')` helper or a `Config::set('clinic.timezone', 'UTC')` setup. Mirror the `BookAppointmentTest` style for write tests and the `ListAppointmentsTest` style for read tests.

**For the spec/implementation drift (item 3)**: modify `PatientPolicy@view` to check `$user->doctorProfile` has at least one appointment with the patient. The check is a single `exists()` query (no N+1). The pattern is the same as the existing `AppointmentPolicy@view` logic.

### Affected files

| File | Change type | Reason |
|---|---|---|
| `tests/Feature/Api/ShowAppointmentTest.php` | MOD (+2 scenarios) | Items 1, 2 |
| `tests/Feature/Api/ShowPatientTest.php` | MOD (+1 scenario) | Item 3 (test side) |
| `tests/Feature/Api/ExceptionMappingTest.php` | MOD (+1 scenario) | Item 4 |
| `tests/Feature/Api/TimezoneResolutionTest.php` | NEW (~25 LOC, 1 scenario) | Item 5 |
| `tests/Feature/Api/BookAppointmentTest.php` | MOD (+2 scenarios) | Items 6, 9 |
| `tests/Feature/Api/ListAppointmentsTest.php` | MOD (+3 scenarios) | Items 7, 8, 10 |
| `tests/Feature/Api/ShowDoctorTest.php` | MOD (+1 scenario) | Item 11 |
| `tests/Feature/Api/ConcurrentDoubleBookHttpTest.php` | MOD (+1 scenario) | Item 12 |
| `app/Policies/PatientPolicy.php` | MOD (~10 LOC: add `exists()` check) | Item 3 (impl side) |
| `README.md` | NOT MOD | Out of scope |
| `openspec/specs/agenda/api/spec.md` | NOT MOD | The 11 scenarios already exist; the change adds tests, not new spec text |
| `openspec/specs/agenda/concurrency-http/spec.md` | NOT MOD | The 1 scenario already exists; the change adds the test |

### Estimated LOC

| Layer | LOC |
|---|---|
| New tests (11 scenarios across 8 files) | ~200 |
| `PatientPolicy` change (item 3) | ~10 |
| `TimezoneResolutionTest` (new file) | ~25 |
| **Total** | **~235** |
| Within 400-line review budget | ✅ |

## PR split (locked): 1 PR, stacked-to-main

The change is small enough for a single PR. Strict TDD with red+green pairs per item. Estimated commit count: 12 RED + 12 GREEN (or 11 RED + 1 RED for the policy change + 12 GREEN = 24 commits total). Use 2-commit split per item (red + green) to maintain the auditability pattern from agenda-http.

**Commit pattern** (bottom-to-top):
```
<hash> chore(test): verify agenda-test-coverage test suite on both drivers (verify)
<hash> test(api): ShowDoctorTest 404 for missing doctor (red)
<hash> feat(policy): PatientPolicy@view enforces shared-appointment check (green)  [item 3]
<hash> test(api): ShowPatientTest 403 for unassigned doctor (red)  [item 3]
... (mirror for items 12, 11, 10, 9, 8, 7, 6, 5, 4, 2, 1)
<hash> test(api): ShowAppointmentTest admin reads any + doctor cross-coverage 403 (red)  [items 1+2]
```

**Chain strategy**: stacked-to-main (cut `feat/test-coverage` from main, FF-merge when green).

## Capabilities (delta specs to be created in sdd-spec phase)

The change adds TESTS, not new requirements. The existing spec scenarios are already documented in the canonical specs; the change locks them in code. **No new capability is introduced.**

For auditability, the sdd-spec phase will create a minimal delta spec at `openspec/changes/agenda-test-coverage/specs/agenda/test-coverage/spec.md` with one new requirement that DOCUMENTS the new test-to-scenario mapping:

> ### Requirement: REQ-TEST-COVERAGE-1 — Spec Scenarios Have Tests
> Every WHEN/THEN scenario in `openspec/specs/agenda/api/spec.md` (62 scenarios) and `openspec/specs/agenda/concurrency-http/spec.md` (4 scenarios) MUST be covered by at least one Pest test that exercises the described behavior. Test names MUST follow the pattern `it_<short_description>` and the test class MUST be at `tests/Feature/Api/<Endpoint>Test.php` or `tests/Unit/...` as appropriate.

The 11 new test scenarios will be documented as ADDED scenarios under this requirement, each citing the source spec scenario and the test file path. This makes the test-to-spec mapping grep-able in CI.

## Affected Areas (impact matrix)

| Area | Impact | Description |
|---|---|---|
| `app/Policies/PatientPolicy.php` | MOD | Add `exists()` check for doctor→patient appointment (item 3). Affects 1 endpoint (`GET /api/patients/{id}`). |
| `app/Http/Controllers/Api/PatientController.php` | UNCHANGED | The policy change is sufficient; no controller change needed. |
| `tests/Feature/Api/*Test.php` | MOD + NEW | 7 test files modified, 1 new test file (`TimezoneResolutionTest.php`). 11 new test scenarios. |
| `routes/api.php` | UNCHANGED | No route changes. |
| `bootstrap/app.php` | UNCHANGED | No middleware changes. |
| `app/Http/Responses/Api/ErrorResponse.php` | UNCHANGED | No new error codes. |
| Existing canonical specs | UNCHANGED | The 11 scenarios already exist; the change adds tests, not new spec text. |

## Test counts (expected after this change)

| Driver | Before | After | Delta |
|---|---|---|---|
| SQLite (in-memory) | 118 passed + 3 skipped | 129 passed + 3 skipped | +11 |
| MariaDB 10.11.9 | 121 passed | 132 passed | +11 |
| Coverage % of `agenda/api` spec (62 scenarios) | 50/62 (81%) | 61/62 (98%) | +17% |
| Coverage % of `agenda/concurrency-http` spec (4 scenarios) | 3/4 (75%) | 4/4 (100%) | +25% |
| **Total coverage %** | **53/66 (80%)** | **65/66 (98%)** | **+18%** |

(The remaining untested scenario is REQ-API-3 #2 "422 validation surfaces field-level errors under details" — already covered indirectly by `BookAppointmentTest` and `ListAppointmentsTest` validation paths; not a strict gap.)

## Open questions for sdd-apply

1. **Item 3 — `PatientPolicy@view` semantics**: confirm the check is "doctor must have at least one appointment with the patient" (not "doctor must have a future appointment" or some other temporal qualifier). The agenda-core spec for doctor-patient relationships is silent on the temporal aspect; I'll default to "any appointment, past or future" as the simplest correct interpretation.
2. **Item 5 — `TimezoneResolutionTest` location**: as a new test file at `tests/Feature/Api/TimezoneResolutionTest.php`, OR extend the existing `BookAppointmentTest` / `ListAppointmentsTest` with a `?tz=` assertion? The new-file approach gives a dedicated home for TZ-related tests but adds 1 more file to the suite. I'll default to the new-file approach in `sdd-tasks` unless the user prefers otherwise.
3. **Item 12 — `conflicting_appointment_id` field**: confirm the field name in the spec (`error.details.conflicting_appointment_id`) matches the implementation in `ErrorResponse::resolve()` for `PatientOverlapException`. If the impl uses a different field name, the test will fail and we'll need to align the impl + spec.

## What "done" looks like

- 11 new test scenarios pass on both SQLite and MariaDB
- 1 modified `PatientPolicy@view` makes the doctor-unassigned-patient test pass
- Full suite: 129 passed + 3 skipped (SQLite) / 132 passed (MariaDB)
- `php artisan route:list --path=api` still shows 18 routes (no new routes)
- `sdd-verify` re-run returns `pass` (no warnings) or `pass-with-warnings` (only the 3 cosmetic SUGGESTIONs remain)
- Change archived: delta spec for `agenda/test-coverage` promoted to canonical, change folder moved to `archive/`
