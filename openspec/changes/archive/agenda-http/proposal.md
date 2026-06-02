# Proposal: agenda-http

**Change ID**: agenda-http
**Status**: proposed
**Date**: 2026-06-02
**Author**: orchestrator (via sdd-propose sub-agent)
**Reviewers**: user (approval gate)
**Depends on**: agenda-core (archived at `6ba4d6a`; canonical specs in `openspec/specs/`)

## Why

The Filament admin/doctor UI works for the staff, but patients and doctors on the go need a REST API to integrate with a future mobile app and the patient web portal. The current `BookAppointmentAction`, `CancelAppointmentAction`, the four `*AppointmentTransition` classes, and the `DoctorAvailabilityService` are all HTTP-unreachable — the domain is ready, only the transport is missing. This change exposes the existing agenda domain over a JSON HTTP API.

## What changes

Expose the existing agenda domain over a JSON HTTP API. Auth via Sanctum bearer tokens. RBAC predicates from PR 3 (`isAdmin`/`isDoctor`/`isPatient`). Domain exception → HTTP status code mapping in `bootstrap/app.php`. No new domain logic — the actions and services are already in place. 14 endpoints across 3 PRs.

### In scope

- `POST /api/appointments` (book) — wraps `BookAppointmentAction`
- `DELETE /api/appointments/{id}` (cancel) — wraps `CancelAppointmentAction`
- `POST /api/appointments/{id}/transitions/confirm` — `ConfirmAppointmentTransition`
- `POST /api/appointments/{id}/transitions/complete` — `CompleteAppointmentTransition`
- `POST /api/appointments/{id}/transitions/no-show` — `MarkNoShowAppointmentTransition`
- `GET /api/appointments` (list, paginated; `?from= ?to= ?doctor_id= ?patient_id= ?state= ?mine=`)
- `GET /api/appointments/{id}` (detail)
- `GET /api/doctors` (list; `?specialty_id= ?q=`)
- `GET /api/doctors/{id}` (detail)
- `GET /api/doctors/{id}/slots` (`?date= ?from= ?to= ?duration= ?tz=`; wraps `DoctorAvailabilityService`)
- `GET /api/patients/{id}` (own profile or a doctor-owned patient)
- `GET /api/me` (current user, any role)
- `GET /api/specialties` (read-only)
- `GET /api/medical-histories/{id}` (own or for a doctor-owned patient)
- `GET /api/prescriptions` (`?patient_id= ?from= ?to=`; current user by default)
- `GET /api/audit-logs` (admin-only; `?actor_id= ?action= ?subject_type= ?from= ?to=`)

