# Tasks: agenda-http

> Chain strategy: `stacked-to-main`. Each PR branch (e.g. `feat/agenda-http-auth`) cuts off `main` after the previous PR merged. No tracker branch.
> Review budget: **400 lines / PR**.
> TDD required in PRs 1, 2, and 3 — every `feat` task in this change is preceded by a `test` commit that fails. `sdd-verify` greps `git log` for the red→green pair pattern (the `bfe7931 → ddecce5` pair from agenda-core PR 5 is the canonical reference).
> No DB migrations. `agenda-http` is transport-only. The schema is locked at `6ba4d6a` (agenda-core). `migrate:fresh --seed` must remain green.
> Strict TDD annotations: `[RED]` = test-first (must fail), `[GREEN]` = impl-after (must pass), `[STRUCTURAL]` = scaffolding with no test gate. Two exceptions to the strict pattern are documented inline at `T-API-3` (unit-test-alongside-impl, pure value object) and `T-API-14` (race test asserts emergent behavior, no green impl needed). `T-API-7` ships red+green in a single commit because the route registration is trivial and the test verifies both halves.
> Filament assets are unchanged in this change. `npm run build` is a sanity check, not a gate.
> Test runner: `vendor/bin/pest` (Pest 4.7.1, Pest convention; `uses(...)` for fixtures).

---

## Review Workload Forecast

| Field | Value |
|---|---|
| Estimated changed lines (total) | ~1,065 LOC hand-written (controllers + FormRequests + Resources + middleware + ErrorResponse + tests + 1 config + 1 routes file) |
| 400-line budget risk (total) | **Medium** — each PR fits the 400-line cap; the risk is the aggregate scope |
| Chained PRs recommended | **Yes** — 3-PR chain is locked in the design §9 |
| Suggested split | PR 1 (auth + exception handler + TZ middleware) → PR 2 (book + cancel + race) → PR 3 (reads + transitions + directory) |
| Delivery strategy | `ask-always` (per `openspec/AGENTS.md` preflight) |
| Chain strategy | `stacked-to-main` (locked in proposal + design) |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: Medium

### Per-PR forecast

| PR | Title | Est. LOC (hand-written) | 400-line budget? | Red / Green / Structural commits |
|---|---|---|---|---|
| PR 1 | Auth + Exception Handler + TZ Middleware | 330–380 | **Yes** | 3 red / 2 green / 2 structural / 1 verify = 8 commits |
| PR 2 | Mutations + Race Test | 330–380 | **Yes** | 2 red / 2 green / 1 race-verify / 1 structural / 1 verify = 7 commits |
| PR 3 | Reads + State Transitions + Directory | 330–380 | **Yes** | ~12 red / ~12 green / 1 structural (docs) / 1 verify = ~24 commits |

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|---|---|---|---|
| 1 | JSON transport skeleton: error envelope + TZ middleware + Sanctum auth + 3 auth endpoints | PR 1 | base: `main`; tests + middleware + config; no domain coupling |
| 2 | Mutations reachable: `POST /api/appointments`, `DELETE /api/appointments/{id}`, the HTTP race test | PR 2 | base: `main` after PR 1 merges; depends on PR 1's `withExceptions` handler |
| 3 | Full public surface: 14 read endpoints + 3 transition endpoints + 1 logout | PR 3 | base: `main` after PR 2 merges; depends on PR 1 (handler) + PR 2 (auth + scopes) |

**`ask-always` triggers**: per-PR LOC, middleware-order contract, race test driver-aware skipping. The orchestrator MUST halt and ask the user one of the standard chain-strategy confirmations (stack-as-is, split-into-2-PER-feature, or `size:exception`) before each PR launches.

---

## PR 1 — Auth + Exception Handler + TZ Middleware

> **TDD mandatory.** The first commit is the red test for the error envelope (REQ-API-3 + REQ-API-4). The green commit for the exception handler MUST NOT land until that test is committed and observed to fail. The order of red→green pairs is enforced by `sdd-verify`.

- [x] **T-API-1 [STRUCTURAL]** — `chore(routes): create routes/api.php and register it in bootstrap/app.php`
  - Files: `routes/api.php` (NEW, ~10 LOC empty shell returning a 200 stub), `bootstrap/app.php` (MOD, add `->withRouting(api: __DIR__.'/../routes/api.php', apiPrefix: 'api')` inside the existing `Application::configure()` chain)
  - Commit: `chore(routes): create routes/api.php + register in bootstrap/app.php`
  - Test gate: `php artisan route:list` shows the new file loaded with no `/api/*` routes yet; the only `/api*` line is the auto-mounted `Closure` placeholder

- [x] **T-API-2 [STRUCTURAL]** — `chore(config): add config/clinic.php with timezone config`
  - Files: `config/clinic.php` (NEW, ~15 LOC: `return ['timezone' => env('CLINIC_TIMEZONE', 'America/Argentina/Buenos_Aires')];`), `.env.example` (MOD, add `CLINIC_TIMEZONE=America/Argentina/Buenos_Aires`)
  - Commit: `chore(config): add clinic.timezone config + CLINIC_TIMEZONE env`
  - Test gate: `php artisan tinker --execute="echo config('clinic.timezone');"` returns `America/Argentina/Buenos_Aires`

- [x] **T-API-3 [STRUCTURAL with unit tests alongside]** — `feat(clinic): add App\Clinic\Timezone value object`
  - Files: `app/Clinic/Timezone.php` (NEW, ~50 LOC: `final readonly class Timezone` with constructor + `static from(string): self` validating via `timezone_identifiers_list()` and throwing `InvalidTimezoneException` on miss + `toLocal(CarbonInterface $utc): CarbonImmutable` + `toUtc(CarbonInterface $local): CarbonImmutable` + `format(CarbonInterface $utc): string` returning ISO 8601 with offset + `static isValid(string): bool` helper), `app/Exceptions/InvalidTimezoneException.php` (NEW, ~10 LOC: `extends RuntimeException`, carries the rejected timezone string via `getRejectedName()`), `tests/Unit/Clinic/TimezoneTest.php` (NEW, ~80 LOC, 5 tests: `from_happy_path_returns_Timezone_instance`; `from_with_invalid_tz_throws_InvalidTimezoneException`; `toLocal_converts_UTC_to_named_zone_with_correct_offset`; `toUtc_converts_named_zone_to_UTC`; `isValid_returns_true_for_valid_names_and_false_otherwise`)
  - Commit: `feat(clinic): add Timezone value object + unit tests + InvalidTimezoneException`
  - **TDD exception documented**: this is a "test alongside impl" (not strict red→green) because the value object is pure logic and unit-testable. The test file is committed in the same commit as the impl, the value object's correctness is verified by the unit tests, and the integration surface (FormRequest + API Resource) is tested at the HTTP layer in T-API-7+ where strict TDD applies. The convention from `agenda-core` PR 4 (`DoctorAvailabilityService` + its unit test in one commit) is the precedent.
  - Test gate: `vendor/bin/pest tests/Unit/Clinic/TimezoneTest.php` → 5 passed
  - Dependency: T-API-2 (config exists for the `Timezone` to read from in the integration tests)

- [x] **T-API-4 [STRUCTURAL]** — `feat(middleware): add ResolveTimezone middleware`
  - Files: `app/Http/Middleware/ResolveTimezone.php` (NEW, ~30 LOC: `handle(Request $request, Closure $next)` reads `$request->query('tz')`, on non-empty validates via `Timezone::isValid()` — on invalid throws `InvalidTimezoneException`; on empty falls back to `config('clinic.timezone')`; sets `$request->attributes->set('tz', new Timezone($name))`; calls `$next($request)`. Class docblock states: "MUST run before `auth:sanctum` so the 401 response is also rendered in the requested TZ (N7).")
  - Commit: `feat(middleware): add ResolveTimezone middleware (runs before auth:sanctum)`
  - **No standalone test for the middleware** at this commit. The integration test in T-API-7 covers it. The reason: the middleware's behaviour is "read query, set attribute, optionally throw" — too thin to test in isolation, and `Timezone` (T-API-3) already covers the validation logic. Convention from agenda-core PR 3 (middleware-like wiring shipped without its own test) is the precedent.
  - Test gate: `php -r "require 'vendor/autoload.php'; echo class_exists('App\\Http\\Middleware\\ResolveTimezone');"`

