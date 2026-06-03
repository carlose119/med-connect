<!-- Source: openspec/changes/archive/agenda-spec-drift/specs/agenda/api/spec.md -- synced 2026-06-02 (agenda-spec-drift archive) -->
# Delta for agenda/api (agenda-spec-drift)

## Purpose

This delta spec captures a MODIFIED scenario inside REQ-API-7 of the canonical
`agenda/api` sub-capability. The scenario at lines 313-316 of
`openspec/specs/agenda/api/spec.md` references the retired placeholder route
`/api/me` in both its heading (line 313) and its When clause (line 315). The
same requirement already documents the canonical `GET /api/auth/me` route
earlier in the file (line 213, introduced in `agenda-http` PR 4), so the
stale duplicate scenario leaks a wrong route into any agentic reader that
scans the spec.

This change updates the 2 drifted lines so the scenario names and exercises
the canonical route that the codebase, the test suite, and the `README.md`
already follow. The requirement prose and the other 29 scenarios in
REQ-API-7 stay untouched. The 3 legitimate `/api/medical-histories/{id}`
substrings at lines 323, 328, 333 of the canonical spec are not drift and
are out of scope.

This change is the sibling of `agenda-readme-drift` (archived at
`d3b4ef9`), which fixed the same drift family in the `README.md`. The
`agenda-readme-drift` proposal explicitly deferred the spec drift to this
change (obs #55).

## MODIFIED Requirements

### Requirement: REQ-API-7 Endpoint Contracts
The system MUST expose the 16 public endpoints + 3 auth endpoints listed in the subsections below. Every endpoint MUST be reachable only through the JSON transport under `/api/*`, MUST be covered by the auth + RBAC + error envelope + TZ + pagination contracts above, and MUST match the response shapes documented per endpoint. Successful read endpoints return `{"data":<resource>}`; successful list endpoints return `{"data":[...],"meta":...,"links":...}`; successful write endpoints return `2xx` with the appropriate `AppointmentResource` (or empty body for `204`).
(Previously: scenario "GET /api/me returns 200 with the current user" used the retired placeholder route; the scenario now targets the canonical `GET /api/auth/me`.)

#### Scenario: POST /api/auth/login returns 200 with user and token
- **Given** a valid user with `email` and `password`
- **When** the client calls `POST /api/auth/login` with `{email, password, device_name?}`
- **Then** the response is `200` and the body is `{"data":{"user":{"id","name","email","role"},"token":"<plaintext>"}}`

#### Scenario: POST /api/auth/login with bad credentials returns 401 UNAUTHENTICATED
- **Given** a valid user
- **When** the client calls `POST /api/auth/login` with the wrong password
- **Then** the response is `401` and the error code is `UNAUTHENTICATED`

#### Scenario: POST /api/auth/logout returns 204 and revokes the token
- **Given** an authenticated user with a valid Sanctum token
- **When** the client calls `POST /api/auth/logout` with the bearer token
- **Then** the response is `204` and the token is deleted from `personal_access_tokens`

#### Scenario: GET /api/auth/me returns 200 with the current user
- **Given** an authenticated user
- **When** the client calls `GET /api/auth/me` with the bearer token
- **Then** the response is `200` and the body is `{"data":{"id","name","email","role"}}`

#### Scenario: POST /api/appointments (book) returns 201 with the appointment JSON
- **Given** patient `P` and a published slot at `now + 3h` for doctor `D`
- **When** `P` calls `POST /api/appointments` with `{doctor_id: D.id, start_time: <ISO8601>, notes?}`
- **Then** the response is `201` and the body is `{"data":<AppointmentResource>}` with `state = "pending"`

#### Scenario: POST /api/appointments with a non-patient actor returns 403 FORBIDDEN
- **Given** an admin or a doctor
- **When** the client calls `POST /api/appointments`
- **Then** the response is `403` and the error code is `FORBIDDEN` (only patients book)

#### Scenario: DELETE /api/appointments/{id} (cancel) returns 200 with state=cancelled
- **Given** patient `P` with a `pending` appointment starting at `now + 48h`
- **When** `P` calls `DELETE /api/appointments/{id}`
- **Then** the response is `200` and the body is `{"data":<AppointmentResource>}` with `state = "cancelled"`

#### Scenario: DELETE /api/appointments/{id} outside the 24h window returns 422 CANCELLATION_WINDOW_VIOLATION
- **Given** patient `P` with a `pending` appointment starting at `now + 12h`
- **When** `P` calls `DELETE /api/appointments/{id}`
- **Then** the response is `422` and the error code is `CANCELLATION_WINDOW_VIOLATION`

#### Scenario: GET /api/appointments returns 200 with paginated list scoped by role
- **Given** any authenticated user
- **When** the client calls `GET /api/appointments` with optional `?from= ?to= ?doctor_id= ?patient_id= ?state= ?mine=true`
- **Then** the response is `200` with the paginated envelope; patients see only their own appointments; doctors see only their own; admins see all

#### Scenario: GET /api/appointments with no auth returns 401
- **Given** a client with no bearer token
- **When** the client calls `GET /api/appointments`
- **Then** the response is `401` and the error code is `UNAUTHENTICATED`

#### Scenario: GET /api/appointments/{id} returns 200 with detail
- **Given** an authenticated actor with view rights on the appointment
- **When** the client calls `GET /api/appointments/{id}`
- **Then** the response is `200` and the body is `{"data":<AppointmentResource>}`

#### Scenario: GET /api/appointments/{id} for a forbidden appointment returns 403
- **Given** patient `P` and an appointment not assigned to `P`
- **When** `P` calls `GET /api/appointments/{id}`
- **Then** the response is `403` and the error code is `FORBIDDEN`

#### Scenario: POST /api/appointments/{id}/transitions/confirm returns 200 with state=confirmed
- **Given** the assigned doctor and a `pending` appointment
- **When** the doctor calls `POST /api/appointments/{id}/transitions/confirm`
- **Then** the response is `200` and the body is `{"data":<AppointmentResource>}` with `state = "confirmed"`

#### Scenario: POST /api/appointments/{id}/transitions/confirm by a non-assigned doctor returns 403 UNAUTHORIZED_ACTOR
- **Given** a doctor `D2` not assigned to the appointment
- **When** `D2` calls `POST /api/appointments/{id}/transitions/confirm`
- **Then** the response is `403` and the error code is `UNAUTHORIZED_ACTOR`

#### Scenario: POST /api/appointments/{id}/transitions/complete returns 200 with state=completed
- **Given** the assigned doctor and a `confirmed` appointment
- **When** the doctor calls `POST /api/appointments/{id}/transitions/complete` with optional `{notes}`
- **Then** the response is `200` and the body is `{"data":<AppointmentResource>}` with `state = "completed"`

#### Scenario: POST /api/appointments/{id}/transitions/no-show returns 200 with state=no_show
- **Given** the assigned doctor (or an admin) and a `confirmed` appointment
- **When** the doctor calls `POST /api/appointments/{id}/transitions/no-show`
- **Then** the response is `200` and the body is `{"data":<AppointmentResource>}` with `state = "no_show"`

#### Scenario: GET /api/doctors returns 200 with paginated doctor list
- **Given** an authenticated user
- **When** the client calls `GET /api/doctors` with optional `?specialty_id= ?q=`
- **Then** the response is `200` with the paginated envelope; each row has `id`, `name`, `specialty{name,slug}`, `license_number`

#### Scenario: GET /api/doctors/{id} returns 200 with doctor detail
- **Given** an authenticated user and an existing doctor
- **When** the client calls `GET /api/doctors/{id}`
- **Then** the response is `200` and the body is `{"data":<DoctorResource>}` with `id`, `name`, `specialty`, `bio`, `license_number`

#### Scenario: GET /api/doctors/{id} for a missing doctor returns 404
- **Given** no doctor with id `99999`
- **When** a client calls `GET /api/doctors/99999`
- **Then** the response is `404` and the error code is `NOT_FOUND`

#### Scenario: GET /api/doctors/{id}/slots returns 200 with the available slots
- **Given** doctor `D` with a published schedule and an authenticated user
- **When** the client calls `GET /api/doctors/{id}/slots?date=YYYY-MM-DD` with optional `?from= ?to= ?duration= ?tz=`
- **Then** the response is `200` and the body is `{"data":[{"start":"<ISO8601>","end":"<ISO8601>"},...]}` in the resolved timezone

#### Scenario: GET /api/doctors/{id}/slots with bad date returns 422
- **Given** any client
- **When** the client calls `GET /api/doctors/{id}/slots?date=not-a-date`
- **Then** the response is `422` and the error code is `VALIDATION_ERROR`

#### Scenario: GET /api/patients/{id} returns 200 for the patient themselves
- **Given** patient `P`
- **When** `P` calls `GET /api/patients/{P.id}`
- **Then** the response is `200` and the body is `{"data":<PatientResource>}`

#### Scenario: GET /api/patients/{id} returns 200 for an admin
- **Given** admin `A` and any patient
- **When** `A` calls `GET /api/patients/{id}`
- **Then** the response is `200` and the body is `{"data":<PatientResource>}`

#### Scenario: GET /api/auth/me returns 200 with the current user (MODIFIED)
- **Given** any authenticated user
- **When** the client calls `GET /api/auth/me`
- **Then** the response is `200` and the body is `{"data":{"id","name","email","role"}}`

#### Scenario: GET /api/specialties returns 200 with the active specialty list
- **Given** an authenticated user
- **When** the client calls `GET /api/specialties?active=true`
- **Then** the response is `200` and the body is `{"data":[{"id","name","slug"},...]}` for specialties with `is_active = true`

#### Scenario: GET /api/medical-histories/{id} returns 200 for the owning patient
- **Given** patient `P` with a `medical_history` id `H`
- **When** `P` calls `GET /api/medical-histories/{H.id}`
- **Then** the response is `200` and the body is `{"data":<MedicalHistoryResource>}`

#### Scenario: GET /api/medical-histories/{id} returns 200 for an assigned doctor
- **Given** doctor `D` with at least one appointment with patient `P`, and patient `P`'s `medical_history` id `H`
- **When** `D` calls `GET /api/medical-histories/{H.id}`
- **Then** the response is `200`

#### Scenario: GET /api/medical-histories/{id} for a forbidden patient returns 403
- **Given** doctor `D` with no appointment with patient `Q`, and `Q`'s `medical_history` id `H`
- **When** `D` calls `GET /api/medical-histories/{H.id}`
- **Then** the response is `403` and the error code is `FORBIDDEN`

#### Scenario: GET /api/prescriptions returns 200 paginated and scoped by role
- **Given** an authenticated user
- **When** the client calls `GET /api/prescriptions` with optional `?patient_id= ?from= ?to=`
- **Then** the response is `200` with the paginated envelope; patients see only their own prescriptions; doctors see only prescriptions from their appointments; admins see all

#### Scenario: GET /api/audit-logs returns 200 for an admin
- **Given** admin `A`
- **When** the client calls `GET /api/audit-logs` with optional `?actor_id= ?action= ?subject_type= ?from= ?to=`
- **Then** the response is `200` with the paginated envelope of audit log rows

#### Scenario: GET /api/audit-logs returns 403 for a non-admin
- **Given** a doctor or a patient
- **When** the client calls `GET /api/audit-logs`
- **Then** the response is `403` and the error code is `FORBIDDEN`

## ADDED Requirements

None.

## REMOVED Requirements

None.

## Out of scope (for this delta)

- **The 3 legitimate `/api/medical-histories/{id}` substrings at lines 323, 328, 333 of the canonical spec** — not drift; they name a different endpoint family.
- **Other spec/implementation drift** (routes outside REQ-API-7, error codes, status mappings, RBAC tables) — out of scope by design; the only drift closed here is the 1 stale `/api/me` scenario.
- **De-duplicating the now-redundant `GET /api/auth/me` scenario** — after the archive, REQ-API-7 will contain two scenarios with the same heading (the canonical one at the original line 213 and the updated duplicate at the original line 313). They have slightly different Given/When text. The follow-up audit is tracked in obs #55; this change only updates the path references, not the scenario structure.
- **Renaming the `MeTest` Pest class** — the class already targets `/api/auth/me` correctly; only the spec prose was wrong.
- **Code, routes, controllers** — the codebase already follows canonical routes; the spec just needs to match reality.
