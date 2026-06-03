<!-- Source: openspec/changes/archive/agenda-spec-drift/specs/agenda/api/spec.md -- synced 2026-06-02 (agenda-spec-drift archive) -->
# Agenda API

## Purpose
The agenda API exposes the existing agenda domain (booking, cancellation, state transitions, slot lookup, directory reads, audit reads) over a JSON HTTP transport. It does not introduce new domain logic; it wraps the action layer, services, and policies that agenda-core already supplies. Authentication is Laravel Sanctum bearer tokens; the same Gate policies used by Filament enforce role and ownership. Timezone resolution, the standard error envelope, and the pagination contract are fixed for v1.

## ADDED Requirements

### Requirement: REQ-API-1 Sanctum Bearer Authentication
The system MUST authenticate every `/api/*` request except `POST /api/auth/login` with a Sanctum bearer token. A request with a valid token resolves the corresponding `User` as the actor; a missing, malformed, or expired token MUST be rejected with `401` and the standard error envelope. Expired tokens MUST be reported with code `TOKEN_EXPIRED`; missing or malformed tokens MUST be reported with code `UNAUTHENTICATED`.

#### Scenario: Valid bearer token authenticates the request
- **Given** a user with a valid Sanctum token
- **When** the client sends any `/api/*` request with `Authorization: Bearer <token>`
- **Then** the request is authenticated and the corresponding `User` is the actor

#### Scenario: Missing token returns 401 UNAUTHENTICATED
- **Given** any client
- **When** the client sends a request to any `/api/*` endpoint with no `Authorization` header
- **Then** the response is `401` and the body is `{"error":{"code":"UNAUTHENTICATED","message":"..."}}`

#### Scenario: Expired token returns 401 TOKEN_EXPIRED
- **Given** a Sanctum token whose `expires_at` is in the past
- **When** the client uses the expired token in the `Authorization` header
- **Then** the response is `401` and the body is `{"error":{"code":"TOKEN_EXPIRED","message":"..."}}`

#### Scenario: Malformed token returns 401 UNAUTHENTICATED
- **Given** any client
- **When** the client sends `Authorization: Bearer not-a-real-token`
- **Then** the response is `401` and the error code is `UNAUTHENTICATED`

### Requirement: REQ-API-2 Role and Ownership Authorization
The system MUST enforce role and ownership using the same Gate policies that Filament uses (`AppointmentPolicy`, `PatientPolicy`, `UserPolicy`). A patient SHALL see only their own appointments and clinical data; a doctor SHALL see only their own appointments and their assigned patients; an admin SHALL see everything within the `isAdmin` gate. A request that fails the gate MUST return `403` with error code `FORBIDDEN`; a request that fails an action-level actor check MUST return `403` with error code `UNAUTHORIZED_ACTOR`.

#### Scenario: Patient lists only their own appointments
- **Given** patient `P` with two appointments and a different patient `Q` with one appointment
- **When** `P` calls `GET /api/appointments`
- **Then** the response is `200` and the `data` array contains exactly `P`'s two appointments and zero of `Q`'s

#### Scenario: Doctor cannot read another doctor's appointment
- **Given** doctor `D` assigned to appointment `A1` and doctor `D2` assigned to appointment `A2`
- **When** `D` calls `GET /api/appointments/{A2.id}`
- **Then** the response is `403` and the error code is `FORBIDDEN`

#### Scenario: Admin reads any appointment
- **Given** admin `A` and any appointment
- **When** `A` calls `GET /api/appointments/{id}`
- **Then** the response is `200` with the appointment JSON

#### Scenario: Doctor can read a patient with whom they share an appointment
- **Given** doctor `D` with at least one appointment with patient `P`
- **When** `D` calls `GET /api/patients/{P.id}`
- **Then** the response is `200` with the patient profile

#### Scenario: Doctor cannot read an unassigned patient
- **Given** doctor `D` with no appointment with patient `Q`
- **When** `D` calls `GET /api/patients/{Q.id}`
- **Then** the response is `403` and the error code is `FORBIDDEN`

#### Scenario: Patient cannot read another patient
- **Given** patient `P` and a different patient `Q`
- **When** `P` calls `GET /api/patients/{Q.id}`
- **Then** the response is `403` and the error code is `FORBIDDEN`

### Requirement: REQ-API-3 Standard Error Envelope
Every `4xx` and `5xx` response MUST use the envelope `{"error":{"code","message","details?"}}`. The `code` MUST be a stable uppercase snake_case string. The `message` MUST be a human-readable string safe for end-user display. The `details` field, when present, MUST carry structured context (e.g. validation errors keyed by field, conflicting appointment id, slot start time). Successful `2xx` responses that return a body MUST use `{"data":...}` to keep a single envelope grammar across the API.

#### Scenario: Error response uses the standard envelope
- **Given** any `/api/*` request that results in a `4xx` or `5xx` response
- **When** the client reads the body
- **Then** the body parses as `{"error":{"code":"<UPPER_SNAKE>","message":"<string>","details":<object|null>}}`

