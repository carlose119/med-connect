# Design: agenda-http

## 1. Context

Patients and doctors on the go need a JSON HTTP transport to book appointments, cancel them, and look at the agenda from a future mobile app and a future patient web portal. The Filament admin/doctor UI works for the staff, but it is not reachable from a phone, a browser tab that is not on the staff side, or a third-party integration. The domain layer that backs those interactions — `BookAppointmentAction`, `CancelAppointmentAction`, the four `*AppointmentTransition` classes, and `DoctorAvailabilityService` — is already in place and tested (archived at `6ba4d6a`); the only thing missing is the transport that wraps it.

`agenda-core` (archived) supplied the runnable skeleton, the layered hexagonal shape (Models / States / Services / Actions / Exceptions / Policies), the appointments state machine on top of `spatie/laravel-model-states` 2.13, the driver-aware unique partial index on `(doctor_id, start_time)`, and the six domain exceptions with their `httpStatus()` declarations. The `User` model already wires `Laravel\Sanctum\HasApiTokens`. The three policies (`AppointmentPolicy`, `PatientPolicy`, `UserPolicy`) are already registered and gated. `agenda-core` also landed one HTTP-shaped feature test per action (`tests/Feature/Agenda/BookAppointmentFailureTest.php`, `ConcurrentDoubleBookTest.php`) that uses `actingAs()` + the HTTP helpers, so the testbed for API work is proven.

This change — `agenda-http` — adds the transport layer only. No new domain logic, no new state edges, no DB migrations. It exposes the existing domain over a JSON HTTP API under `/api/*`, behind Sanctum bearer tokens, with the same Gate policies that Filament uses, and surfaces the six domain exceptions as the canonical HTTP error codes documented in the proposal. The 16 public endpoints + 3 auth endpoints (login/logout/me) are split across three chained PRs, each one under the 400-line review budget.

## 2. Goals & non-goals

**Goals**

- Expose the existing agenda domain over a JSON HTTP API (book, cancel, transitions, reads, directory, audit).
- Map the 6 domain exceptions + 4 Laravel exceptions to the canonical HTTP status + error-code pairs (see §6) via a single `withExceptions(...)` block in `bootstrap/app.php`.
- Resolve the timezone per request: `?tz=` query param override → `config('clinic.timezone')` → `America/Argentina/Buenos_Aires` default. Store the resolved TZ on `request->attributes['tz']` and read it in every API Resource and every datetime-accepting FormRequest.
- Enforce RBAC through the existing `AppointmentPolicy`, `PatientPolicy`, and `UserPolicy` Gate policies (no new policy class needed). Scoped queries: patients see only their own appointments and prescriptions; doctors see only theirs; admins see all.
- Paginated list endpoints with the `LengthAwarePaginator` envelope (`{data, meta, links}`) and `per_page` default 20 / max 100.
- Strict TDD per PR: red commit → green commit per production change. The `bfe7931 → ddecce5` pair from `agenda-core` PR 5 is the canonical pattern.
- Keep each PR under the 400-line review budget.
- All datetimes UTC in the DB (per the locked PRD); ISO 8601 with offset in the resolved TZ on the wire.

**Non-goals** (deferred to later changes)

- No new domain logic (no new actions, no new state transitions, no new models, no new policies).
- No DB schema changes (no new migrations; the `agenda-core` schema is the source of truth).
- No CORS configuration (deferred to the future patient-web change).
- No API versioning — endpoints live at `/api/...`, not `/api/v1/...`. Version bump is a future change.
- No OpenAPI / Swagger generation. The API is self-documenting via consistent naming and the standard envelope. Auto-generation (via `darkaonline/l5-swagger` or `scribe`) is a future change.
- No writes for `medical_notes`, `medical_attachments`, or `prescriptions`. GET-only is exposed; the wire-up for POST/PUT/DELETE lands in a future change.
- No rate limiting beyond Sanctum's defaults. No webhook subscriptions. No `?include=` eager-loading param. No cursor-based pagination.
- No `api_enabled` feature flag. Sanctum token revocation (`User::tokens()->delete()`) is the kill switch.

## 3. Architecture

```
Client (mobile / web / CLI)
  |  HTTP+JSON
  |  Authorization: Bearer <sanctum-token>
  v
[1] Middleware chain
    ResolveTimezone          (NEW; sets request->attributes['tz'])
    Authenticate (sanctum)   (Laravel stock; rejects with 401 if missing/expired)
    SubstituteBindings       (Laravel stock; implicit route-model binding)
  |
  v
[2] Route  ->  Controller method
    (thin: validation -> action call -> resource)
  |
  v
[3] FormRequest  (validation, tz->toUtc on datetime inputs)
       |
       v
    Action / Eloquent query / Transition class  (domain logic from agenda-core)
       |
       v
    DomainException OR Appointment model
  |
  v
[4] Eloquent API Resource
    (formats datetimes via $request->attributes['tz']->toLocal(...))
  |
  v
JSON  ->  Client
```

**Layer [1] — Middleware chain** (new for `agenda-http`):

