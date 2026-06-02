# Agenda HTTP Concurrency

## Purpose
The HTTP layer is the second concurrency surface for appointment booking (after the action-level test in `tests/Feature/Agenda/ConcurrentDoubleBookTest.php`). Two clients may submit `POST /api/appointments` for the same slot in parallel — through the future mobile app, the patient web portal, or browser multi-tabs. The system MUST surface the action-level persistence safety net as a deterministic `201` / `409` pair at the HTTP layer. The unique partial index `uniq_doctor_start_not_cancelled` is the source of truth; the HTTP layer just exposes the failure as the standard error envelope.

## ADDED Requirements

### Requirement: REQ-CONC-1 HTTP-Layer Concurrency Contract
The system MUST guarantee that two concurrent `POST /api/appointments` requests for the same `(doctor_id, start_time)` return exactly one `201 Created` (with the new appointment JSON) and exactly one `409 Conflict` (with error code `SLOT_NOT_AVAILABLE`). The losing request's `error.details` MUST include the conflicting appointment id so the client can refresh and display the correct state. The contract is testable on MariaDB (the unique index is enforced at the DB level); on SQLite the test is marked `skipped` because SQLite's transaction model does not surface the race.

#### Scenario: Two concurrent POSTs return 201 + 409 for the same slot
- **Given** doctor `D` and a published slot at `T`
- **When** two clients send `POST /api/appointments` for `(D.id, T)` at the same instant from different sessions
- **Then** exactly one response is `201` with the new appointment and exactly one is `409` with error code `SLOT_NOT_AVAILABLE`

#### Scenario: The losing response includes the conflicting appointment id
- **Given** the losing request from the previous scenario
- **When** the client reads the response body
- **Then** `error.details.conflicting_appointment_id` is the id of the appointment the winning request created

#### Scenario: A 409 from the HTTP layer matches the action-level outcome
- **Given** a patient calling `POST /api/appointments` after another patient has already booked the same slot via the action layer
- **When** the HTTP request is processed
- **Then** the response is `409` and the error code is `SLOT_NOT_AVAILABLE`, identical to the action-level test's outcome

#### Scenario: No double-write occurs under HTTP concurrency
- **Given** two concurrent `POST /api/appointments` for the same slot
- **When** both requests complete
- **Then** the `appointments` table contains exactly one non-cancelled row for that `(doctor_id, start_time)`

## Out of scope (for this delta)

- **Distributed locking beyond the existing `uniq_doctor_start_not_cancelled` partial unique index** — the DB unique constraint is the source of truth; the HTTP layer just surfaces the failure.
- **Idempotency keys** — clients retrying on network failure may produce a second `409`; client-side de-duplication via `Idempotency-Key` is deferred to v2.
- **Advisory locks at the application layer** — `lockForUpdate` inside `BookAppointmentAction` is sufficient; no new locking middleware.
- **Cross-process queue serialization** — out of scope; the system relies on the DB unique index.
- **The `GET /api/doctors/{id}/slots` race** — slot reads are advisory; the booking path is the only one with a write-side race and is the only one in scope here.