**Auth surface (PR 1 only):** `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/me` (these reuse Sanctum's `HasApiTokens`; not in the public client surface above).

### Out of scope (deferred to later changes)

- **Writes** for `clinical-records` (medical notes, attachments) and `prescriptions` — tables exist; the wire-up is for a future change
- Webhooks for appointment events
- OpenAPI/Swagger generation
- Rate limiting beyond Sanctum's defaults
- CORS for browser clients (the patient-web change will add it)
- API versioning (`/api/v1/...`) — use `/api/...` for v1

## Approach (locked)

**API architecture**: Approach A — Laravel REST + Sanctum bearer tokens + FormRequest validation + Eloquent API Resources for response shaping.

**Why A over B (Filament API plugin) and C (hybrid)**: see explore-report §2. A is explicit, idiomatic, easy to test, plays well with strict TDD. B has unconfirmed v5.6 support and couples the API shape to the admin form. C pays an inconsistency tax.

**Components (new, unless noted):**
- `routes/api.php` (NEW) — `Route::middleware(['auth:sanctum', 'resolve.timezone'])->prefix('api')->group(...)`
- `app/Http/Controllers/Api/` (NEW dir) — thin controllers per resource
- `app/Http/Requests/Api/` (NEW dir) — FormRequest per write endpoint
- `app/Http/Resources/Api/` (NEW dir) — Eloquent API Resource per model
- `app/Http/Middleware/ResolveTimezone.php` (NEW) — reads `?tz=` query param + `app.clinic_timezone` config
- `bootstrap/app.php` (MOD) — `withMiddleware` adds `api` group, `withExceptions` adds the domain handler
- `app/Http/Responses/Api/ErrorResponse.php` (NEW) — the standard error envelope shape
- `config/clinic.php` (NEW) — `timezone` key, default `America/Argentina/Buenos_Aires`

**Auth**: Sanctum's `auth:sanctum` middleware on all endpoints except `POST /api/auth/login`. Role + ownership enforced by Gate/Policies (`AppointmentPolicy`, `PatientPolicy`, `UserPolicy`) — same as Filament.

**Timezone strategy (locked)**: local TZ by default + `?tz=` override. `ResolveTimezone` middleware reads `?tz=` first, falls back to `config('clinic.timezone')`, stores in `request->attributes`. API Resources wrap datetimes in `tz($request->attributes->get('tz'))`. Write bodies that carry explicit datetimes (POST `/api/appointments`) are interpreted in the resolved TZ; server converts to UTC before writing to DB.

**Error envelope (standard):**
```json
{
  "error": {
    "code": "SLOT_NOT_AVAILABLE",
    "message": "The requested slot is no longer available.",
    "details": { "doctor_id": 5, "start_time": "2026-06-15T13:00:00-03:00" }
  }
}
```

**Exception → HTTP mapping:**

| Exception | HTTP | error.code |
|---|---|---|
| `SlotNotAvailableException` | 409 | `SLOT_NOT_AVAILABLE` |
| `AnticipationWindowViolationException` | 422 | `ANTICIPATION_WINDOW_VIOLATION` |
| `PatientOverlapException` | 422 | `PATIENT_OVERLAP` |
| `UnauthorizedActorException` | 403 | `UNAUTHORIZED_ACTOR` |
| `CancellationWindowViolationException` | 422 | `CANCELLATION_WINDOW_VIOLATION` |
| `InvalidStateTransitionException` | 422 | `INVALID_STATE_TRANSITION` |
| `AuthorizationException` (Laravel) | 403 | `FORBIDDEN` |
| `ModelNotFoundException` | 404 | `NOT_FOUND` |
| `ValidationException` (Laravel) | 422 | `VALIDATION_ERROR` |
| `NotFoundHttpException` (Laravel) | 404 | `ROUTE_NOT_FOUND` |
| anything else | 500 | `INTERNAL_ERROR` |

## PR split (locked): 3-PR chained, stacked-to-main

| PR | Scope | Files (new) | LOC est. |
|---|---|---|---|
| **P1** | Auth + exception handler + middleware + base controller | `routes/api.php` + `bootstrap/app.php` (mod) + `ResolveTimezone` + `ErrorResponse` + `AuthController` + `LoginRequest` + `UserResource` + `AuthTest` + `ExceptionMappingTest` + `TimezoneMiddlewareTest` | ~330-380 |
| **P2** | Mutations: book + cancel + HTTP race test | `AppointmentController@store` + `AppointmentController@destroy` + `StoreAppointmentRequest` + `CancelAppointmentRequest` (optional) + `AppointmentResource` + `AppointmentMutationTest` + `ConcurrentDoubleBookHttpTest` (MariaDB-only) | ~330-380 |
| **P3** | Reads + state transitions + directory + audit/clinical reads | `AppointmentController@index` + `@show` + `DoctorController@index` + `@show` + `SpecialtyController@index` + `SlotController@show` + `TransitionController@confirm` + `@complete` + `@noShow` + `MeController` + `PatientController@show` + `MedicalHistoryController@show` + `PrescriptionController@index` + `AuditLogController@index` + ~6 FormRequests + ~8 API Resources + 8+ tests | ~330-380 |

**Chain strategy**: stacked-to-main (each PR merges to main; next PR branches off main) — same as agenda-core.

**Strict TDD per PR**: every production change has a red commit followed by a green commit. The pair `bfe7931 → ddecce5` from agenda-core PR 5 is the canonical pattern.

## Capabilities (delta specs to be created in sdd-spec phase)

### New Capabilities

- **`agenda/api`**: REST contract for booking + cancel + transitions + reads. Requirements: REQ-API-1 Sanctum bearer auth; REQ-API-2 RBAC + ownership; REQ-API-3 standard error envelope; REQ-API-4 domain exception → HTTP mapping; REQ-API-5 TZ resolution (default + override); REQ-API-6 the 14 endpoint contracts.
- **`agenda/concurrency-http`**: HTTP-layer concurrency contract. Requirements: REQ-API-7 two concurrent `POST /api/appointments` for the same slot return `409` for one and `201` for the other (persistence contract surfaced at the HTTP layer).

### Modified Capabilities

- **None.** No existing spec changes at the requirement level. `agenda`, `users-roles`, `clinical-records`, `prescriptions`, `admin-audit` keep their canonical text. The HTTP layer is additive.

## Affected Areas

| Area | Impact | Description |
|---|---|---|
| `routes/api.php` | NEW | New file; groups `/api/*` under `auth:sanctum` + `resolve.timezone` |
| `bootstrap/app.php` | MOD | `withMiddleware(api: ...)` + `withExceptions(DomainException → JSON)` |
| `app/Http/Middleware/ResolveTimezone.php` | NEW | `?tz=` → `app.clinic_timezone` → `request->attributes` |
| `app/Http/Controllers/Api/` | NEW dir | ~10 controllers |
| `app/Http/Requests/Api/` | NEW dir | ~6 FormRequests |
| `app/Http/Resources/Api/` | NEW dir | ~8 API Resources |
| `app/Http/Responses/Api/ErrorResponse.php` | NEW | Standard error envelope |
| `config/clinic.php` | NEW | `timezone` key |
| `app/Models/User.php` | MOD (P1) | Wire `HasApiTokens` token helpers (already imported) |
| `app/Exceptions/Domain/*` | UNCHANGED | All 6 already declare `httpStatus()` |
| `app/Actions/*` | UNCHANGED | Reused as-is |
| `app/Services/DoctorAvailabilityService.php` | UNCHANGED | Reused as-is |
| `tests/Feature/Api/` | NEW dir | ~15 feature tests |
| `openspec/specs/` | UNCHANGED this phase | Delta specs go to `openspec/changes/agenda-http/specs/` |

## Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| **N1** (new): empty `withExceptions` returns 500 HTML for every domain failure if PR 1 is botched | Med | P1 lands the handler + `ExceptionMappingTest` (6 cases, one per domain exception) as the first test in P1. Highest-priority deliverable. |
| **N2** (new): HTTP race surface doubles (mobile + web + multi-tab) beyond the action-level race | Med | P2 adds `ConcurrentDoubleBookHttpTest` (MariaDB-only) mirroring `tests/Feature/Agenda/ConcurrentDoubleBookTest.php`. Most important new test. |
| **N6** (new): 400-line budget pressure | Med | 3-PR chained split keeps each PR ~330-380 LOC. Per-PR `ask-always` confirm. |
| **N3** (new): `SANCTUM_STATEFUL_DOMAINS` not yet configured for the future patient-web | Low | Document in design as TODO; surface for the patient-web change. Bearer-only is sufficient for v1. |
| **R1** (inherited): 8 untested scenarios in `clinical-records` + `prescriptions` (W1-W2) | Low | agenda-http exposes GET-only for these; no writes. Deferred tests stay deferred. |
| **R2** (inherited): action-level `ConcurrentDoubleBookTest` is the persistence safety net | Low | Action test stays; HTTP test added (N2). |

## Rollback Plan

- **PR-by-PR rollback**: revert the merge commit on `main` for the affected PR. Each PR is a self-contained chunk; the Filament UI and the action layer are unchanged in this change, so reverting removes the HTTP surface cleanly without orphaning domain writes.
- **Feature flag fallback (if needed)**: a `config('agenda.api_enabled', true)` toggle gates the `routes/api.php` registration in `bootstrap/app.php` via a `Route::middleware('feature:agenda.api')` check. If a hotfix is needed in production, flipping the flag to `false` returns 404 for all `/api/*` routes within one deploy.
- **Token revocation**: Sanctum's `User::tokens()->delete()` is the kill switch for compromised tokens. No DB-level cleanup needed; tokens are rows in `personal_access_tokens`.
- **No DB migrations** in this change. Zero schema risk. `migrate:fresh --seed` is unchanged.

## Dependencies

- `agenda-core` (archived, `6ba4d6a`) — supplies the action layer, services, exceptions, and policies that this change wraps.
- `laravel/sanctum` 4.3.2 — already installed; `User` model already has `HasApiTokens`.
- PHP 8.4+ / Laravel 13 / Pest 3 — current stack, no upgrade.

## Success Criteria

1. `migrate:fresh --seed` succeeds on MariaDB 10.11.9 (no schema changes; this is a transport-only change).
2. `vendor/bin/pest` green on SQLite with `DB_CONNECTION=sqlite` — expect 50+ passed (the 46 from agenda-core + 4+ new HTTP tests from P1) and the 2 driver-aware skipped (action-level `ConcurrentDoubleBookTest`).
3. `vendor/bin/pest` green on MariaDB — expect 52+ passed (the 46 from agenda-core + 4+ new HTTP tests from P1 + 2 from P2 that switch the action-level `ConcurrentDoubleBookTest` to also exercise the HTTP layer).
4. `php artisan route:list --path=api` shows 14+ routes (the 14 endpoints + the 3 auth endpoints = 17 in the final state).
5. Tinker: `php artisan tinker --execute="app(\App\Services\DoctorAvailabilityService::class)"` confirms the service is unchanged.
6. Tinker/HTTP smoke: create a Sanctum token for a patient user via `php artisan tinker`, then `curl -H "Authorization: Bearer $TOKEN" -X POST /api/appointments -d '{...}'` books a published slot and returns 201 with the appointment JSON.
7. Concurrent double-book: two parallel `curl POST /api/appointments` calls for the same slot return 201 (one) and 409 (the other) with the `SLOT_NOT_AVAILABLE` error code.
8. `git log` shows the 3-PR chain: P1 (auth + exceptions) → P2 (mutations + race) → P3 (reads + transitions).

## Open questions (deferred to sdd-spec)

- Pagination defaults (`per_page=20`, `max=100`) — sdd-spec will lock
- Pagination response shape (cursor vs offset) — sdd-spec will lock
- Whether `?include=` query param (eager-loading relations) is in scope for P3 — sdd-spec will lock
- Whether the audit log endpoint requires any specific role check beyond `isAdmin` — sdd-spec will lock

## Next steps (after proposal approval)

1. User approves this proposal.
2. Orchestrator launches `/sdd-ff agenda-http` to fast-forward: sdd-spec → sdd-design → sdd-tasks. Produces the 2 delta spec files, the design doc, and the task list.
3. User reviews the spec/design/tasks.
4. Orchestrator launches `/sdd-apply` for P1 (auth + exception handler).
5. After P1 lands and merges, `/sdd-apply` for P2 (mutations + race), then P3 (reads + transitions).
6. After all 3 PRs land, `/sdd-verify` audits the implementation against this proposal + the new specs.
7. `/sdd-archive` syncs the agenda-http delta specs into `openspec/specs/agenda-api/` + `openspec/specs/agenda-concurrency-http/` and moves the change folder to `openspec/changes/archive/2026-06-02-agenda-http/`.

## skill_resolution

paths-injected