- `App\Http\Middleware\ResolveTimezone` (NEW) — reads `?tz=` from the query string, validates against `timezone_identifiers_list()`, sets `request->attributes->set('tz', $tz)`. Falls back to `config('clinic.timezone')` (default `America/Argentina/Buenos_Aires`) when `?tz=` is absent. Throws `InvalidTimezoneException` (custom, 422 `INVALID_TIMEZONE`) when the value is non-empty and invalid. **Runs before `auth:sanctum`** so the 401 response is also rendered in the requested TZ.
- `Illuminate\Auth\Middleware\Authenticate` (Laravel stock) — gated to the `sanctum` guard. Triggers `AuthenticationException`, mapped to `401` by the handler in §6.
- `Illuminate\Routing\Middleware\SubstituteBindings` (Laravel stock) — implicit route-model binding for `{appointment}`, `{doctor}`, `{patient}`, `{medical_history}`.

Mounted in `routes/api.php` as:

```
Route::middleware([ResolveTimezone::class, 'auth:sanctum'])
    ->prefix('api')
    ->group(__DIR__.'/api.php');
```

Registered in `bootstrap/app.php` via `->withRouting(api: __DIR__.'/../routes/api.php', apiPrefix: 'api', ...)`.

**Layer [2] — Controllers** (new dir `app/Http/Controllers/Api/`):

| Controller | Methods | Wraps | LOC est. |
|---|---|---|---|
| `AuthController` | `login`, `logout`, `me` | Sanctum `createToken` / `tokens()->delete()` | ~50 |
| `AppointmentController` | `index`, `show`, `store`, `destroy` | `BookAppointmentAction`, `CancelAppointmentAction`, Eloquent | ~80 |
| `AppointmentTransitionController` | `confirm`, `complete`, `markNoShow` | `*AppointmentTransition` classes | ~60 |
| `DoctorController` | `index`, `show`, `slots` | `DoctorAvailabilityService` | ~60 |
| `PatientController` | `show` | Eloquent + `PatientPolicy` | ~30 |
| `MeController` | `show` | Eloquent (current user) | ~20 |
| `SpecialtyController` | `index` | Eloquent (active only) | ~20 |
| `MedicalHistoryController` | `show` | Eloquent + `MedicalNotePolicy` | ~30 |
| `PrescriptionController` | `index` | Eloquent | ~40 |
| `AuditLogController` | `index` | Eloquent (admin-only) | ~40 |

**Total: 10 controllers, ~430 LOC.** Each is thin: FormRequest → action call → Resource. No controller method exceeds 25 lines of business logic.

**Layer [3] — FormRequests** (new dir `app/Http/Requests/Api/`):

| FormRequest | Fields | Rules |
|---|---|---|
| `LoginRequest` (P1) | `email`, `password`, `device_name?` | `required`, `email`, `string`, `exists:users,email` |
| `BookAppointmentRequest` (P2) | `doctor_id`, `start_time`, `notes?` | `required`, `exists:doctors,id`, `date`, `after:+2 hours`, `string|max:1000` |
| `CancelAppointmentRequest` (P2) | `reason` | `required`, `string`, `max:1000` |
| `AppointmentTransitionRequest` (P3) | `notes?` | `nullable`, `string`, `max:1000` |
| `ListAppointmentsRequest` (P3) | `from`, `to`, `doctor_id`, `patient_id`, `state`, `mine`, `per_page` | `nullable`, `date`, `exists`, `in:pending,confirmed,completed,cancelled,no_show`, `boolean`, `integer\|min:1\|max:100` |
| `ListDoctorsRequest` (P3) | `specialty_id?`, `q?`, `per_page?` | `nullable`, `exists:specialties,id`, `string`, `integer\|min:1\|max:100` |
| `ListSlotsRequest` (P3) | `date`, `duration?`, `from?`, `to?` | `required`, `date_format:Y-m-d`, `integer\|min:15\|max:240`, `date_format:H:i` |
| `ListPrescriptionsRequest` (P3) | `patient_id?`, `from?`, `to?`, `per_page?` | `nullable`, `exists:patients,id`, `date`, `integer\|min:1\|max:100` |
| `ListAuditLogsRequest` (P3) | `actor_id?`, `action?`, `subject_type?`, `from?`, `to?`, `per_page?` | `nullable`, `exists:users,id`, `string`, `string`, `date`, `integer\|min:1\|max:100` |

**Total: 9 FormRequests, ~250 LOC.** Each `validated()` call also re-casts the datetime fields through `App\Clinic\Timezone::toUtc()` so the action layer receives UTC regardless of the resolved TZ.

**Layer [4] — Eloquent API Resources** (new dir `app/Http/Resources/Api/`):

| Resource | Wraps | Renders |
|---|---|---|
| `UserResource` | `User` | `id`, `name`, `email`, `role` (no `password`, no `remember_token`, no `email_verified_at`) |
| `DoctorResource` | `Doctor` | `id`, `name`, `license_number`, `bio`, `specialty{id,name,slug}` |
| `PatientResource` | `Patient` | `id`, `name`, `dni`, `birth_date` (in resolved TZ) |
| `SpecialtyResource` | `Specialty` | `id`, `name`, `slug`, `is_active` |
| `AppointmentResource` | `Appointment` | `id`, `state`, `start_time`, `end_time` (in resolved TZ), `notes`, `cancellation_reason`, `doctor{id,name}`, `patient{id,name}` |
| `MedicalHistoryResource` | `MedicalHistory` | `id`, `patient_id`, `opened_at`, `notes_count` |
| `PrescriptionResource` | `Prescription` | `id`, `unique_code`, `issued_at`, `items{drug,dose,frequency,duration,position}` |
| `AuditLogResource` | `AuditLog` | `id`, `actor_type`, `actor_id`, `verb`, `subject_type`, `subject_id`, `payload`, `occurred_at` |
| `SlotResource` | `{start, end}` array | `start`, `end` in resolved TZ ISO 8601 with offset |