#### Scenario: 422 validation surfaces field-level errors under details
- **Given** a `POST /api/appointments` request missing `doctor_id`
- **When** the request is sent
- **Then** the response is `422`, the error code is `VALIDATION_ERROR`, and `error.details` contains the Laravel validation errors keyed by field name

#### Scenario: 204 No Content has an empty body
- **Given** a successful `DELETE /api/appointments/{id}`
- **When** the response is returned
- **Then** the response status is `204` and the body is empty

### Requirement: REQ-API-4 Domain Exception → HTTP Mapping
The system MUST map domain exceptions thrown by the action layer to the HTTP status and error code pairs in the table below. The mapping is fixed for v1; any unmapped exception MUST return `500` with error code `INTERNAL_ERROR`. The table is the canonical source of truth.

| Exception | HTTP | error.code |
|---|---|---|
| `SlotNotAvailableException` | 409 | `SLOT_NOT_AVAILABLE` |
| `AnticipationWindowViolationException` | 422 | `ANTICIPATION_WINDOW_VIOLATION` |
| `PatientOverlapException` | 422 | `PATIENT_OVERLAP` |
| `UnauthorizedActorException` | 403 | `UNAUTHORIZED_ACTOR` |
| `CancellationWindowViolationException` | 422 | `CANCELLATION_WINDOW_VIOLATION` |
| `InvalidStateTransitionException` | 422 | `INVALID_STATE_TRANSITION` |
| Laravel `AuthorizationException` | 403 | `FORBIDDEN` |
| Laravel `ModelNotFoundException` | 404 | `NOT_FOUND` |
| Laravel `ValidationException` | 422 | `VALIDATION_ERROR` |
| Laravel `NotFoundHttpException` | 404 | `ROUTE_NOT_FOUND` |

#### Scenario: SlotNotAvailableException surfaces as 409 SLOT_NOT_AVAILABLE
- **Given** a doctor with an existing non-cancelled appointment at the requested `start_time`
- **When** a client calls `POST /api/appointments` for that slot
- **Then** the response is `409` and the error code is `SLOT_NOT_AVAILABLE`

#### Scenario: AnticipationWindowViolationException surfaces as 422 ANTICIPATION_WINDOW_VIOLATION
- **Given** a published slot at `now + 30m`
- **When** a client calls `POST /api/appointments` for that slot
- **Then** the response is `422` and the error code is `ANTICIPATION_WINDOW_VIOLATION`

#### Scenario: PatientOverlapException surfaces as 422 PATIENT_OVERLAP
- **Given** patient `P` with an existing non-cancelled appointment at `now + 3h` with doctor `D1`
- **When** `P` calls `POST /api/appointments` for a published slot at `now + 3h` with doctor `D2`
- **Then** the response is `422` and the error code is `PATIENT_OVERLAP`

#### Scenario: UnauthorizedActorException surfaces as 403 UNAUTHORIZED_ACTOR
- **Given** a `pending` appointment assigned to doctor `D` and a different doctor `D2`
- **When** `D2` calls `POST /api/appointments/{id}/transitions/confirm`
- **Then** the response is `403` and the error code is `UNAUTHORIZED_ACTOR`

#### Scenario: CancellationWindowViolationException surfaces as 422 CANCELLATION_WINDOW_VIOLATION
- **Given** patient `P` with a `pending` appointment starting at `now + 12h`
- **When** `P` calls `DELETE /api/appointments/{id}`
- **Then** the response is `422` and the error code is `CANCELLATION_WINDOW_VIOLATION`

#### Scenario: InvalidStateTransitionException surfaces as 422 INVALID_STATE_TRANSITION
- **Given** a `cancelled` appointment
- **When** a client calls `POST /api/appointments/{id}/transitions/confirm`
- **Then** the response is `422` and the error code is `INVALID_STATE_TRANSITION`

#### Scenario: Laravel AuthorizationException surfaces as 403 FORBIDDEN
- **Given** patient `P` and an appointment not assigned to `P`
- **When** `P` calls `GET /api/appointments/{id}` (the AppointmentPolicy `view` gate fails)
- **Then** the response is `403` and the error code is `FORBIDDEN`

#### Scenario: ModelNotFoundException surfaces as 404 NOT_FOUND
- **Given** no appointment with id `99999`
- **When** a client calls `GET /api/appointments/99999`
- **Then** the response is `404` and the error code is `NOT_FOUND`

#### Scenario: NotFoundHttpException surfaces as 404 ROUTE_NOT_FOUND
- **Given** any client
- **When** the client calls `/api/this-route-does-not-exist`
- **Then** the response is `404` and the error code is `ROUTE_NOT_FOUND`

#### Scenario: Unmapped exception surfaces as 500 INTERNAL_ERROR
- **Given** a code path that throws an exception not in the mapping table
- **When** the response is returned
- **Then** the response is `500` and the error code is `INTERNAL_ERROR`