- [x] **T-API-5 [RED]** — `test(api): pin the standard error envelope and 6 domain exception → HTTP mappings`
  - Files: `tests/Feature/Api/ExceptionMappingTest.php` (NEW, ~150 LOC, 10 scenarios). The test registers a throwaway route inside the test (`Route::get('/_test/throw/{type}', fn (string $type) => throw $exceptionMap[$type]())` guarded by `Sanctum::actingAs($user)` so the throw runs through the full middleware chain including the exception handler). The 10 scenarios, each is a separate `test(...)` method:
    1. `SlotNotAvailableException` → 409 + `error.code = 'SLOT_NOT_AVAILABLE'`
    2. `AnticipationWindowViolationException` → 422 + `'ANTICIPATION_WINDOW_VIOLATION'`
    3. `PatientOverlapException` → 422 + `'PATIENT_OVERLAP'`
    4. `UnauthorizedActorException` → 403 + `'UNAUTHORIZED_ACTOR'`
    5. `CancellationWindowViolationException` → 422 + `'CANCELLATION_WINDOW_VIOLATION'`
    6. `InvalidStateTransitionException` → 422 + `'INVALID_STATE_TRANSITION'`
    7. `AuthorizationException` (Laravel) → 403 + `'FORBIDDEN'`
    8. `ModelNotFoundException` → 404 + `'NOT_FOUND'`
    9. `ValidationException` → 422 + `'VALIDATION_ERROR'` + `details` keyed by field
    10. Any other exception → 500 + `'INTERNAL_ERROR'`
  - Commit: `test(api): red — standard error envelope + 10 exception mappings (fails)`
  - **MUST FAIL** at this commit. The framework returns HTML 500 for unhandled exceptions; none of the 10 assertions pass. `vendor/bin/pest tests/Feature/Api/ExceptionMappingTest.php` exits non-zero.
  - Test gate: `vendor/bin/pest --filter=ExceptionMappingTest` exits non-zero on this commit only
  - Dependency: T-API-1 (route file registered so the test route can be mounted), T-API-3 (`InvalidTimezoneException` is part of the test map if we choose to cover it; in P1 we cover the 10 listed above and add the TZ one in T-API-7)

- [x] **T-API-6 [GREEN]** — `feat(api): add the withExceptions handler + ErrorResponse helper + auth:sanctum on /api/*`
  - Files: `bootstrap/app.php` (MOD, add the `->withExceptions(function (Exceptions $exceptions) { $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) { if (! $request->is('api/*')) return null; return \App\Http\Responses\Api\ErrorResponse::fromException($e, $request); }); })` block, ~25 LOC), `app/Http/Responses/Api/ErrorResponse.php` (NEW, ~70 LOC: `static fromException(\Throwable $e, Request $request): JsonResponse` holds a `match()` on exception class — domain exceptions are matched by `instanceof DomainException` and use `httpStatus()` as the canonical source; the `code` is derived from the short class name uppercased to snake_case; Laravel exceptions are matched by exact class; the unmapped case returns 500 with `INTERNAL_ERROR` and redacts the message in non-local environments), `routes/api.php` (MOD, replace the stub with the route group: `Route::middleware([ResolveTimezone::class, 'auth:sanctum'])->prefix('api')->group(function () { Route::get('/me', fn () => auth()->user()); });` + the 3 auth routes registered outside the group)
  - Commit: `feat(api): withExceptions handler + ErrorResponse helper + auth:sanctum on /api/* (green)`
  - **MUST PASS** at this commit. All 10 `ExceptionMappingTest` scenarios pass.
  - Test gate: `vendor/bin/pest --filter=ExceptionMappingTest` exits 0
  - Dependency: T-API-5 (the red test must be committed first)

- [x] **T-API-7 [RED+GREEN together]** — `test(api)+feat(api): Sanctum auth + ResolveTimezone integration on /api/me`
  - Files: `tests/Feature/Api/AuthSanctumTest.php` (NEW, ~50 LOC, 3 scenarios: GIVEN no `Authorization` header → 401 + `error.code = 'UNAUTHENTICATED'`; GIVEN a valid Sanctum token via `actingAs($user, 'sanctum')` → 200 + `data.id = $user->id`; GIVEN `?tz=America/New_York` → 200 + assertion on the resolved TZ being honoured by the next test in T-API-7.5 if needed, or simple smoke that the middleware does not throw), `routes/api.php` (MOD, confirm the `Route::middleware([ResolveTimezone::class, 'auth:sanctum'])->prefix('api')->group(...)` is in place; this task adds no new routes beyond the `/api/me` already in T-API-6)
  - Commit: `test(api)+feat(api): sanctum auth + ResolveTimezone integration on /api/me`
  - **TDD exception documented**: the red and green are committed together because the route registration is trivial (one middleware group + one route closure) and the test verifies both halves. Splitting the commit would be artificial. Convention from agenda-core PR 3 (`$user->isAdmin()` predicate + its test in one commit) is the precedent.
  - Test gate: `vendor/bin/pest --filter=AuthSanctumTest` exits 0 on this commit
  - Dependency: T-API-6 (exception handler in place; without it, the 401 from missing token would render as HTML 500)

- [x] **T-API-8 [VERIFICATION]** — `chore(test): verify PR 1 test suite + migrate:fresh --seed + route:list gates on MariaDB`
  - Files: none
  - Commit: `chore(test): verify PR 1 test suite + migrate:fresh --seed + route:list gates on MariaDB`
  - The PR 1 acceptance gate (all must pass before merge):
    1. `vendor/bin/pest` (SQLite in-memory) → all tests green, no new skipped
    2. `DB_CONNECTION=mariadb php artisan migrate:fresh --env=testing` → exits 0
    3. `DB_CONNECTION=mariadb vendor/bin/pest` → all tests green
    4. `php artisan route:list --path=api` → shows the registered routes (the `/api/me` placeholder at minimum)
    5. `php artisan tinker --execute="echo config('clinic.timezone');"` → returns `America/Argentina/Buenos_Aires`
    6. `git log --oneline -8` shows the expected commit shape (see below)
  - Test gate: all 6 above pass
  - Dependency: T-API-7

### PR 1 expected commit log (bottom-to-top)

```
<hash> chore(test): verify PR 1 test suite + migrate:fresh --seed + route:list gates on MariaDB
<hash> test(api)+feat(api): sanctum auth + ResolveTimezone integration on /api/me
<hash> feat(api): withExceptions handler + ErrorResponse helper + auth:sanctum on /api/* (green)
<hash> test(api): red — standard error envelope + 10 exception mappings (fails)
<hash> feat(middleware): add ResolveTimezone middleware (runs before auth:sanctum)
<hash> feat(clinic): add Timezone value object + unit tests + InvalidTimezoneException
<hash> chore(config): add clinic.timezone config + CLINIC_TIMEZONE env
<hash> chore(routes): create routes/api.php + register in bootstrap/app.php
```

### PR 1 verification (must all pass)

- `vendor/bin/pest` → 0 failures, 0 errors, 5 new tests (Timezone unit) + 10 new tests (ExceptionMapping) + 3 new tests (AuthSanctum) = **18 new tests, all green**
- `DB_CONNECTION=mariadb vendor/bin/pest` → same
- `php artisan migrate:fresh --env=testing` → exits 0 (no schema changes)
- `php artisan route:list --path=api` → shows the registered routes
- `php artisan tinker --execute="echo config('clinic.timezone');"` → `America/Argentina/Buenos_Aires`

### PR 1 known risks to verify

- **N1** (Med, High impact): empty `withExceptions` returns 500 HTML for every domain failure. **Verified** by the 10-scenario `ExceptionMappingTest`; the red commit lands first, the green commit second, the order is enforced by `sdd-verify` via `git log`.
- **N7** (Low): `ResolveTimezone` must run before `auth:sanctum` so the 401 response is in the requested TZ. **Verified** by the route group `Route::middleware([ResolveTimezone::class, 'auth:sanctum'])` order + the middleware class docblock.

---

## PR 2 — Mutations + Race Test