**Total: 9 Resources, ~350 LOC.** Each calls `request()->attributes->get('tz')->toLocal($model->start_time)->toIso8601String()` for datetime fields. No JSON-LD, no HAL — just the standard `{data: ...}` envelope.

**Exception handler** (modified `bootstrap/app.php`):

```
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
        if (! $request->is('api/*')) {
            return null;  // web routes keep the default HTML handling
        }
        return \App\Http\Responses\Api\ErrorResponse::fromException($e, $request);
    });
})
```

`ErrorResponse::fromException()` (NEW, in `app/Http/Responses/Api/ErrorResponse.php`) inspects the exception class, returns a `JsonResponse` with the correct status + `{"error":{"code","message","details?"}}` envelope. See §6 for the full mapping.

**TZ resolution** — `App\Clinic\Timezone` (NEW value object):

```
final readonly class Timezone
{
    public function __construct(public string $name) {}     // validated IANA name
    public static function from(string $name): self;       // throws InvalidTimezoneException
    public function toLocal(CarbonInterface $utc): CarbonImmutable;   // ->tz($this->name)
    public function toUtc(CarbonInterface $local): CarbonImmutable;   // ->tz('UTC')
    public function format(CarbonInterface $utc): string;             // ISO 8601 with offset
}
```

Resolved per-request by `ResolveTimezone`, stored in `request->attributes['tz']`. All Eloquent API Resources call `$tz->format($model->start_time)`; all FormRequests that accept a datetime call `$tz->toUtc(CarbonImmutable::parse($input))` before passing to the action.

**Auth + RBAC** — Sanctum's `HasApiTokens` is already on `User`. Each controller method that touches a model instance calls `$this->authorize('view|cancel|update', $model)` to invoke the existing policy. For list endpoints the query is scoped before the authorize call:

- `AppointmentController@index` — `$query->where('patient_id', auth()->user()->patient->id)` for patients, `$query->where('doctor_id', $doctor->id)` for doctors, no scope for admins.
- `PrescriptionController@index` — same shape, scoped by `patient_id` for patients and via the appointments join for doctors.
- `AuditLogController@index` — `$this->authorize('viewAny', AuditLog::class)`; only `isAdmin()` passes (enforced inside the controller with an explicit role check that throws `AuthorizationException` for safety).

## 4. Data model

**No schema changes.** `agenda-http` is transport-only. The data model is exactly the agenda-core data model, documented in the archived agenda-core `design.md` §"Database Design" and the canonical specs under `openspec/specs/{agenda,doctor-schedule,users-roles,clinical-records,prescriptions,admin-audit}/`. No new tables, no new columns, no new indexes. `migrate:fresh --seed` is unchanged.

The only configuration added is `config/clinic.php` (NEW, ~15 LOC):

```
return [
    'timezone' => env('CLINIC_TIMEZONE', 'America/Argentina/Buenos_Aires'),
];
```

This key is read by `ResolveTimezone` middleware (fallback) and exposed for any future display logic.

## 5. API surface

16 public endpoints + 3 auth endpoints (19 total). The "14" in the proposal was an undercount; the spec locked pagination + audit + auth endpoints to 19. All endpoints live under `/api/*`; all but `POST /api/auth/login` require a Sanctum bearer token.

