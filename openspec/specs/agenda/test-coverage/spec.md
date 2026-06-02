<!-- Source: openspec/changes/archive/agenda-test-coverage/specs/agenda/test-coverage/spec.md -- synced 2026-06-02 (agenda-test-coverage archive) -->
# Test Coverage

## Purpose
Documents which canonical spec scenarios are covered by which Pest tests in the med-connect codebase. The change adds 12 new test scenarios (11 in `agenda/api` + 1 in `agenda/concurrency-http`) that close the gaps flagged by the `sdd-verify` re-run on `agenda-http` (PASS WITH WARNINGS, 2026-06-02, see `openspec/changes/archive/agenda-http/verify-report.md` WARNING #1). The 11 untested scenarios in `agenda/api` are the WARNING #1 list verbatim; the 1 scenario in `agenda/concurrency-http` is WARNING #1 item #11. One of the 12 scenarios (REQ-API-2 §5, "Doctor cannot read an unassigned patient") is ALSO a spec/implementation drift — the canonical spec demands a stricter policy than the current `PatientPolicy@view` provides — and the change tightens the policy to close the gap. No canonical spec text is modified; this delta only documents the test-to-scenario mapping and the policy enforcement for grep-ability in CI.

## ADDED Requirements

### Requirement: REQ-TEST-COVERAGE-1 — Spec Scenarios Have Tests
Every WHEN/THEN scenario in the canonical `openspec/specs/agenda/api/spec.md` and `openspec/specs/agenda/concurrency-http/spec.md` MUST be covered by at least one Pest test that exercises the described behavior. Test methods MUST follow the pattern `it_<short_description>` and the test class MUST live at `tests/Feature/Api/<Endpoint>Test.php` (or `tests/Unit/...` for non-feature tests). This requirement makes the test-to-spec mapping an explicit, grep-able contract: a CI grep for `REQ-API-X §N` in the `tests/Feature/Api/` tree MUST surface a test method that asserts the scenario.

#### Scenario: Doctor cannot read another doctor's appointment (REQ-API-2 §2)
- **Source spec**: `openspec/specs/agenda/api/spec.md` REQ-API-2 §2 ("Doctor cannot read another doctor's appointment")
- **Given** doctor `D` assigned to appointment `A1` and a different doctor `D2` assigned to appointment `A2`
- **When** `D` calls `GET /api/appointments/{A2.id}`
- **Then** the response is `403` and `error.code = 'FORBIDDEN'`
- **Test**: `tests/Feature/Api/ShowAppointmentTest.php` → `it('returns 403 FORBIDDEN for a doctor from a different coverage')` (NEW scenario)

#### Scenario: Admin reads any appointment (REQ-API-2 §3)
- **Source spec**: `openspec/specs/agenda/api/spec.md` REQ-API-2 §3 ("Admin reads any appointment")
- **Given** admin `A` and any appointment `A.id`
- **When** `A` calls `GET /api/appointments/{A.id}`
- **Then** the response is `200` and the body is `{"data":<AppointmentResource>}`
- **Test**: `tests/Feature/Api/ShowAppointmentTest.php` → `it('returns 200 for an admin reading any appointment')` (NEW scenario)

#### Scenario: Doctor cannot read an unassigned patient (REQ-API-2 §5)
- **Source spec**: `openspec/specs/agenda/api/spec.md` REQ-API-2 §5 ("Doctor cannot read an unassigned patient")
- **Given** doctor `D` with no appointment with patient `Q`
- **When** `D` calls `GET /api/patients/{Q.id}`
- **Then** the response is `403` and `error.code = 'FORBIDDEN'`
- **Test**: `tests/Feature/Api/ShowPatientTest.php` → `it('returns 403 FORBIDDEN for a doctor with no shared appointments')` (NEW scenario)
- **Spec clarification**: the canonical spec does not define "unassigned" precisely. This change ENFORCES "at least one appointment (any state, past or future) between the doctor and the patient" via `PatientPolicy@view`. This is the simplest correct interpretation; if a temporal qualifier is needed (e.g. "doctor must have a non-cancelled appointment"), the canonical spec MUST be amended in a follow-up change and the policy tightened accordingly.
- **Impl change**: `app/Policies/PatientPolicy.php` MOD — add an `Appointment::where(doctor_id, patient_id)->exists()` check on the `view` gate. The check is a single `exists()` query (no N+1) and mirrors the existing `AppointmentPolicy@view` logic.

#### Scenario: NotFoundHttpException surfaces as 404 ROUTE_NOT_FOUND (REQ-API-4 §10)
- **Source spec**: `openspec/specs/agenda/api/spec.md` REQ-API-4 §10 ("NotFoundHttpException surfaces as 404 ROUTE_NOT_FOUND")
- **Given** any client with a valid bearer token
- **When** the client calls `GET /api/this-route-does-not-exist`
- **Then** the response is `404` and `error.code = 'ROUTE_NOT_FOUND'` (distinct from `NOT_FOUND`, which is reserved for missing resources)
- **Test**: `tests/Feature/Api/ExceptionMappingTest.php` → `it('returns 404 ROUTE_NOT_FOUND for an unknown route')` (NEW scenario)

#### Scenario: No `?tz=` falls back to clinic.timezone (REQ-API-5 §1)
- **Source spec**: `openspec/specs/agenda/api/spec.md` REQ-API-5 §1 ("No `?tz=` falls back to clinic.timezone")
- **Given** a client with no `?tz=` query and `clinic.timezone = America/Argentina/Buenos_Aires`
- **When** the client calls `GET /api/doctors/{id}/slots?date=2026-06-15` (or any `/api/*` endpoint that returns datetimes)
- **Then** the response datetimes carry the offset `-03:00` (the resolved TZ is `America/Argentina/Buenos_Aires`)
- **Test**: `tests/Feature/Api/TimezoneResolutionTest.php` → `it('defaults to clinic.timezone when ?tz= is omitted')` (NEW scenario, NEW test file)

#### Scenario: Write body is interpreted in the resolved timezone and stored as UTC (REQ-API-5 §3)
- **Source spec**: `openspec/specs/agenda/api/spec.md` REQ-API-5 §3 ("Write body is interpreted in the resolved timezone and stored as UTC")
- **Given** patient `P` posting `POST /api/appointments` with `start_time = "2026-06-15T10:00:00-03:00"` (resolved TZ is `America/Argentina/Buenos_Aires`)
- **When** the appointment is created
- **Then** the stored `appointments.start_time` value is `'2026-06-15 13:00:00'` UTC (asserted via a Carbon-cast on the model or a raw `DB::table` read)
- **Test**: `tests/Feature/Api/BookAppointmentTest.php` → `it('interprets the write body in the resolved TZ and stores UTC')` (NEW scenario, with a DB-column assertion)

#### Scenario: `per_page` above the maximum is rejected (REQ-API-6 §3)
- **Source spec**: `openspec/specs/agenda/api/spec.md` REQ-API-6 §3 ("`per_page` above the maximum is rejected")
- **Given** any client
- **When** the client calls `GET /api/appointments?per_page=200` (maximum is 100)
- **Then** the response is `422` and `error.code = 'VALIDATION_ERROR'` with `error.details.per_page` carrying the field-level validation error
- **Test**: `tests/Feature/Api/ListAppointmentsTest.php` → `it('rejects per_page above the maximum')` (NEW scenario)

#### Scenario: `per_page` below 1 is rejected (REQ-API-6 §4)
- **Source spec**: `openspec/specs/agenda/api/spec.md` REQ-API-6 §4 ("`per_page` below 1 is rejected")
- **Given** any client
- **When** the client calls `GET /api/appointments?per_page=0`
- **Then** the response is `422` and `error.code = 'VALIDATION_ERROR'` with `error.details.per_page` carrying the field-level validation error
- **Test**: `tests/Feature/Api/ListAppointmentsTest.php` → `it('rejects per_page below 1')` (NEW scenario)

#### Scenario: Non-patient actor returns 403 on POST /api/appointments (REQ-API-7 §5)
- **Source spec**: `openspec/specs/agenda/api/spec.md` REQ-API-7 §5 ("POST /api/appointments with a non-patient actor returns 403 FORBIDDEN")
- **Given** an admin or a doctor (parameterised; both MUST be tested)
- **When** the client calls `POST /api/appointments` with a valid body
- **Then** the response is `403` and `error.code = 'FORBIDDEN'` (only patients book)
- **Test**: `tests/Feature/Api/BookAppointmentTest.php` → `it('returns 403 FORBIDDEN for a non-patient actor')` (NEW scenario, parameterised over admin + doctor)

#### Scenario: GET /api/appointments with no auth returns 401 (REQ-API-7 §7)
- **Source spec**: `openspec/specs/agenda/api/spec.md` REQ-API-7 §7 ("GET /api/appointments with no auth returns 401")
- **Given** a client with no `Authorization` header
- **When** the client calls `GET /api/appointments`
- **Then** the response is `401` and `error.code = 'UNAUTHENTICATED'`
- **Test**: `tests/Feature/Api/ListAppointmentsTest.php` → `it('returns 401 UNAUTHENTICATED for an unauthenticated request')` (NEW scenario)

#### Scenario: GET /api/doctors/{id} for a missing doctor returns 404 (REQ-API-7 §13)
- **Source spec**: `openspec/specs/agenda/api/spec.md` REQ-API-7 §13 ("GET /api/doctors/{id} for a missing doctor returns 404")
- **Given** no doctor with id `99999` and a client with a valid bearer token
- **When** the client calls `GET /api/doctors/99999`
- **Then** the response is `404` and `error.code = 'NOT_FOUND'` (NOT `ROUTE_NOT_FOUND` — the route exists, only the resource is missing)
- **Test**: `tests/Feature/Api/ShowDoctorTest.php` → `it('returns 404 NOT_FOUND for a missing doctor')` (NEW scenario)

#### Scenario: The losing 409 response includes the conflicting_appointment_id (REQ-CONC-HTTP-1 §2)
- **Source spec**: `openspec/specs/agenda/concurrency-http/spec.md` REQ-CONC-HTTP-1 §2 ("The losing response includes the conflicting appointment id")
- **Given** two concurrent `POST /api/appointments` requests for the same `(doctor_id, start_time)` (the existing `ConcurrentDoubleBookHttpTest` sets this up on MariaDB)
- **When** the losing request's 409 response is read
- **Then** `error.code = 'SLOT_NOT_AVAILABLE'` and `error.details.conflicting_appointment_id` equals the id of the winning appointment
- **Test**: `tests/Feature/Api/ConcurrentDoubleBookHttpTest.php` → `it('includes conflicting_appointment_id in the 409 details')` (NEW scenario, extends the existing race test with one more assertion; MariaDB-only, `->skipOnSqlite()` preserved)
- **Caveat**: the field name in the spec (`error.details.conflicting_appointment_id`) MUST match the implementation in `ErrorResponse::resolve()` for `SlotNotAvailableException` (the 409, not 422). If the impl uses a different field name (e.g. `existing_appointment_id`), the test will fail and the impl + spec MUST be aligned before archive. This is item #3 in the proposal's "Open questions for sdd-apply" and MUST be resolved during the `sdd-apply` phase.

## MODIFIED Scenarios

No canonical spec text is modified. The 12 scenarios already exist in the canonical `agenda/api` and `agenda/concurrency-http` specs; the change adds tests, not new spec text. The only behavioural change is the `PatientPolicy@view` enforcement (REQ-API-2 §5, scenario #3 above), which is a CLARIFICATION of the existing "doctor cannot read an unassigned patient" scenario — no spec text changes, but the impl now enforces it. The "Spec clarification" callout under scenario #3 SHOULD be reviewed by the user during `sdd-apply`; if a stricter or temporal qualifier is needed, the canonical spec MUST be amended in a follow-up change.

## REMOVED Scenarios

None.

## Out of scope (for this delta)

- **`DoctorResource` shape drift** (`data.user.name` vs top-level `data.name`) — separate change `agenda-resource-shape` (~30 LOC).
- **README `/api/me` references** (5 lines) + missing `/api/doctors/{id}` row + `MeTest` filter — separate change `agenda-readme-drift` (~10 LOC).
- **Spec self-contradiction on DELETE 204 vs 200** — the verify sub-agent misread the spec; the spec is consistent (204 is for `POST /api/auth/logout`, 200 is for `DELETE /api/appointments/{id}` with a resource body). No spec change needed.
- **3 cosmetic SUGGESTIONs** (`DoctorController@slots` TZ fallback, `AuditLogResource` defensive parse, `MedicalHistoryController` inline role check) — informational, can wait.
- **PR 3 LOC budget overrun** (informational only) — historical record, no action.

## Coverage delta (after this change)

| Spec | Scenarios | Before | After | Delta |
|---|---|---|---|---|
| `agenda/api` | 62 | 50 covered (81%) | 61 covered (98%) | +11 |
| `agenda/concurrency-http` | 4 | 3 covered (75%) | 4 covered (100%) | +1 |
| **Total** | **66** | **53 (80%)** | **65 (98%)** | **+12 (+18%)** |

The 1 remaining untested scenario after this change is REQ-API-3 §2 ("422 validation surfaces field-level errors under `details`") — already covered INDIRECTLY by `BookAppointmentTest` and `ListAppointmentsTest` validation paths (the `ExceptionMappingTest` also asserts the `details` shape on `ValidationException`). The test is a real behaviour assertion of the spec scenario; the spec is not modified, the existing tests just happen to cover it. A dedicated scenario in `ExceptionMappingTest` for `error.details` field-level keys on a `POST /api/appointments` body would close the last 1% but is non-blocking.

## Test counts (expected after this change)

| Driver | Before | After | Delta |
|---|---|---|---|
| SQLite (in-memory) | 118 passed + 3 skipped | 130 passed + 3 skipped | +12 |
| MariaDB 10.11.9 | 121 passed | 133 passed | +12 |

## Open questions to surface to `sdd-apply`

1. **PatientPolicy@view semantics** (scenario #3): confirm the check is "at least one appointment (any state, past or future) between the doctor and the patient". If a temporal qualifier is needed (e.g. "non-cancelled", "future", "within the last 12 months"), the canonical spec MUST be amended and the policy tightened accordingly. Default: "any appointment, past or future" (simplest correct interpretation).
2. **TimezoneResolutionTest location** (scenario #5): new file at `tests/Feature/Api/TimezoneResolutionTest.php`, or extend the existing `BookAppointmentTest` / `ListAppointmentsTest` with `?tz=` assertions? Default: new file (dedicated home for TZ tests).
3. **`conflicting_appointment_id` field name** (scenario #12): confirm the field name in the spec (`error.details.conflicting_appointment_id`) matches the implementation in `ErrorResponse::resolve()` for `SlotNotAvailableException`. If the impl uses a different field name, the test will fail and the impl + spec MUST be aligned before archive.