### Requirement: REQ-API-5 Timezone Resolution
The system MUST resolve a single timezone per request and expose it on `request->attributes['tz']`. Resolution order: (1) the `?tz=` query parameter, if present and valid; (2) the `clinic.timezone` config value; (3) the default `America/Argentina/Buenos_Aires`. API Resources MUST render every datetime field as ISO 8601 with offset in the resolved timezone. Write bodies that carry an explicit datetime (`POST /api/appointments`) MUST be interpreted in the resolved timezone and converted to UTC before persisting. An invalid `?tz=` value MUST be rejected with `422` and error code `INVALID_TIMEZONE`.

#### Scenario: No `?tz=` falls back to clinic.timezone
- **Given** a client with no `?tz=` query and `clinic.timezone = America/Argentina/Buenos_Aires`
- **When** the client calls `GET /api/doctors/{id}/slots?date=2026-06-15`
- **Then** the response datetimes are in `America/Argentina/Buenos_Aires` (offset `-03:00`)

#### Scenario: `?tz=` override is honored
- **Given** a client with `?tz=America/New_York`
- **When** the client calls any `/api/*` endpoint that returns datetimes
- **Then** the response datetimes are in `America/New_York` (offset `-04:00` or `-05:00` depending on DST)

#### Scenario: Write body is interpreted in the resolved timezone and stored as UTC
- **Given** a client with `?tz=America/New_York` posting `POST /api/appointments` with `start_time = "2026-06-15T09:00:00"`
- **When** the appointment is created
- **Then** the stored `start_time` in the database is the UTC equivalent of `09:00 -04:00`

#### Scenario: Invalid `?tz=` is rejected
- **Given** a client with `?tz=Not/A_Real_Zone`
- **When** the client calls any `/api/*` endpoint
- **Then** the response is `422` and the error code is `INVALID_TIMEZONE`

### Requirement: REQ-API-6 Pagination Contract
List endpoints (`GET /api/appointments`, `GET /api/doctors`, `GET /api/prescriptions`, `GET /api/audit-logs`) MUST use Laravel's `LengthAwarePaginator` (page-based, offset). The response MUST be `{"data":[...],"meta":{"current_page","per_page","total","last_page"},"links":{"first","last","prev","next"}}`. The `per_page` query parameter MUST default to `20` and accept values between `1` and `100`; a value outside the range MUST be rejected with `422` and error code `VALIDATION_ERROR`.

#### Scenario: Default pagination returns 20 items per page
- **Given** 25 appointments
- **When** a client calls `GET /api/appointments` (no `per_page`)
- **Then** the response is `200`, `data` has 20 items, and `meta.current_page = 1`

#### Scenario: Custom `per_page` within range is honored
- **Given** 50 appointments
- **When** a client calls `GET /api/appointments?per_page=5`
- **Then** the response is `200`, `data` has 5 items, and `meta.last_page = 10`

#### Scenario: `per_page` above the maximum is rejected
- **Given** any client
- **When** the client calls `GET /api/appointments?per_page=500`
- **Then** the response is `422` and the error code is `VALIDATION_ERROR`

#### Scenario: `per_page` below 1 is rejected
- **Given** any client
- **When** the client calls `GET /api/appointments?per_page=0`
- **Then** the response is `422` and the error code is `VALIDATION_ERROR`

### Requirement: REQ-API-7 Endpoint Contracts
The system MUST expose the 16 public endpoints + 3 auth endpoints listed in the subsections below. Every endpoint MUST be reachable only through the JSON transport under `/api/*`, MUST be covered by the auth + RBAC + error envelope + TZ + pagination contracts above, and MUST match the response shapes documented per endpoint. Successful read endpoints return `{"data":<resource>}`; successful list endpoints return `{"data":[...],"meta":...,"links":...}`; successful write endpoints return `2xx` with the appropriate `AppointmentResource` (or empty body for `204`).

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

#### Scenario: GET /api/auth/me returns 200 with the current user
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
- **When** `A` calls `GET /api/audit-logs` with optional `?actor_id= ?action= ?subject_type= ?from= ?to=`
- **Then** the response is `200` with the paginated envelope of audit log rows

#### Scenario: GET /api/audit-logs returns 403 for a non-admin
- **Given** a doctor or a patient
- **When** the client calls `GET /api/audit-logs`
- **Then** the response is `403` and the error code is `FORBIDDEN`

## Out of scope (for this delta)

- **CORS** for browser cross-origin clients — deferred to the patient-web change.
- **OpenAPI / Swagger generation** — not generated in v1.
- **Rate limiting beyond Sanctum defaults** — no custom throttle middleware.
- **API versioning under `/api/v1/...`** — v1 endpoints live at `/api/...`; a v2 prefix is a v2 concern.
- **Writes** for `medical_notes`, `medical_attachments`, and `prescriptions` — tables exist, GET-only is exposed; the wire-up for POST/PUT/DELETE is for a future change.
- **Cursor-based pagination** — `LengthAwarePaginator` only in v1.
- **`?include=` query param** for eager-loading relations — not in v1.
- **Webhooks** for appointment events — not in v1.
- **Slot write endpoints** — slots are computed on the fly; only reads are exposed.
- **HTTP-layer concurrency contract** — the concurrent-POST deterministic 201/409 contract lives in `agenda/concurrency-http` and is not duplicated here.