| # | Method | Path | Controller#action | Auth | FormRequest | Resource | Wraps |
|---|---|---|---|---|---|---|---|
| 1 | POST | `/api/auth/login` | `AuthController@login` | none | `LoginRequest` | `UserResource` (+`token`) | Sanctum `createToken` |
| 2 | POST | `/api/auth/logout` | `AuthController@logout` | any | — | — (204) | `tokens()->delete()` |
| 3 | GET | `/api/auth/me` | `AuthController@me` | any | — | `UserResource` | current user |
| 4 | POST | `/api/appointments` | `AppointmentController@store` | patient | `BookAppointmentRequest` | `AppointmentResource` (201) | `BookAppointmentAction` |
| 5 | DELETE | `/api/appointments/{appointment}` | `AppointmentController@destroy` | patient (own), doctor (own), admin | `CancelAppointmentRequest` | `AppointmentResource` (200) | `CancelAppointmentAction` |
| 6 | POST | `/api/appointments/{appointment}/transitions/confirm` | `AppointmentTransitionController@confirm` | doctor (own), admin | `AppointmentTransitionRequest` | `AppointmentResource` (200) | `ConfirmAppointmentTransition` |
| 7 | POST | `/api/appointments/{appointment}/transitions/complete` | `AppointmentTransitionController@complete` | doctor (own), admin | `AppointmentTransitionRequest` | `AppointmentResource` (200) | `CompleteAppointmentTransition` |
| 8 | POST | `/api/appointments/{appointment}/transitions/no-show` | `AppointmentTransitionController@markNoShow` | doctor (own), admin | `AppointmentTransitionRequest` | `AppointmentResource` (200) | `MarkNoShowAppointmentTransition` |
| 9 | GET | `/api/appointments` | `AppointmentController@index` | any (scoped) | `ListAppointmentsRequest` | `AppointmentResource::collection` (paginated) | Eloquent + role scope |
| 10 | GET | `/api/appointments/{appointment}` | `AppointmentController@show` | any (gated) | — | `AppointmentResource` | `AppointmentPolicy@view` |
| 11 | GET | `/api/doctors` | `DoctorController@index` | any | `ListDoctorsRequest` | `DoctorResource::collection` (paginated) | Eloquent |
| 12 | GET | `/api/doctors/{doctor}` | `DoctorController@show` | any | — | `DoctorResource` | Eloquent |
| 13 | GET | `/api/doctors/{doctor}/slots` | `DoctorController@slots` | any | `ListSlotsRequest` | `SlotResource::collection` (not paginated) | `DoctorAvailabilityService` |
| 14 | GET | `/api/patients/{patient}` | `PatientController@show` | patient (own), doctor (assigned), admin | — | `PatientResource` | `PatientPolicy@view` |
| 15 | GET | `/api/me` | `MeController@show` | any | — | `UserResource` | current user |
| 16 | GET | `/api/specialties` | `SpecialtyController@index` | any | — | `SpecialtyResource::collection` (not paginated) | Eloquent (`is_active = true`) |
| 17 | GET | `/api/medical-histories/{medical_history}` | `MedicalHistoryController@show` | patient (own), doctor (assigned), admin | — | `MedicalHistoryResource` | `MedicalNotePolicy@view` |
| 18 | GET | `/api/prescriptions` | `PrescriptionController@index` | any (scoped) | `ListPrescriptionsRequest` | `PrescriptionResource::collection` (paginated) | Eloquent + role scope |
| 19 | GET | `/api/audit-logs` | `AuditLogController@index` | admin only | `ListAuditLogsRequest` | `AuditLogResource::collection` (paginated) | Eloquent |

Route model binding: `{appointment}`, `{doctor}`, `{patient}`, `{medical_history}` resolve to their respective models via `SubstituteBindings`. The `AuditLogController@index` does NOT use route model binding (it lists rows). The list endpoints (`index`) return `{data, meta, links}`; the detail endpoints (`show`) return `{data}`; the write endpoints return the resource; `DELETE` with no body and `logout` return `204` with an empty body.

## 6. Exception → HTTP mapping

| Exception | HTTP | `error.code` | Handler location |
|---|---|---|---|
| `App\Exceptions\Domain\SlotNotAvailableException` | 409 | `SLOT_NOT_AVAILABLE` | `bootstrap/app.php` `withExceptions` → `ErrorResponse::fromException()` |
| `App\Exceptions\Domain\AnticipationWindowViolationException` | 422 | `ANTICIPATION_WINDOW_VIOLATION` | same |
| `App\Exceptions\Domain\PatientOverlapException` | 422 | `PATIENT_OVERLAP` | same |
| `App\Exceptions\Domain\UnauthorizedActorException` | 403 | `UNAUTHORIZED_ACTOR` | same |
| `App\Exceptions\Domain\CancellationWindowViolationException` | 422 | `CANCELLATION_WINDOW_VIOLATION` | same |
| `App\Exceptions\Domain\InvalidStateTransitionException` | 422 | `INVALID_STATE_TRANSITION` | same |
| `App\Exceptions\InvalidTimezoneException` (NEW) | 422 | `INVALID_TIMEZONE` | same |
| `Illuminate\Auth\AuthenticationException` | 401 | `UNAUTHENTICATED` (or `TOKEN_EXPIRED` when the Sanctum token is past `expires_at`) | same |
| `Illuminate\Auth\Access\AuthorizationException` | 403 | `FORBIDDEN` | same |
| `Illuminate\Database\Eloquent\ModelNotFoundException` | 404 | `NOT_FOUND` | same |
| `Illuminate\Validation\ValidationException` | 422 | `VALIDATION_ERROR` (`details` = field-keyed errors) | same |
| `Symfony\Component\HttpKernel\Exception\NotFoundHttpException` | 404 | `ROUTE_NOT_FOUND` | same |
| Anything else | 500 | `INTERNAL_ERROR` (no `details`, message redacted in production) | same — catch-all in `ErrorResponse::fromException()` |

The handler location is a single call in `bootstrap/app.php`:

```
$exceptions->render(function (\Throwable $e, Request $request) {
    if (! $request->is('api/*')) return null;
    return \App\Http\Responses\Api\ErrorResponse::fromException($e, $request);
});
```

`ErrorResponse::fromException()` (NEW, ~70 LOC) holds the `match()` on the exception class. Domain exceptions are matched by `instanceof DomainException`; their `httpStatus()` is the canonical source, and the `code` is derived from the exception's short class name uppercased to snake_case (e.g. `SlotNotAvailableException` → `SLOT_NOT_AVAILABLE`). For the `details` payload: `SlotNotAvailableException` carries the conflicting appointment id; `ValidationException` carries the field errors; everything else omits `details` or carries a redacted `null`.