> **TDD mandatory.** Two red commits, two green commits, one race test (no green impl needed — it asserts emergent behavior from T-API-5's handler + the unique index in `appointments`), one fixture trait, one verify.

- [x] **T-API-9 [STRUCTURAL]** — `chore(test): add test fixture helper CreatesPatients`
  - Files: `tests/Support/CreatesPatients.php` (NEW, ~30 LOC: Pest trait with `createPatientUser(): array{user, patient, token}` method that `User::factory()->create(['role' => 'patient'])` + creates the `Patient` profile with a unique `dni` + `MedicalHistory` (via `EnsureMedicalHistoryAction` for parity with the action layer) + `->createToken('test')` returning the plaintext + the `User`. Method returns the array so the caller can destructure. Class docblock states: "Wraps `User::createToken()` (Sanctum N8). Use `$user->actingAs($user, 'sanctum')` for `$this->getJson/postJson/...` calls.")
  - Commit: `chore(test): add CreatesPatients fixture trait`
  - Test gate: a trivial `it('creates a patient with token', function () { $f = app(\Tests\Support\CreatesPatients::class); $r = $f->createPatientUser(); expect($r['token'])->not->toBeEmpty(); })->uses(CreatesPatients::class);` in the same file — minimal smoke; not a real feature test
  - Dependency: T-API-8

- [x] **T-API-10 [RED]** — `test(api): book an appointment via POST /api/appointments returns 201 with the JSON shape`
  - Files: `tests/Feature/Api/BookAppointmentTest.php` (NEW, ~200 LOC, 4 scenarios: `it_returns_201_with_appointment_resource_on_happy_path`; `it_returns_422_VALIDATION_ERROR_when_doctor_id_missing`; `it_returns_409_SLOT_NOT_AVAILABLE_when_slot_already_taken`; `it_returns_422_ANTICIPATION_WINDOW_VIOLATION_when_start_within_2h`). Uses `CreatesPatients` + a doctor + published schedule from the existing factories. The test asserts the `AppointmentResource` shape via `assertJsonPath('data.id', ...)` + `assertJsonPath('data.state', 'pending')` + ISO 8601 `start_time` in the resolved TZ.
  - Commit: `test(api): red — book appointment returns 201 (fails; route not registered)`
  - **MUST FAIL** at this commit. The `POST /api/appointments` route does not exist yet, so the test gets a 404 (or 500 from the handler if `NotFoundHttpException` is unmapped — but T-API-6 mapped it to 404 with `ROUTE_NOT_FOUND`). All 4 scenarios fail with the wrong status.
  - Test gate: `vendor/bin/pest --filter=BookAppointmentTest` exits non-zero on this commit only
  - Dependency: T-API-9 (the patient fixture is needed to act-as)

- [x] **T-API-11 [GREEN]** — `feat(api): add AppointmentController@store + BookAppointmentRequest + AppointmentResource + the route`
  - Files: `app/Http/Controllers/Api/AppointmentController.php` (NEW, ~50 LOC: `__construct(private BookAppointmentAction $action) {}` + `public function store(BookAppointmentRequest $request): AppointmentResource` that calls `$this->action->__invoke($request->validated('doctor_id'), $tz->toUtc(CarbonImmutable::parse($request->validated('start_time'))), $request->user()->patient->id, $request->validated('notes'))` and returns `new AppointmentResource($appointment)` with `response()->setStatusCode(201)`), `app/Http/Requests/Api/BookAppointmentRequest.php` (NEW, ~30 LOC: `authorize` returns `$user && $user->isPatient()`; `rules` is `['doctor_id' => 'required|exists:doctors,id', 'start_time' => 'required|date|after:+2 hours', 'notes' => 'nullable|string|max:1000']`), `app/Http/Resources/Api/AppointmentResource.php` (NEW, ~40 LOC: `toArray($request)` returns `['id' => $this->id, 'state' => $this->state->getName(), 'start_time' => $request->attributes->get('tz')->format($this->start_time), 'end_time' => $request->attributes->get('tz')->format($this->end_time), 'notes' => $this->notes, 'cancellation_reason' => $this->cancellation_reason, 'doctor' => ['id' => $this->doctor->id, 'name' => $this->doctor->user->name], 'patient' => ['id' => $this->patient->id, 'name' => $this->patient->user->name]]`), `routes/api.php` (MOD, add `Route::post('/appointments', [AppointmentController::class, 'store'])` inside the group)
  - Commit: `feat(api): AppointmentController@store + BookAppointmentRequest + AppointmentResource (green)`
  - **MUST PASS** at this commit. All 4 `BookAppointmentTest` scenarios pass. The slot-taken scenario depends on the action-layer race contract from agenda-core (the unique index catches the duplicate); the test inserts a pre-existing appointment via the factory, then asserts the second `POST` returns 409 with `error.code = 'SLOT_NOT_AVAILABLE'`.
  - Test gate: `vendor/bin/pest --filter=BookAppointmentTest` exits 0
  - Dependency: T-API-10

- [x] **T-API-12 [RED]** — `test(api): cancel an appointment via DELETE /api/appointments/{id} returns 200 with the updated state`
  - Files: `tests/Feature/Api/CancelAppointmentTest.php` (NEW, ~150 LOC, 3 scenarios: `it_returns_200_with_state_cancelled_when_patient_cancels_within_24h`; `it_returns_422_CANCELLATION_WINDOW_VIOLATION_when_patient_cancels_within_24h`; `it_returns_200_with_state_cancelled_when_doctor_cancels_anytime`). Uses `CreatesPatients` + a `CreatesDoctors` inline equivalent (defined in this file for PR 2; the shared trait comes in PR 3) + a `pending` appointment at `now + 48h` for the happy case + a `pending` appointment at `now + 12h` for the window case.
  - Commit: `test(api): red — cancel appointment returns 200 (fails; route not registered)`
  - **MUST FAIL** at this commit. The `DELETE /api/appointments/{id}` route does not exist yet.
  - Test gate: `vendor/bin/pest --filter=CancelAppointmentTest` exits non-zero on this commit only
  - Dependency: T-API-10 (the booked appointment from the green test could be reused; the test fixtures are independent)

- [x] **T-API-13 [GREEN]** — `feat(api): add AppointmentController@destroy + CancelAppointmentRequest + the route`
  - Files: `app/Http/Controllers/Api/AppointmentController.php` (MOD, add `public function destroy(Appointment $appointment, CancelAppointmentRequest $request): AppointmentResource` that authorizes via `$this->authorize('cancel', $appointment)` + calls `app(CancelAppointmentAction::class)(...)` and returns the resource), `app/Http/Requests/Api/CancelAppointmentRequest.php` (NEW, ~15 LOC: `rules` is `['reason' => 'required|string|max:1000']`; `authorize` delegates to the model's policy), `routes/api.php` (MOD, add `Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])` inside the group)
  - Commit: `feat(api): AppointmentController@destroy + CancelAppointmentRequest (green)`
  - **MUST PASS** at this commit. All 3 `CancelAppointmentTest` scenarios pass.
  - Test gate: `vendor/bin/pest --filter=CancelAppointmentTest` exits 0
  - Dependency: T-API-12

- [x] **T-API-14 [RACE-VERIFY, red in the sense of asserting behavior not yet covered by other tests]** — `test(api): two concurrent POST /api/appointments for the same slot return 201 + 409 (MariaDB-only)`
  - Files: `tests/Feature/Api/ConcurrentDoubleBookHttpTest.php` (NEW, ~80 LOC, 1 scenario). The test:
    1. Skips on SQLite via `$this->markTestSkipped('HTTP race requires MariaDB or PostgreSQL')` when `config('database.default') === 'sqlite'`. Pattern mirrored from `tests/Feature/Agenda/ConcurrentDoubleBookTest.php`.
    2. Creates a doctor + a published schedule + a target slot at `now + 3h`.
    3. Uses `DB::transaction(...)` × 2 wrapping `app()->handle($request)` calls (the in-process kernel; mirrors how the action-level test works on MariaDB).
    4. Asserts: one response is `201` with `data.id` set; the other is `409` with `error.code = 'SLOT_NOT_AVAILABLE'` and `error.details.conflicting_appointment_id` matching the winner's id.
    5. Asserts: the `appointments` table contains exactly one non-cancelled row for `(doctor_id, start_time)`.
  - Commit: `test(api): pin the HTTP-layer race contract (ConcurrentDoubleBookHttpTest, MariaDB-only)`
  - **TDD exception documented**: this test is a "red" in the sense that it asserts behavior not yet covered by other tests, but the "green" is "no code change needed" — the persistence contract (the unique index from `agenda-core` PR 2) and the exception handler (T-API-6) are already in place. The test is a single commit that asserts emergent behavior from the already-tested components. This is an acceptable exception when (1) the components are individually tested, (2) the integration is non-trivial, and (3) the test guards a real-world failure mode (concurrent double-book from multi-tab or multi-device). The action-level `ConcurrentDoubleBookTest` (agenda-core PR 4) is the precedent.
  - Test gate: with `DB_CONNECTION=mariadb`, `vendor/bin/pest --filter=ConcurrentDoubleBookHttpTest` exits 0; with `DB_CONNECTION=sqlite`, the test is reported as skipped (not failed)
  - Dependency: T-API-11 (the `POST /api/appointments` route must be registered)

- [x] **T-API-15 [VERIFICATION]** — `chore(test): verify PR 2 test suite + migrate:fresh --seed + route:list gates on MariaDB`
  - Files: none
  - Commit: `chore(test): verify PR 2 test suite + migrate:fresh --seed + route:list gates on MariaDB`
  - The PR 2 acceptance gate (all must pass before merge):
    1. `vendor/bin/pest` (SQLite in-memory) → all tests green, 0 new skipped, no driver-aware tests regressed
    2. `DB_CONNECTION=mariadb php artisan migrate:fresh --env=testing` → exits 0
    3. `DB_CONNECTION=mariadb vendor/bin/pest` → all tests green; `ConcurrentDoubleBookHttpTest` is the only test that changes status (skipped → green) on MariaDB
    4. `php artisan route:list --path=api` → shows the `POST /api/appointments` and `DELETE /api/appointments/{appointment}` routes
    5. A quick `php artisan tinker` smoke: `app(\App\Actions\BookAppointmentAction::class)` resolves; `app(\App\Http\Resources\Api\AppointmentResource::class)` resolves
    6. `git log --oneline -7` shows the expected commit shape (see below)
  - Test gate: all 6 above pass
  - Dependency: T-API-14

### PR 2 expected commit log (bottom-to-top)

```
<hash> chore(test): verify PR 2 test suite + migrate:fresh --seed + route:list gates on MariaDB
<hash> test(api): pin the HTTP-layer race contract (ConcurrentDoubleBookHttpTest, MariaDB-only)
<hash> feat(api): AppointmentController@destroy + CancelAppointmentRequest (green)
<hash> test(api): red — cancel appointment returns 200 (fails; route not registered)
<hash> feat(api): AppointmentController@store + BookAppointmentRequest + AppointmentResource (green)
<hash> test(api): red — book appointment returns 201 (fails; route not registered)
<hash> chore(test): add CreatesPatients fixture trait
```

### PR 2 verification (must all pass)

- `vendor/bin/pest` → 0 failures, 0 errors, **7 new tests** (4 Book + 3 Cancel) + 1 race test (skipped on SQLite)
- `DB_CONNECTION=mariadb vendor/bin/pest` → same + race test is green
- `php artisan migrate:fresh --env=testing` → exits 0
- `php artisan route:list --path=api` → shows the 2 new mutation routes
- The `ConcurrentDoubleBookHttpTest` reports as `skipped` on SQLite (not failed) and as `passed` on MariaDB

### PR 2 known risks to verify

- **N2** (Med, High impact): HTTP race surface doubles. **Verified** by `ConcurrentDoubleBookHttpTest` on MariaDB; the test asserts the deterministic 201+409 pair and the `conflicting_appointment_id` in the error details.
- **N8** (Low): Sanctum `HasApiTokens` token creation in test fixtures. **Verified** by `CreatesPatients::createPatientUser()` returning a real plaintext token; the trait is the only token issuance path in the test suite.
- **R2** (Low, inherited from agenda-core): the action-level `ConcurrentDoubleBookTest` is the persistence safety net. **NOT removed** — the HTTP test (N2) is added on top. The two tests are complementary.

---

## PR 3 — Reads + State Transitions + Directory + Audit/Clinical Reads

> **TDD mandatory.** 12 red→green pairs (one per endpoint or endpoint group) + 1 docs commit + 1 verify. PR 3 is the largest of the three (~24 commits, ~330-380 LOC hand-written). Strict TDD applies; every `feat` task in this PR is preceded by a `test` commit that fails.

### Sub-group 3A: Reads (8 endpoints, 12 commits, ~150-200 LOC)

- [ ] **T-API-16 [RED]** — `test(api): GET /api/appointments returns paginated list scoped by role`
  - Files: `tests/Feature/Api/ListAppointmentsTest.php` (NEW, ~100 LOC, 4 scenarios: `it_returns_paginated_list_for_patient_scoped_to_own`; `it_returns_paginated_list_for_doctor_scoped_to_own`; `it_returns_paginated_list_for_admin_unscoped`; `it_returns_pagination_envelope_data_meta_links_with_default_per_page_20`). Asserts the `LengthAwarePaginator` shape from REQ-API-6: `data`, `meta.current_page`, `meta.last_page`, `meta.per_page`, `meta.total`, `links.first`, `links.last`, `links.prev`, `links.next`. Patient fixture creates 3 appointments; doctor fixture creates 3 more; admin sees all 6.
  - Commit: `test(api): red — list appointments paginated + role-scoped (fails; route not registered)`
  - **MUST FAIL** at this commit. Route returns 404.
  - Test gate: `vendor/bin/pest --filter=ListAppointmentsTest` exits non-zero on this commit only
  - Dependency: T-API-15

- [ ] **T-API-17 [GREEN]** — `feat(api): add AppointmentController@index + ListAppointmentsRequest + the route`
  - Files: `app/Http/Controllers/Api/AppointmentController.php` (MOD, add `public function index(ListAppointmentsRequest $request): AnonymousResourceCollection` that builds a query scoped by role: `if ($user->isPatient()) $query->where('patient_id', $user->patient->id);` `elseif ($user->isDoctor()) $query->where('doctor_id', $user->doctor->id);` `// admin: no scope`; applies `from`/`to`/`doctor_id`/`patient_id`/`state` filters; returns `AppointmentResource::collection($query->paginate($request->integer('per_page', 20)))`), `app/Http/Requests/Api/ListAppointmentsRequest.php` (NEW, ~20 LOC: rules per design §3 `ListAppointmentsRequest`), `routes/api.php` (MOD, add `Route::get('/appointments', [AppointmentController::class, 'index'])`)
  - Commit: `feat(api): AppointmentController@index + ListAppointmentsRequest (green)`
  - Test gate: `vendor/bin/pest --filter=ListAppointmentsTest` exits 0
  - Dependency: T-API-16

- [ ] **T-API-18 [RED]** — `test(api): GET /api/appointments/{id} returns detail with 403 for non-owner`
  - Files: `tests/Feature/Api/ShowAppointmentTest.php` (NEW, ~60 LOC, 2 scenarios: `it_returns_200_with_resource_for_authorized_actor`; `it_returns_403_FORBIDDEN_for_non_owner_patient`). The 403 scenario uses two patients; the second one calls the endpoint on the first one's appointment and asserts `error.code = 'FORBIDDEN'`.
  - Commit: `test(api): red — show appointment detail + 403 for non-owner (fails; route not registered)`
  - **MUST FAIL** at this commit. Route returns 404.
  - Test gate: `vendor/bin/pest --filter=ShowAppointmentTest` exits non-zero on this commit only
  - Dependency: T-API-15

- [ ] **T-API-19 [GREEN]** — `feat(api): add AppointmentController@show`
  - Files: `app/Http/Controllers/Api/AppointmentController.php` (MOD, add `public function show(Appointment $appointment): AppointmentResource` that authorizes via `$this->authorize('view', $appointment)` and returns `new AppointmentResource($appointment)`), `routes/api.php` (MOD, add `Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])`)
  - Commit: `feat(api): AppointmentController@show (green)`
  - Test gate: `vendor/bin/pest --filter=ShowAppointmentTest` exits 0
  - Dependency: T-API-18

- [ ] **T-API-20 [RED]** — `test(api): GET /api/doctors returns paginated list with ?specialty_id= filter`
  - Files: `tests/Feature/Api/ListDoctorsTest.php` (NEW, ~70 LOC, 2 scenarios: `it_returns_paginated_list_with_specialty_id_filter`; `it_returns_pagination_envelope_data_meta_links`). Uses 2 specialties × 3 doctors; asserts the filter and the envelope.
  - Commit: `test(api): red — list doctors paginated + specialty filter (fails; route not registered)`
  - **MUST FAIL** at this commit. Route returns 404.
  - Test gate: `vendor/bin/pest --filter=ListDoctorsTest` exits non-zero on this commit only
  - Dependency: T-API-15

- [ ] **T-API-21 [GREEN]** — `feat(api): add DoctorController@index + ListDoctorsRequest + DoctorResource + the route`
  - Files: `app/Http/Controllers/Api/DoctorController.php` (NEW, ~30 LOC: `__construct(private ListDoctorsRequest ...) {}` + `public function index(ListDoctorsRequest $request): AnonymousResourceCollection` that filters by `specialty_id` and searches `name LIKE %q%`; returns `DoctorResource::collection(...)`), `app/Http/Requests/Api/ListDoctorsRequest.php` (NEW, ~15 LOC: rules per design §3), `app/Http/Resources/Api/DoctorResource.php` (NEW, ~30 LOC: `toArray($request)` returns `['id', 'name' => $this->user->name, 'license_number', 'bio', 'specialty' => ['id' => $this->specialty->id, 'name' => $this->specialty->name, 'slug' => $this->specialty->slug]]`), `routes/api.php` (MOD, add `Route::get('/doctors', [DoctorController::class, 'index'])`)
  - Commit: `feat(api): DoctorController@index + ListDoctorsRequest + DoctorResource (green)`
  - Test gate: `vendor/bin/pest --filter=ListDoctorsTest` exits 0
  - Dependency: T-API-20

- [ ] **T-API-22 [RED]** — `test(api): GET /api/doctors/{id}/slots returns the available slots for a date`
  - Files: `tests/Feature/Api/ListSlotsTest.php` (NEW, ~80 LOC, 3 scenarios: `it_returns_slots_array_for_published_schedule`; `it_returns_empty_array_when_no_schedule`; `it_returns_422_VALIDATION_ERROR_for_invalid_date_format`). Uses `CreatesDoctors` (the trait added in this PR; see T-API-38) + a `DoctorSchedule` with `day_of_week` matching the test date. Asserts the `SlotResource` shape: `data[0].start` and `data[0].end` in the resolved TZ.
  - Commit: `test(api): red — list doctor slots (fails; route not registered)`
  - **MUST FAIL** at this commit. Route returns 404.
  - Test gate: `vendor/bin/pest --filter=ListSlotsTest` exits non-zero on this commit only
  - Dependency: T-API-15

- [ ] **T-API-23 [GREEN]** — `feat(api): add DoctorController@slots + ListSlotsRequest + SlotResource + the route`
  - Files: `app/Http/Controllers/Api/DoctorController.php` (MOD, add `public function slots(Doctor $doctor, ListSlotsRequest $request): AnonymousResourceCollection` that calls `app(DoctorAvailabilityService::class)->slots($doctor->id, CarbonImmutable::parse($request->validated('date')), $request->attributes->get('tz')->name)` and wraps the array in `SlotResource::collection`), `app/Http/Requests/Api/ListSlotsRequest.php` (NEW, ~20 LOC: rules per design §3), `app/Http/Resources/Api/SlotResource.php` (NEW, ~20 LOC: `toArray($request)` returns `['start' => $request->attributes->get('tz')->format($this['start']), 'end' => $request->attributes->get('tz')->format($this['end'])]`), `routes/api.php` (MOD, add `Route::get('/doctors/{doctor}/slots', [DoctorController::class, 'slots'])`)
  - Commit: `feat(api): DoctorController@slots + ListSlotsRequest + SlotResource (green)`
  - Test gate: `vendor/bin/pest --filter=ListSlotsTest` exits 0
  - Dependency: T-API-22

- [ ] **T-API-24 [RED]** — `test(api): GET /api/me returns the current user`
  - Files: `tests/Feature/Api/MeTest.php` (NEW, ~30 LOC, 1 scenario: `it_returns_200_with_user_resource_for_authenticated_actor`). The test logs in as each of the 3 roles and asserts the `UserResource` shape (`id`, `name`, `email`, `role`).
  - Commit: `test(api): red — /me returns current user (fails; route not registered)`
  - **MUST FAIL** at this commit. Route returns 404.
  - Test gate: `vendor/bin/pest --filter=MeTest` exits non-zero on this commit only
  - Dependency: T-API-15

- [ ] **T-API-25 [GREEN]** — `feat(api): add MeController@show + UserResource + the route`
  - Files: `app/Http/Controllers/Api/MeController.php` (NEW, ~20 LOC: `public function show(Request $request): UserResource` returns `new UserResource($request->user())`), `app/Http/Resources/Api/UserResource.php` (NEW, ~20 LOC: `toArray($request)` returns `['id', 'name', 'email', 'role' => $this->role->value]` — explicit deny-list, no `password`, no `remember_token`, no `email_verified_at`), `routes/api.php` (MOD, add `Route::get('/me', [MeController::class, 'show'])` outside the auth group — wait, inside; the auth group is what gates the token)
  - Commit: `feat(api): MeController@show + UserResource (green)`
  - Test gate: `vendor/bin/pest --filter=MeTest` exits 0
  - Dependency: T-API-24

- [ ] **T-API-26 [RED]** — `test(api): GET /api/specialties returns the active list`
  - Files: `tests/Feature/Api/ListSpecialtiesTest.php` (NEW, ~30 LOC, 1 scenario: `it_returns_200_with_active_specialties_only`). Creates 2 active + 1 inactive specialty; asserts only the 2 actives are returned.
  - Commit: `test(api): red — list active specialties (fails; route not registered)`
  - **MUST FAIL** at this commit. Route returns 404.
  - Test gate: `vendor/bin/pest --filter=ListSpecialtiesTest` exits non-zero on this commit only
  - Dependency: T-API-15

- [ ] **T-API-27 [GREEN]** — `feat(api): add SpecialtyController@index + SpecialtyResource + the route`
  - Files: `app/Http/Controllers/Api/SpecialtyController.php` (NEW, ~20 LOC: `public function index(): AnonymousResourceCollection` returns `SpecialtyResource::collection(Specialty::where('is_active', true)->orderBy('name')->get())` — NOT paginated per design §3), `app/Http/Resources/Api/SpecialtyResource.php` (NEW, ~20 LOC: `toArray($request)` returns `['id', 'name', 'slug', 'is_active']`), `routes/api.php` (MOD, add `Route::get('/specialties', [SpecialtyController::class, 'index'])`)
  - Commit: `feat(api): SpecialtyController@index + SpecialtyResource (green)`
  - Test gate: `vendor/bin/pest --filter=ListSpecialtiesTest` exits 0
  - Dependency: T-API-26

- [ ] **T-API-28 [RED]** — `test(api): GET /api/patients/{id} returns the patient (admin, doctor for own patients, or self)`
  - Files: `tests/Feature/Api/ShowPatientTest.php` (NEW, ~50 LOC, 3 scenarios: `it_returns_200_for_patient_calling_self`; `it_returns_200_for_doctor_assigned_to_patient_via_appointment`; `it_returns_403_FORBIDDEN_for_doctor_without_appointment_with_patient`)
  - Commit: `test(api): red — show patient with role-based authz (fails; route not registered)`
  - **MUST FAIL** at this commit. Route returns 404.
  - Test gate: `vendor/bin/pest --filter=ShowPatientTest` exits non-zero on this commit only
  - Dependency: T-API-15

- [ ] **T-API-29 [GREEN]** — `feat(api): add PatientController@show + PatientResource + the route`
  - Files: `app/Http/Controllers/Api/PatientController.php` (NEW, ~25 LOC: `public function show(Patient $patient): PatientResource` authorizes via `$this->authorize('view', $patient)` and returns the resource), `app/Http/Resources/Api/PatientResource.php` (NEW, ~25 LOC: `toArray($request)` returns `['id', 'name' => $this->user->name, 'dni', 'birth_date' => $request->attributes->get('tz')->format($this->birth_date)]`), `routes/api.php` (MOD, add `Route::get('/patients/{patient}', [PatientController::class, 'show'])`)
  - Commit: `feat(api): PatientController@show + PatientResource (green)`
  - Test gate: `vendor/bin/pest --filter=ShowPatientTest` exits 0
  - Dependency: T-API-28

- [ ] **T-API-30 [RED]** — `test(api): GET /api/medical-histories/{id} returns the history with proper authz`
  - Files: `tests/Feature/Api/ShowMedicalHistoryTest.php` (NEW, ~50 LOC, 2 scenarios: `it_returns_200_for_patient_calling_own`; `it_returns_403_FORBIDDEN_for_unassigned_doctor`)
  - Commit: `test(api): red — show medical history with role-based authz (fails; route not registered)`
  - **MUST FAIL** at this commit. Route returns 404.
  - Test gate: `vendor/bin/pest --filter=ShowMedicalHistoryTest` exits non-zero on this commit only
  - Dependency: T-API-15

- [ ] **T-API-31 [GREEN]** — `feat(api): add MedicalHistoryController@show + MedicalHistoryResource + the route`
  - Files: `app/Http/Controllers/Api/MedicalHistoryController.php` (NEW, ~25 LOC: `public function show(MedicalHistory $medicalHistory): MedicalHistoryResource` authorizes via `$this->authorize('view', $medicalHistory)` and returns the resource), `app/Http/Resources/Api/MedicalHistoryResource.php` (NEW, ~25 LOC: `toArray($request)` returns `['id', 'patient_id', 'opened_at' => $request->attributes->get('tz')->format($this->opened_at), 'notes_count' => $this->notes()->count()]`), `routes/api.php` (MOD, add `Route::get('/medical-histories/{medical_history}', [MedicalHistoryController::class, 'show'])`)
  - Commit: `feat(api): MedicalHistoryController@show + MedicalHistoryResource (green)`
  - Test gate: `vendor/bin/pest --filter=ShowMedicalHistoryTest` exits 0
  - Dependency: T-API-30

- [ ] **T-API-32 [RED]** — `test(api): GET /api/prescriptions returns paginated list for the current user`
  - Files: `tests/Feature/Api/ListPrescriptionsTest.php` (NEW, ~60 LOC, 2 scenarios: `it_returns_paginated_list_for_patient_scoped_to_own`; `it_returns_paginated_list_for_doctor_scoped_to_own_appointments`). Patient sees own; doctor sees via the appointments join; admin sees all (covered implicitly in the admin assertion).
  - Commit: `test(api): red — list prescriptions paginated + role-scoped (fails; route not registered)`
  - **MUST FAIL** at this commit. Route returns 404.
  - Test gate: `vendor/bin/pest --filter=ListPrescriptionsTest` exits non-zero on this commit only
  - Dependency: T-API-15

- [ ] **T-API-33 [GREEN]** — `feat(api): add PrescriptionController@index + ListPrescriptionsRequest + PrescriptionResource + the route`
  - Files: `app/Http/Controllers/Api/PrescriptionController.php` (NEW, ~35 LOC: `public function index(ListPrescriptionsRequest $request): AnonymousResourceCollection` with the role-scope logic from design §3; joins on appointments for doctor scope), `app/Http/Requests/Api/ListPrescriptionsRequest.php` (NEW, ~15 LOC: rules per design §3), `app/Http/Resources/Api/PrescriptionResource.php` (NEW, ~35 LOC: `toArray($request)` returns `['id', 'unique_code', 'issued_at' => $request->attributes->get('tz')->format($this->issued_at), 'items' => PrescriptionItemResource::collection($this->whenLoaded('items'))]`) + `app/Http/Resources/Api/PrescriptionItemResource.php` (NEW, ~15 LOC: `toArray` returns `['drug', 'dose', 'frequency', 'duration', 'position']`), `routes/api.php` (MOD, add `Route::get('/prescriptions', [PrescriptionController::class, 'index'])`)
  - Commit: `feat(api): PrescriptionController + ListPrescriptionsRequest + PrescriptionResource (green)`
  - Test gate: `vendor/bin/pest --filter=ListPrescriptionsTest` exits 0
  - Dependency: T-API-32

- [ ] **T-API-34 [RED]** — `test(api): GET /api/audit-logs returns paginated list for admin only`
  - Files: `tests/Feature/Api/ListAuditLogsTest.php` (NEW, ~70 LOC, 3 scenarios: `it_returns_paginated_list_for_admin`; `it_returns_403_FORBIDDEN_for_doctor`; `it_returns_403_FORBIDDEN_for_patient`)
  - Commit: `test(api): red — list audit logs admin-only (fails; route not registered)`
  - **MUST FAIL** at this commit. Route returns 404.
  - Test gate: `vendor/bin/pest --filter=ListAuditLogsTest` exits non-zero on this commit only
  - Dependency: T-API-15

- [ ] **T-API-35 [GREEN]** — `feat(api): add AuditLogController@index + ListAuditLogsRequest + AuditLogResource + the route`
  - Files: `app/Http/Controllers/Api/AuditLogController.php` (NEW, ~30 LOC: `public function index(ListAuditLogsRequest $request): AnonymousResourceCollection` with an explicit `if (! $request->user()->isAdmin()) abort(403, 'FORBIDDEN');` line, paginates by `actor_id`, `action`, `subject_type`, `from`, `to` filters), `app/Http/Requests/Api/ListAuditLogsRequest.php` (NEW, ~15 LOC: rules per design §3), `app/Http/Resources/Api/AuditLogResource.php` (NEW, ~25 LOC: `toArray($request)` returns `['id', 'actor_type', 'actor_id', 'verb', 'subject_type', 'subject_id', 'payload', 'occurred_at' => $request->attributes->get('tz')->format($this->occurred_at)]`), `routes/api.php` (MOD, add `Route::get('/audit-logs', [AuditLogController::class, 'index'])`)
  - Commit: `feat(api): AuditLogController + ListAuditLogsRequest + AuditLogResource (green)`
  - Test gate: `vendor/bin/pest --filter=ListAuditLogsTest` exits 0
  - Dependency: T-API-34

### Sub-group 3B: State transitions + logout (4 endpoints, 1 commits, ~90-120 LOC)

- [ ] **T-API-36 [RED]** — `test(api): POST /api/appointments/{id}/transitions/{action} moves to the correct state for the 3 transitions + POST /api/auth/logout returns 204`
  - Files: `tests/Feature/Api/TransitionAppointmentTest.php` (NEW, ~150 LOC, 3 scenarios × 1 transition = 3 scenarios for `confirm` + 1 for `complete` + 1 for `no-show` + 1 for `logout`, parameterized via `dataset('transitions', fn () => ['confirm', 'complete', 'no-show'])` + a separate `LogoutTest.php` with 1 scenario). The unauthorized scenario for `confirm` is also included: `it_returns_403_UNAUTHORIZED_ACTOR_for_non_assigned_doctor`. Total: 5 scenarios across 2 files.
  - Commit: `test(api): red — transition endpoints + logout (fails; routes not registered)`
  - **MUST FAIL** at this commit. All 4 transition routes + the logout route return 404.
  - Test gate: `vendor/bin/pest --filter=TransitionAppointmentTest` and `--filter=LogoutTest` exit non-zero on this commit only
  - Dependency: T-API-15

- [ ] **T-API-37 [GREEN]** — `feat(api): add AppointmentTransitionController (confirm + complete + markNoShow) + AppointmentTransitionRequest + AuthController@logout + the 4 routes`
  - Files: `app/Http/Controllers/Api/AppointmentTransitionController.php` (NEW, ~50 LOC: 3 thin methods, each authorizes via the policy + invokes the corresponding `ConfirmAppointmentTransition` / `CompleteAppointmentTransition` / `MarkNoShowAppointmentTransition` from agenda-core PR 3 + returns the `AppointmentResource`), `app/Http/Requests/Api/AppointmentTransitionRequest.php` (NEW, ~15 LOC: rules per design §3 — `notes` is nullable string max 1000), `app/Http/Controllers/Api/AuthController.php` (MOD, add `public function logout(Request $request): Response` that calls `$request->user()->currentAccessToken()->delete()` and returns `response()->noContent()`), `routes/api.php` (MOD, add 4 routes: `Route::post('/appointments/{appointment}/transitions/confirm', [AppointmentTransitionController::class, 'confirm'])`, same for `complete` and `no-show`; `Route::post('/auth/logout', [AuthController::class, 'logout'])`)
  - Commit: `feat(api): 3 transition endpoints + logout (green)`
  - Test gate: `vendor/bin/pest --filter=TransitionAppointmentTest` and `--filter=LogoutTest` exit 0
  - Dependency: T-API-36

### Sub-group 3C: Docs + verify

- [ ] **T-API-38 [STRUCTURAL]** — `chore(test)+docs(readme): add CreatesDoctors fixture + document the REST API + curl examples + Sanctum token flow`
  - Files: `tests/Support/CreatesDoctors.php` (NEW, ~40 LOC: Pest trait with `createDoctorUser(): array{user, doctor, token}` method that creates a `User` (role=doctor), a `Doctor` profile with a unique `license_number`, a `MedicalHistory`-less patient if needed, a Sanctum token, and a published `DoctorSchedule` for the test's day-of-week. The schedule is keyed off `CarbonImmutable::now()->dayOfWeek` so the test can compute a valid `date` query string for the slots endpoint), `README.md` (MOD, add a "REST API (agenda-http)" section, ~150 LOC, with: a 19-row endpoint table (3 auth + 16 public); a `curl POST /api/auth/login` example; a `curl POST /api/appointments` example with bearer token; a `?tz=America/New_York` override example; the `LengthAwarePaginator` envelope shape; the standard error envelope shape; the `Roles` and `Ownership` matrix)
  - Commit: `chore(test)+docs(readme): add CreatesDoctors fixture + document REST API + curl + Sanctum flow`
  - Test gate: `vendor/bin/pest --filter=CreatesDoctors` (a trivial smoke test in the trait file) exits 0; the README contains the section header (`grep -q 'REST API' README.md`)
  - Dependency: T-API-35 (all 3A endpoints are documented)

- [ ] **T-API-39 [VERIFICATION]** — `chore(test): verify PR 3 test suite + migrate:fresh --seed + route:list gates on MariaDB`
  - Files: none
  - Commit: `chore(test): verify PR 3 test suite + migrate:fresh --seed + route:list gates on MariaDB`
  - The PR 3 acceptance gate (all must pass before merge):
    1. `vendor/bin/pest` (SQLite in-memory) → all tests green, 0 new skipped
    2. `DB_CONNECTION=mariadb php artisan migrate:fresh --env=testing` → exits 0
    3. `DB_CONNECTION=mariadb vendor/bin/pest` → all tests green
    4. `php artisan route:list --path=api` → shows 19 routes (3 auth + 16 public)
    5. `npm run build` → exits 0 (Filament assets are unchanged, but the build is a sanity check)
    6. The README's curl examples are smoke-tested: `php artisan tinker --execute="echo \App\Models\User::factory()->create(['role' => 'patient'])->createToken('smoke')->plainTextToken;"` returns a token; `curl -H "Authorization: Bearer $TOKEN" http://127.0.0.1:8000/api/me` returns the user JSON
    7. `git log --oneline -24` shows the expected commit shape (see below)
  - Test gate: all 7 above pass
  - Dependency: T-API-38

### PR 3 expected commit log (bottom-to-top, ~24 commits)

```
<hash> chore(test): verify PR 3 test suite + migrate:fresh --seed + route:list gates on MariaDB
<hash> chore(test)+docs(readme): add CreatesDoctors fixture + document REST API + curl + Sanctum flow
<hash> feat(api): 3 transition endpoints + logout (green)
<hash> test(api): red — transition endpoints + logout (fails; routes not registered)
<hash> feat(api): AuditLogController + ListAuditLogsRequest + AuditLogResource (green)
<hash> test(api): red — list audit logs admin-only (fails; route not registered)
<hash> feat(api): PrescriptionController + ListPrescriptionsRequest + PrescriptionResource (green)
<hash> test(api): red — list prescriptions paginated + role-scoped (fails; route not registered)
<hash> feat(api): MedicalHistoryController@show + MedicalHistoryResource (green)
<hash> test(api): red — show medical history with role-based authz (fails; route not registered)
<hash> feat(api): PatientController@show + PatientResource (green)
<hash> test(api): red — show patient with role-based authz (fails; route not registered)
<hash> feat(api): SpecialtyController@index + SpecialtyResource (green)
<hash> test(api): red — list active specialties (fails; route not registered)
<hash> feat(api): MeController@show + UserResource (green)
<hash> test(api): red — /me returns current user (fails; route not registered)
<hash> feat(api): DoctorController@slots + ListSlotsRequest + SlotResource (green)
<hash> test(api): red — list doctor slots (fails; route not registered)
<hash> feat(api): DoctorController@index + ListDoctorsRequest + DoctorResource (green)
<hash> test(api): red — list doctors paginated + specialty filter (fails; route not registered)
<hash> feat(api): AppointmentController@show (green)
<hash> test(api): red — show appointment detail + 403 for non-owner (fails; route not registered)
<hash> feat(api): AppointmentController@index + ListAppointmentsRequest (green)
<hash> test(api): red — list appointments paginated + role-scoped (fails; route not registered)
```

### PR 3 verification (must all pass)

- `vendor/bin/pest` → 0 failures, 0 errors, **~26 new tests** (one per endpoint + a few parametrized over transitions) + 1 race test (skipped on SQLite) + 5 Timezone unit tests + 1 CreatesPatients smoke + 1 CreatesDoctors smoke
- `DB_CONNECTION=mariadb vendor/bin/pest` → same
- `php artisan migrate:fresh --env=testing` → exits 0
- `php artisan route:list --path=api` → shows 19 routes
- `npm run build` → exits 0
- The README curl examples are smoke-tested live

### PR 3 known risks to verify

- **N9** (Low): `LengthAwarePaginator` JSON envelope shape must match the spec scenario. **Verified** by the list-endpoint tests asserting on `{data, links, meta}` keys directly (T-API-16, T-API-20, T-API-32, T-API-34). The Laravel default shape is the expected shape; no custom serialization.
- **N6** (Med): 400-line budget pressure. **Verified** by the per-PR LOC estimate (~330-380) staying under the cap. The 24-commit count is the consequence of strict TDD (one red + one green per endpoint), not a code-volume concern.

---

## Open questions for sdd-apply (7 from design §12)

The design surfaces 7 open questions. The tasks above adopt the recommended resolution for each. `sdd-apply` should confirm with the user before deviating.

1. **Order of `tests/Feature/Api/` files** — Recommended: **alphabetical** (matches the existing `tests/Feature/Agenda/` convention). Tasks above use alphabetical (e.g. `AuthSanctumTest`, `BookAppointmentTest`, `CancelAppointmentTest`, ...). **No deviation expected.**
2. **Add a `GET /api/routes` debug endpoint?** — Recommended: **defer to a future change**. `php artisan route:list --path=api` is sufficient for v1. **Not in tasks.**
3. **Add `GET /api/version` returning the app version?** — Recommended: **defer to a future change**. **Not in tasks.**
4. **Should Sanctum tokens have an expiry?** — Recommended: **defer to a future security-hardening change**. v1 is long-lived bearer tokens with explicit `tokens()->delete()` revocation. **Not in tasks.**
5. **Pagination URL base** — Recommended: **no action**. Laravel's `LengthAwarePaginator` uses the current request URL as the base. Worth knowing; no code change. **Not in tasks.**
6. **Timezone in error details** — Recommended: **resolved TZ** (matches the request). The `SLOT_NOT_AVAILABLE` `error.details.start_time` is formatted via the `Timezone` value object. Documented in the design §7. **Reflected in T-API-6 (ErrorResponse::fromException) — uses `$request->attributes->get('tz')` to format datetime details.**
7. **`?mine=true` query param on list endpoints** — Recommended: **add in PR 3** alongside the list endpoints with the role-scoping semantics. The current tasks use the role-based scope without `?mine=` (patients see own implicitly; doctors see own implicitly; admin sees all). If the user wants `?mine=true`, add it as a one-line filter override in `AppointmentController@index` and `PrescriptionController@index` in T-API-17 and T-API-33. **Trivial change; not in current tasks; surface for user confirmation.**

---

## PR 4 — Auth Surface (login + logout + me + TOKEN_EXPIRED)

**Why this PR exists**: `sdd-verify` for PR 3 returned FAIL with 2 CRITICAL findings — the 3 auth endpoints (`POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/me`) listed in `proposal.md:37`, `design.md:185,189-191`, `spec.md:197-214` (REQ-API-7) and the `README.md:463-465` curl example were never implemented. The implementation has `GET /api/me` (a PR 1 placeholder) instead of `/api/auth/me`. Additionally, the `TOKEN_EXPIRED` error code required by `spec.md:9,21-24` (REQ-API-1 scenario #3) is missing — `ErrorResponse::resolve()` always returns `UNAUTHENTICATED` for any `AuthenticationException`.

**Goal**: close the 2 CRITICAL spec gaps. After PR 4 merges, `sdd-verify` should return `pass-with-warnings` (or `pass`) and `sdd-archive` can run.

**Scope**:
- 3 new endpoints (`POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/me`) — spec REQ-API-7, 4 scenarios total
- 1 new error code (`TOKEN_EXPIRED`) — spec REQ-API-1 scenario #3
- Retire the undocumented `/api/me` placeholder + `MeController@show` (move the logic to `AuthController@me` to satisfy the spec path)
- Keep the existing `UserResource` (its shape `{id, name, email, role}` already matches both `REQ-API-7` login + me responses)

**Out of scope** (deferred to a future change):
- `?mine=true` query param (still an open question)
- Throttling / rate-limiting on `/api/auth/login` (Laravel's `ThrottleRequests` middleware; can be added in a follow-up)
- Refresh tokens / token rotation (Sanctum v1 doesn't support; out of scope for v1)
- `personal_access_tokens.expires_at` migration (PR 4 uses a test-time `expires_at` override on the token model via `Carbon::setTestNow` + `withExpiresAt` on `createToken`)

**Tasks (10 total — 4 RED+GREEN pairs + 1 STRUCTURAL + 1 VERIFY)**:

- [ ] **T-API-40 [RED]** — `test(api): POST /api/auth/login returns 200 with user and token + 401 with bad creds` (REQ-API-7 scenarios 1 + 2)
  - File: `tests/Feature/Api/AuthLoginTest.php` (NEW, ~45 LOC, 2 scenarios)
  - Scenario A: `it('returns 200 with {data.user, data.token} for valid credentials')` — uses `CreatesPatients` + `actingAs` pattern; asserts `data.user.id`, `data.user.email`, `data.user.role === 'patient'`, and `data.token` is a non-empty string (NOT a specific value, because Sanctum tokens are random)
  - Scenario B: `it('returns 401 UNAUTHENTICATED for bad credentials')` — sends valid email + wrong password; asserts `error.code = 'UNAUTHENTICATED'`

- [ ] **T-API-41 [GREEN]** — `feat(api): AuthController@login + LoginRequest + the route (no auth guard)`
  - Files:
    - `app/Http/Controllers/Api/AuthController.php` (NEW, ~30 LOC: `public function login(LoginRequest $request): JsonResponse` — calls `Auth::guard('web')->attempt($credentials)`, on success returns `new JsonResponse(['data' => ['user' => new UserResource($user), 'token' => $user->createToken($deviceName)->plainTextToken]])`, on failure throws `AuthenticationException` (handled by `ErrorResponse` as 401 UNAUTHENTICATED))
    - `app/Http/Requests/Api/LoginRequest.php` (NEW, ~20 LOC: `authorize(): true`, rules: `email` required|email, `password` required|string, `device_name` nullable|string|max:64)
    - `routes/api.php` (MOD, add `Route::post('/auth/login', [AuthController::class, 'login'])` OUTSIDE the auth:sanctum group but INSIDE the ResolveTimezone group so the 422 INVALID_TIMEZONE envelope is also rendered for login)
  - Commit: `feat(api): AuthController@login + LoginRequest (green)`
  - Dependency: T-API-40

- [ ] **T-API-42 [RED]** — `test(api): POST /api/auth/logout returns 204 and revokes the token` (REQ-API-7 scenario 3)
  - File: `tests/Feature/Api/AuthLogoutTest.php` (NEW, ~35 LOC, 1 scenario + 1 helper)
  - Scenario: `it('returns 204 and deletes the current access token from personal_access_tokens')` — uses `CreatesPatients`, mints a token, calls logout, asserts status 204 + asserts `DB::table('personal_access_tokens')->count() === 0`
  - Helper: `it('returns 401 UNAUTHENTICATED when not authenticated')` (covered by the global exception handler tests already, but worth a 1-line assert here for symmetry)

- [ ] **T-API-43 [GREEN]** — `feat(api): AuthController@logout + the route`
  - Files:
    - `app/Http/Controllers/Api/AuthController.php` (MOD, add `public function logout(Request $request): \Illuminate\Http\Response` that calls `$request->user()->currentAccessToken()->delete()` and returns `response()->noContent()`)
    - `routes/api.php` (MOD, add `Route::post('/auth/logout', [AuthController::class, 'logout'])` INSIDE the auth:sanctum group, immediately after the `/auth/me` route placeholder)
  - Commit: `feat(api): AuthController@logout (green)`
  - Dependency: T-API-42

- [ ] **T-API-44 [RED]** — `test(api): GET /api/auth/me returns 200 with the current user + 401 when not authenticated` (REQ-API-7 scenario 4 + REQ-API-1 scenario 2)
  - File: `tests/Feature/Api/AuthMeTest.php` (NEW, ~40 LOC, 2 scenarios)
  - Scenario A: `it('returns 200 with the current user resource for an authenticated user')` — uses `CreatesPatients`, asserts `data.id`, `data.name`, `data.email`, `data.role === 'patient'`
  - Scenario B: `it('returns 401 UNAUTHENTICATED when not authenticated')` — asserts `error.code = 'UNAUTHENTICATED'`

- [ ] **T-API-45 [GREEN]** — `feat(api): AuthController@me + the route`
  - Files:
    - `app/Http/Controllers/Api/AuthController.php` (MOD, add `public function me(Request $request): UserResource` — returns `new UserResource($request->user())`, mirroring the old `MeController@show` body)
    - `routes/api.php` (MOD, add `Route::get('/auth/me', [AuthController::class, 'me'])` INSIDE the auth:sanctum group)
  - Commit: `feat(api): AuthController@me (green)`
  - Dependency: T-API-44

- [ ] **T-API-46 [STRUCTURAL]** — `chore(refactor): retire /api/me placeholder + MeController; update affected tests`
  - Why: the implementation has `GET /api/me` pointing to `MeController@show`, but the spec says `GET /api/auth/me` and the new `AuthController@me` covers the same logic. The old path was a PR 1 placeholder. For v1 (no external clients) the cleanest move is to rename, not alias.
  - Files:
    - `app/Http/Controllers/Api/MeController.php` (DELETE)
    - `routes/api.php` (MOD, remove `use App\Http\Controllers\Api\MeController;` + remove `Route::get('/me', [MeController::class, 'show']);`)
    - `tests/Feature/Api/MeTest.php` (RENAME → `AuthMeTest.php` + replace 3 `/api/me` paths with `/api/auth/me` + update the doc comment to reference the new path)
    - `tests/Feature/Api/AuthSanctumTest.php` (MOD, replace 2 `/api/me` calls with `/api/auth/me` so the `?tz=` validation tests exercise the canonical route)
  - Commit: `chore(refactor): retire /api/me placeholder, route now lives at /api/auth/me`
  - Dependency: T-API-45 (the new route must exist before the old one is removed, otherwise the AuthSanctumTest updates break)

- [ ] **T-API-47 [RED]** — `test(api): expired Sanctum token returns 401 TOKEN_EXPIRED` (REQ-API-1 scenario 3)
  - File: `tests/Feature/Api/AuthExpiredTokenTest.php` (NEW, ~35 LOC, 1 scenario)
  - Approach: `Carbon::setTestNow($someMoment)` → create a Sanctum token via `createToken('test')` (default 0 expiry, no `expires_at`) → set `Carbon::setTestNow($someLaterMoment)` past whatever threshold the middleware uses → call `/api/auth/me` with the bearer token → assert `error.code = 'TOKEN_EXPIRED'`
  - **Caveat**: Sanctum's `HasApiTokens::createToken()` does NOT set `expires_at` by default. The cleanest way to test "expired" is to manually update `personal_access_tokens.expires_at` to a past timestamp via `DB::table('personal_access_tokens')->update(['expires_at' => now()->subDay()])`. Then call the endpoint and assert the new code path.
  - Scenario: `it('returns 401 TOKEN_EXPIRED for a Sanctum token whose expires_at is in the past')`

- [ ] **T-API-48 [GREEN]** — `feat(api): ErrorResponse resolves TOKEN_EXPIRED for expired Sanctum tokens`
  - File: `app/Http/Responses/Api/ErrorResponse.php` (MOD, the `AuthenticationException` branch — currently returns `'UNAUTHENTICATED'` — now checks: if the request has an `Authorization: Bearer ...` header AND the token can be resolved from `personal_access_tokens` AND its `expires_at` is in the past → return `[401, 'TOKEN_EXPIRED', 'Token has expired.', null]`; otherwise return the existing `[401, 'UNAUTHENTICATED', 'Authentication required.', null]`)
  - Implementation hint: use `Laravel\Sanctum\PersonalAccessToken::findToken($request->bearerToken())` to inspect the token without going through the auth guard (which would also throw).
  - Commit: `feat(api): ErrorResponse distinguishes TOKEN_EXPIRED from UNAUTHENTICATED (green)`
  - Dependency: T-API-47

- [ ] **T-API-49 [VERIFICATION]** — `chore(test): verify PR 4 test suite + migrate:fresh --seed + route:list gates on MariaDB`
  - Run `vendor/bin/pest` → all pass on SQLite (expected: 113+3 → 121+3 = +8 new tests: 2 login + 1 logout + 2 me + 1 expired = 6, plus the 2 moved MeTest→AuthMeTest scenarios = 8 net)
  - Run `DB_CONNECTION=mariadb php artisan migrate:fresh --env=testing` → no errors (no schema changes; transport-only)
  - Run `DB_CONNECTION=mariadb vendor/bin/pest` → all pass including the driver-aware `ConcurrentDoubleBookHttpTest`
  - Run `php artisan route:list --path=api` → 18 routes (was 16: +3 auth, -1 /api/me = net +2 = 18). Verify each new route is registered with the correct middleware
  - Run `php artisan tinker --execute="echo App\Models\User::first()?->createToken('manual-test')->plainTextToken;"` → mints a valid token (sanity check the auth flow end-to-end)
  - Commit: `chore(test): verify PR 4 test suite + route:list gates on MariaDB`
  - Dependency: T-API-48

### PR 4 expected commit log (bottom-to-top, 10 commits)

```
<hash> chore(test): verify PR 4 test suite + route:list gates on MariaDB
<hash> feat(api): ErrorResponse distinguishes TOKEN_EXPIRED from UNAUTHENTICATED (green)
<hash> test(api): expired Sanctum token returns 401 TOKEN_EXPIRED (red)
<hash> chore(refactor): retire /api/me placeholder, route now lives at /api/auth/me
<hash> feat(api): AuthController@me (green)
<hash> test(api): GET /api/auth/me returns 200 with the current user (red)
<hash> feat(api): AuthController@logout (green)
<hash> test(api): POST /api/auth/logout returns 204 and revokes the token (red)
<hash> feat(api): AuthController@login + LoginRequest (green)
<hash> test(api): POST /api/auth/login returns 200 with user and token (red)
```

### PR 4 verification (must all pass)

1. `vendor/bin/pest` on SQLite → 121 passed + 3 skipped (+8 vs PR 3 baseline of 113+3)
2. `DB_CONNECTION=mariadb vendor/bin/pest` → 124 passed (+8 vs PR 3 baseline of 116)
3. `php artisan route:list --path=api` → 18 routes (16 PR 3 baseline + 3 auth - 1 /api/me = +2 net; the spec's 19 includes the /api/me alias which we're NOT implementing)
4. `php -r "require 'vendor/autoload.php'; ..."` manual `curl` smoke test: login → use token → me → logout → me again returns 401 UNAUTHENTICATED (or TOKEN_EXPIRED if we manipulate the token)
5. `git log --oneline -10` shows the expected commit shape with red→green pair order preserved
6. `README.md` curl example for `POST /api/auth/login` now works (returns 200 instead of 404)

### PR 4 known risks to verify

- **R-PR4-1**: The `TOKEN_EXPIRED` detection logic depends on `personal_access_tokens.expires_at` being set. The test uses `DB::table(...)->update(['expires_at' => ...])` to set it manually. Verify that the production code path (no `expires_at` set) still returns `UNAUTHENTICATED` (not `TOKEN_EXPIRED` for valid tokens).
- **R-PR4-2**: Retiring `/api/me` + `MeController` is a breaking change for any client that may have used it. For v1 (no external clients yet) this is safe. Verify no internal Laravel code references `MeController` (e.g. service providers, other tests).
- **R-PR4-3**: `AuthController@login` uses `Auth::guard('web')->attempt($credentials)`. The `web` guard must have the `App\Models\User` provider configured. Verify the default `config/auth.php` providers[users] is wired to `App\Models\User::class` (Laravel default — but worth a sanity check).
- **R-PR4-4**: `UserResource` is used by both `/api/auth/login` and `/api/auth/me`. Verify the resource shape is consistent across both responses (the login wraps it as `data.user`, the me returns it directly as `data`).

---

## Cross-PR dependency graph

```
T-API-1 → T-API-2 → T-API-3 → T-API-4 → T-API-5 → T-API-6 → T-API-7 → T-API-8 (PR 1 done)
                                                                ↓
T-API-9 → T-API-10 → T-API-11 → T-API-12 → T-API-13 → T-API-14 → T-API-15 (PR 2 done)
                                                                          ↓
T-API-16..T-API-19 (appointments index + show) → T-API-20..T-API-23 (doctor slots) →
T-API-24..T-API-27 (/me + specialties) → T-API-28..T-API-31 (patient + medical history) →
T-API-32..T-API-35 (prescriptions + audit-logs) → T-API-36..T-API-37 (transitions + logout) →
T-API-38 (docs + CreatesDoctors) → T-API-39 (verify) (PR 3 done)
```

Within PR 3, the sub-groups are independent of each other (3A: reads, 3B: transitions, 3C: docs+verify) and can be applied in any order within the PR — the dependency is only on PR 2 being merged.

---

## Verification gates (per PR, all 3 must pass before merge)

1. `vendor/bin/pest` (SQLite in-memory) → all tests pass, no new skipped beyond the pre-existing `ConcurrentDoubleBookTest` (action-level) and the new `ConcurrentDoubleBookHttpTest` (HTTP-level) on SQLite
2. `DB_CONNECTION=mariadb php artisan migrate:fresh --env=testing` → no errors (no schema changes; transport-only)
3. `DB_CONNECTION=mariadb vendor/bin/pest` → all tests pass including the driver-aware `ConcurrentDoubleBookHttpTest`
4. `php artisan route:list --path=api` → the expected number of routes (P1: 1 placeholder + auth setup; P2: 2 mutations; P3: 19 total)
5. `npm run build` → no errors (Filament assets are unchanged, but the build is a sanity check)
6. `git log --oneline -<N>` shows the expected commit shape (8 for P1, 7 for P2, ~24 for P3) with the red→green pair order preserved
7. `php artisan tinker --execute="echo config('clinic.timezone');"` → `America/Argentina/Buenos_Aires` (P1 only — locked at P1)

## Aggregate metrics

| Metric | P1 | P2 | P3 | P4 (post-verify follow-up) | Total |
|---|---|---|---|---|---|
| Tasks | 8 | 7 | 24 | 10 | **49** |
| RED commits | 1 | 2 | 12 | 4 | **19** |
| GREEN commits | 1 | 2 | 12 | 4 | **19** |
| STRUCTURAL commits | 4 | 1 | 1 | 1 | **7** |
| VERIFY commits | 1 | 1 | 1 | 1 | **4** |
| RED+GREEN single commits | 1 | 0 | 0 | 0 | **1** |
| LOC estimate (hand-written) | 330–380 | 330–380 | 330–380 | 200–250 | **~1,290** |
| New files (app/) | 5 | 4 | 14 | 2 | **25** |
| New files (tests/) | 3 | 3 | 13 | 4 | **23** |
| Modified files | 2 | 2 | 2 | 4 | **10** |
| Deleted files | 0 | 0 | 0 | 1 (MeController) | **1** |
| TDD exceptions documented | 3 (T-API-3, T-API-7, T-API-14) | 0 | 0 | 0 | **3** |