`InvalidTimezoneException` (NEW, in `app/Exceptions/InvalidTimezoneException.php`) is the only new exception class this change adds. It is not a `DomainException`; it lives in `App\Exceptions\` and is thrown by the `ResolveTimezone` middleware.

## 7. Timezone strategy

**Locked decision**: local TZ by default + `?tz=` query param override.

**Resolution chain** (per request):

1. The `ResolveTimezone` middleware runs first. It reads `$request->query('tz')`.
2. If non-empty, the middleware validates against `timezone_identifiers_list()`. Invalid → throws `InvalidTimezoneException` (422 `INVALID_TIMEZONE`).
3. If valid (or empty), the middleware sets `request->attributes->set('tz', new Timezone($name))`.
4. When the query param is empty, the middleware falls back to `config('clinic.timezone')`, which defaults to `America/Argentina/Buenos_Aires` and can be overridden by `CLINIC_TIMEZONE` in `.env`.
5. The `Timezone` value object is immutable and safe to share across the request.

**Where it is used**:

- `App\Http\Resources\Api\*Resource::toArray($request)` — every datetime field goes through `$request->attributes->get('tz')->format($model->{field})`. Output is ISO 8601 with the resolved offset (e.g. `2026-06-15T13:00:00-03:00`).
- `App\Http\Requests\Api\*Request::validated()` overrides — datetime fields are re-parsed and converted: `$tz->toUtc(CarbonImmutable::parse($value))`. The action layer always sees UTC, matching the DB column.
- `DoctorController@slots` — passes the resolved TZ to `DoctorAvailabilityService::slots(..., $tz)` so the slot edges are rendered in the requested zone.
- `ErrorResponse::fromException()` — when the exception's `details` carries a datetime (e.g. `SLOT_NOT_AVAILABLE` includes `start_time`), that datetime is also formatted in the resolved TZ.

**Login ignores `?tz=`**: `POST /api/auth/login` runs the middleware too (it is a public endpoint that still benefits from the resolved TZ for the response), but the timezone is purely cosmetic on the response. No conversion is needed because the request body has no datetime.

**Middleware order** (in `routes/api.php`):

```
Route::middleware([ResolveTimezone::class, 'auth:sanctum'])
```

`ResolveTimezone` MUST run before `auth:sanctum` so the 401 response is also rendered in the requested TZ (a UI that displays dates in `America/New_York` should still see its own zone in the error body). Documented in N7.

## 8. Test approach

**Test framework**: Pest 4.7.1 (already on dev deps). Default runner: `vendor/bin/pest` with in-memory SQLite (`DB_CONNECTION=sqlite`). MariaDB parity: `DB_CONNECTION=mariadb vendor/bin/pest`.

**Test surface** (new dir `tests/Feature/Api/`):

| Layer | What to test | Approach | Driver |
|---|---|---|---|
| Feature | Auth: login/logout/me happy + bad creds + expired token + missing token | Pest + Sanctum `actingAs($user, 'sanctum')` + `postJson/getJson` | SQLite |
| Feature | Exception envelope shape + 10 exception → HTTP mappings (REQ-API-3 + REQ-API-4) | Pest + `actingAs` + `assertStatus` + `assertJsonPath('error.code', '...')` | SQLite |
| Feature | Timezone resolution: no `?tz=`, `?tz=America/New_York`, write body stored as UTC, invalid `?tz=` (REQ-API-5) | Pest + `actingAs` + Carbon assertions on the response body | SQLite |
| Feature | Book (P2): happy, validation failure, slot taken, anticipation window | Pest + `actingAs($patient, 'sanctum')` + `postJson` | SQLite |
| Feature | Cancel (P2): happy inside 24h, rejected outside 24h, happy by doctor anytime | Pest + `actingAs` + `deleteJson` | SQLite |
| Feature | HTTP concurrency: two concurrent `POST /api/appointments` for the same slot (REQ-CONC-1) | Pest + `DB::transaction` simulation + MariaDB-only run, `skip()` on SQLite | **MariaDB only** |
| Feature | List/detail + role scoping for every endpoint in P3 (REQ-API-2 + REQ-API-7) | Pest + `actingAs` per role + `assertJsonPath('data.0.id', ...)` | SQLite |
| Feature | Audit log: admin sees, doctor/patient 403 | Pest + `actingAs` per role | SQLite |

**Per-endpoint test count**: 2-3 tests per endpoint (happy + auth failure + optional domain failure). Some endpoints with stronger contracts (book, cancel, confirm) get 4 scenarios each.

**HTTP race test** (`tests/Feature/Api/ConcurrentDoubleBookHttpTest.php`, NEW, ~60 LOC):

- Runs **only** when `config('database.default') === 'mariadb'`; skipped on SQLite (SQLite's transaction model serializes the requests and does not surface the race — the persistence safety net is the action-level test, not this one).
- Uses `DB::transaction` + a fork via `pcntl_fork()` when available, or a helper that spawns two `Http::call('POST', ...)` against the in-process kernel via `app()->handle($request)`.
- Asserts: one response is `201` with the appointment JSON; the other is `409` with `error.code = "SLOT_NOT_AVAILABLE"` and `error.details.conflicting_appointment_id` is the id of the winning row.

**Test fixture helpers** (NEW, in `tests/Support/`):

- `CreatesPatients` trait (P1) — `createPatientUser(): array{user, patient, token}` builds a `User` (role=patient), a `Patient` profile, and a Sanctum token via `->createToken('test')` in one call.
- `CreatesDoctors` trait (P3) — same shape for doctors, with a published `DoctorSchedule` for the test's day-of-week so the slot lookup finds the row.
- Both traits are bound to the test classes via `uses(...)` in the relevant test file (Pest convention).

**TDD discipline**: every production change has a `test(...):` red commit followed by a `feat(...):` green commit. The `bfe7931 → ddecce5` pair from `agenda-core` PR 5 is the canonical pattern. `sdd-verify` checks `git log` for these pairs.

## 9. PR split (3-PR chained — locked)

Chain strategy: **stacked-to-main** (each PR merges to main; the next PR branches off main — same as `agenda-core`). Delivery strategy is `ask-always`; the user signs off per PR before the next launches.

### PR 1 — Auth + exception handler + TZ middleware

**Goal**: ship the JSON transport skeleton. After PR 1 merges, every endpoint hits the standard error envelope, the TZ middleware is in place, and `POST /api/auth/login` works.

**TDD red commits** (must fail first):

- `test(api): pin the standard error envelope + 10 exception → HTTP mappings` — `tests/Feature/Api/ExceptionMappingTest.php` with 10 scenarios (one per row of the mapping table in §6 plus the envelope shape).
- `test(api): login returns 200 with user + token, bad creds 401, missing token 401` — `tests/Feature/Api/Auth/LoginTest.php` with 3 scenarios.
- `test(middleware): ResolveTimezone honors ?tz=, falls back to config, rejects invalid 422` — `tests/Feature/Api/TimezoneMiddlewareTest.php` with 3 scenarios.

**TDD green commits** (one per red):

- `feat(api): withExceptions handler in bootstrap/app.php + ErrorResponse helper + InvalidTimezoneException + ResolveTimezone middleware + auth:sanctum on /api/*`
- `feat(api): AuthController@login + LoginRequest + UserResource + auth.sanctum guard on POST /api/auth/login`
- (covered by the same green commit as the first)

**Structural commits** (no red→green needed):

- `chore(routes): create routes/api.php and register it in bootstrap/app.php via withRouting(api:)`
- `chore(config): add config/clinic.php with timezone + CLINIC_TIMEZONE env`

**Expected log shape**:

```
<hash> feat(api): auth + exception handler + TZ middleware (green)
<hash> test(api): pin the error envelope + 10 exception mappings (red)
<hash> test(api): login happy + bad creds + missing token (red)
<hash> test(middleware): TZ resolution + invalid 422 (red)
<hash> chore(config): add clinic.timezone config
<hash> chore(routes): create routes/api.php + register in bootstrap/app.php
```

**LOC**: ~330-380 hand-written (controllers, FormRequests, Resources, middleware, ErrorResponse helper, the 3 tests, config + routes).

**What it enables for PR 2**: the exception handler is in place, the `?tz=` middleware is in place, the `/api/*` route group is registered, the auth flow is proven. PR 2 just adds the appointment mutation endpoints.

### PR 2 — Mutations + race test

**Goal**: book + cancel + the HTTP-layer concurrency contract.

**TDD red commits**:

- `test(api): POST /api/appointments returns 201 with AppointmentResource on happy path` — `tests/Feature/Api/BookAppointmentTest.php` with 4 scenarios (happy, missing `doctor_id`, slot already booked, anticipation window).
- `test(api): DELETE /api/appointments/{id} returns 200 with state=cancelled on happy path` — `tests/Feature/Api/CancelAppointmentTest.php` with 3 scenarios (happy by patient inside 24h, rejected outside 24h, happy by doctor anytime).

**TDD green commits** (one per red):

- `feat(api): AppointmentController@store + BookAppointmentRequest + AppointmentResource`
- `feat(api): AppointmentController@destroy + CancelAppointmentRequest + `PatientPolicy` (already exists) on the cancel gate`

**Race test** (separate sub-pair):

- `test(api): two concurrent POST /api/appointments for the same slot return 201 + 409 (MariaDB-only)` — `tests/Feature/Api/ConcurrentDoubleBookHttpTest.php` with 1 scenario; uses `DB_CONNECTION=mariadb` env guard.
- `chore(test): the race test passes against the live MariaDB` (assertion that the green passes on the real driver — the SQLite run marks it skipped).

**Structural commits**:

- `chore(test): add test fixture helper CreatesPatients in tests/Support/`

**Expected log shape**:

```
<hash> feat(api): cancel endpoint with PatientPolicy gate (green)
<hash> test(api): cancel happy + outside-window + doctor-anytime (red)
<hash> feat(api): book endpoint + AppointmentResource (green)
<hash> test(api): book happy + validation + slot-taken + anticipation (red)
<hash> feat(test): ConcurrentDoubleBookHttpTest green on MariaDB
<hash> test(api): two concurrent POSTs return 201 + 409 (red, MariaDB-only)
<hash> chore(test): add CreatesPatients fixture trait
```

**LOC**: ~330-380 hand-written (2 controllers, 2 FormRequests, 1 Resource, 3 tests + 1 helper trait).

**What it enables for PR 3**: the action layer is reachable from the wire with the right error codes. PR 3 just adds the read endpoints and the three transition endpoints.

### PR 3 — Reads + state transitions + directory + audit/clinical reads

**Goal**: complete the public surface. After PR 3 merges, all 19 endpoints are live.

**TDD red+green pairs** (one per endpoint, in this order):

- `test(api): GET /api/appointments returns paginated list scoped by role` + `feat(api): AppointmentController@index + ListAppointmentsRequest` (3 scenarios: patient sees own, doctor sees own, admin sees all)
- `test(api): GET /api/appointments/{id} returns detail for authorized actor` + `feat(api): AppointmentController@show` (2 scenarios: happy, 403 for non-assigned)
- `test(api): GET /api/doctors returns paginated list` + `feat(api): DoctorController@index + ListDoctorsRequest + DoctorResource` (1 scenario)
- `test(api): GET /api/doctors/{id} returns detail` + `feat(api): DoctorController@show` (1 scenario)
- `test(api): GET /api/doctors/{id}/slots returns available slots in resolved TZ` + `feat(api): DoctorController@slots + ListSlotsRequest + SlotResource` (3 scenarios: happy, invalid date 422, `?tz=` override)
- `test(api): GET /api/patients/{id} returns profile for authorized actor` + `feat(api): PatientController@show` (3 scenarios: self, doctor-assigned, doctor-unassigned 403)
- `test(api): GET /api/me returns current user` + `feat(api): MeController@show` (1 scenario)
- `test(api): GET /api/specialties returns active list` + `feat(api): SpecialtyController@index + SpecialtyResource` (1 scenario)
- `test(api): GET /api/medical-histories/{id} returns history for authorized actor` + `feat(api): MedicalHistoryController@show + MedicalHistoryResource` (3 scenarios: own, assigned doctor, unassigned 403)
- `test(api): GET /api/prescriptions returns paginated list scoped by role` + `feat(api): PrescriptionController@index + ListPrescriptionsRequest + PrescriptionResource` (3 scenarios: patient own, doctor own, admin all)
- `test(api): GET /api/audit-logs returns paginated list for admin only` + `feat(api): AuditLogController@index + ListAuditLogsRequest + AuditLogResource` (3 scenarios: admin happy, doctor 403, patient 403)
- `test(api): POST /api/appointments/{id}/transitions/confirm returns 200 with state=confirmed` + `feat(api): AppointmentTransitionController@confirm + AppointmentTransitionRequest` (2 scenarios: assigned doctor, unassigned doctor 403 `UNAUTHORIZED_ACTOR`)
- `test(api): POST /api/appointments/{id}/transitions/complete returns 200 with state=completed` + `feat(api): AppointmentTransitionController@complete` (1 scenario)
- `test(api): POST /api/appointments/{id}/transitions/no-show returns 200 with state=no_show` + `feat(api): AppointmentTransitionController@markNoShow` (1 scenario)
- `test(api): POST /api/auth/logout returns 204 and revokes the token` + `feat(api): AuthController@logout` (1 scenario)

**Structural commits**:

- `chore(test): add test fixture helper CreatesDoctors in tests/Support/`
- `docs(readme): document the REST API + curl examples + Sanctum token flow`

**Expected log shape** (subset):

```
<hash> docs(readme): document REST API + curl + Sanctum flow
<hash> test(api): confirm + complete + no-show + logout scenarios (red)
<hash> feat(api): 3 transition endpoints + logout (green)
<hash> test(api): audit log admin/doctor/patient (red)
<hash> feat(api): AuditLogController + AuditLogResource (green)
<hash> test(api): prescriptions paginated + role-scoped (red)
<hash> feat(api): PrescriptionController + PrescriptionResource (green)
... (~24 commits total for PR 3)
```

**LOC**: ~330-380 hand-written (~6 controllers, 6 FormRequests, 6 Resources, ~14 test files with 2-3 scenarios each, 1 helper trait, 1 docs update).

**Total across the 3 PRs**: ~1,065 LOC hand-written (fits three review slices at the 400-line budget with some headroom).

## 10. Cross-cutting concerns (deferred, all Low)

These items are intentionally out of scope. They are documented here so the future change that owns each one has a clear starting point.

**CORS** — deferred to the patient-web change. Recommended config when it lands:

```
// config/cors.php
'paths' => ['api/*'],
'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
'allowed_origins' => ['https://app.med-connect.test', 'https://patient.med-connect.test'],
'allowed_headers' => ['Content-Type', 'Authorization', 'X-Timezone'],
'supports_credentials' => false,  // bearer-only for v1
```

**API versioning** — deferred. When v2 is needed, introduce `/api/v1/...` for the new contract and keep `/api/...` as a deprecated alias for 6 months. The route group is built with `prefix('api')` precisely so the `v1` prefix can be added with a one-line change in `routes/api.php`.

**OpenAPI / Swagger** — deferred. When it lands, recommended library: `darkaonline/l5-swagger` (mature) or `scribe` (better FormRequest autodiscovery). The Eloquent API Resources' `toArray()` methods will be the source of truth for the response shape; the FormRequests will be the source of truth for the request shape.

**SANCTUM_STATEFUL_DOMAINS** — not configured in v1. Bearer-only authentication is sufficient. When the patient-web change ships its first-party browser client, this env var needs to be set (and CORS — see above). N3.

**Rate limiting** — Sanctum's defaults only. When public clients start hitting the API, add `throttle:60,1` per IP to the `api` middleware group in `bootstrap/app.php`.

**Idempotency keys** — not in v1. Clients retrying on network failure may produce a second `409`; client-side de-duplication via `Idempotency-Key` is deferred to v2.

## 11. Known caveats & risks

| # | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| N1 | Empty `withExceptions` returns 500 HTML for every domain failure if P1 is botched | Med | High (UX broken) | P1 lands `ErrorResponse::fromException()` + `ExceptionMappingTest` (10 scenarios) as the FIRST test in P1. The red test must fail before the green handler ships. Highest-priority deliverable. |
| N2 | HTTP race surface doubles (mobile + web + multi-tab) beyond the action-level race | Med | High (data integrity if missed) | P2 adds `ConcurrentDoubleBookHttpTest` (MariaDB-only) mirroring `tests/Feature/Agenda/ConcurrentDoubleBookTest.php`. The action-level test stays as the persistence-contract safety net. |
| N3 | `SANCTUM_STATEFUL_DOMAINS` not configured for the future patient-web | Low | Low (no first-party browser client yet) | Documented in §10. Bearer-only is sufficient for v1. |
| N6 | 400-line budget pressure across ~1,065 LOC of new work | Med | Med (reviewer fatigue) | 3-PR chained split (P1 ~330-380, P2 ~330-380, P3 ~330-380). Per-PR `ask-always` confirm before the next launches. |
| N7 | `ResolveTimezone` middleware must run before `auth:sanctum` so the 401 response is in the requested TZ | Low | Low (cosmetic) | Mount order is `[ResolveTimezone, 'auth:sanctum']` in `routes/api.php`. Documented in the middleware class docblock and §7. The middleware does not require an authenticated user. |
| N8 | Sanctum's `HasApiTokens` token creation is the only token issuance path; test fixtures must use `->createToken('name')` | Low | Low (test consistency) | `CreatesPatients` trait encapsulates the call. Documented in the trait docblock and in the P1 test. |
| N9 | `LengthAwarePaginator` JSON envelope shape (`{data, links, meta}`) must match the spec scenario | Low | Low (test-only) | The Laravel default shape is exactly `{data, links:{first,last,prev,next}, meta:{current_page,from,last_page,path,per_page,to,total}}`. The pagination tests assert on the keys directly. |
| R1 | 8 untested scenarios in `clinical-records` + `prescriptions` (W1-W2, inherited from `agenda-core`) | Low | Low (deferred tests) | `agenda-http` exposes GET-only for these; no writes. Deferred tests stay deferred. |
| R2 | Action-level `ConcurrentDoubleBookTest` is the persistence-contract safety net | Low | Low | Action test stays; HTTP test added (N2). The two tests are complementary, not redundant. |
| R3 | Doctor panel needs `discoverResources()` (inherited from `agenda-core`) | Low | Low (no Filament code in `agenda-http`) | Not affected by this change. The doctor panel concern stays in Filament-land; this change is purely transport. |

**New in this design (not in the proposal)**:

- **N7, N8, N9** are design-time observations that the user prompt surfaced. They are all Low and all have concrete mitigations baked into the design above.

**Inherited from `agenda-core`** (carry-over, status unchanged):

- **R1, R2, R3** are tracked in the archived `agenda-core/design.md` §"Risks & Mitigations". None of them are worsened by this change; N2 *adds* to the action-level race with an HTTP-level test.

## 12. Open questions (hints for `sdd-tasks`, not blocking)

These are not blockers for this design. They are decisions that `sdd-tasks` or a future change may want to revisit.

- **Order of `tests/Feature/Api/` files** — alphabetical (predictable, scales) or grouped by endpoint path (easier to find, breaks as the surface grows). Recommend **alphabetical** for v1 to match the existing `tests/Feature/Agenda/` convention.
- **Should we add a `GET /api/routes` debug endpoint** that lists the registered `/api/*` routes? Handly for debugging and for the future mobile app. **Defer to a future change**; `php artisan route:list --path=api` is sufficient for now.
- **Should we add `GET /api/version` returning the app version** (from `composer.json`)? Useful for client cache-busting. **Defer to a future change**; not in v1.
- **Should Sanctum tokens have an expiry**? The current default is "no expiry" (forever, until explicitly revoked). Sanctum supports `expires_at` on token creation. **Defer to a future security-hardening change**; v1 is long-lived bearer tokens with explicit `tokens()->delete()` revocation.
- **Pagination URL base** — Laravel's `LengthAwarePaginator` uses the current request URL as the base for `links.first/last/prev/next`. If a future change adds route-model binding to list endpoints, the base will include the implicit query string. **No action needed**; just worth knowing.
- **Timezone in error details** — should `SLOT_NOT_AVAILABLE` `error.details.start_time` always be in the resolved TZ, or always in UTC? **Spec says resolved TZ** (matches the request). Documented in §7 but worth re-confirming when the `BookAppointmentFailureTest` migration to HTTP is done in P2.
- **`?mine=true` query param on list endpoints** — the proposal mentioned it as a convenience; the spec does not lock it. **Recommendation**: add it in P3 alongside the list endpoints, with the same role-scoping semantics (patients see only their own; `?mine=true` is implicit for patients; explicit for doctors who want to filter "my appointments as a doctor"). **Trivial change** if the user wants it; defer to P3 `sdd-apply` if not.
