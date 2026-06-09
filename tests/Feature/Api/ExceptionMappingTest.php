<?php

use App\Exceptions\Domain\AnticipationWindowViolationException;
use App\Exceptions\Domain\CancellationWindowViolationException;
use App\Exceptions\Domain\InvalidStateTransitionException;
use App\Exceptions\Domain\PatientOverlapException;
use App\Exceptions\Domain\SlotNotAvailableException;
use App\Exceptions\Domain\UnauthorizedActorException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * PR 1 — agenda-http — exception → HTTP envelope contract (REQ-API-3 + REQ-API-4).
 *
 * The 10 scenarios below pin the standard error envelope shape
 * (`{ "error": { "code", "message", "details?" } }`) plus the
 * status code + error.code mapping for one row each of the table in
 * agenda-http/design.md §6. The test throws each exception through a
 * fake route registered in beforeEach() and asserts the JSON response.
 *
 * RED at this commit. The framework returns HTML 500 for the
 * unhandled exceptions; all 10 assertions fail until T-API-6 lands
 * the withExceptions handler + ErrorResponse helper.
 */
beforeEach(function (): void {
    $this->thrower = function (string $type): void {
        throw match ($type) {
            'slot' => new SlotNotAvailableException('Slot not available for test.'),
            'anticipation' => new AnticipationWindowViolationException('Inside 2h window.'),
            'overlap' => new PatientOverlapException('Patient already booked.'),
            'unauthorized_actor' => new UnauthorizedActorException('Wrong actor for this transition.'),
            'cancellation_window' => new CancellationWindowViolationException('Inside 24h cancellation window.'),
            'invalid_transition' => new InvalidStateTransitionException('Out of terminal state.'),
            'forbidden' => new AuthorizationException('Not authorised for this resource.'),
            'not_found' => new ModelNotFoundException('No query results for the test model.'),
            'validation' => new ValidationException(Validator::make([], ['name' => 'required'])),
            'internal' => new RuntimeException('Boom — unmapped exception.'),
            default => throw new RuntimeException("Unknown thrower type: {$type}"),
        };
    };

    // Register a public throw-route under the api/* prefix so the
    // withExceptions handler (lands in T-API-6) sees the request as
    // an API request. The route is not behind auth:sanctum.
    Route::get('/api/_test/throw/{type}', function (string $type) {
        ($this->thrower)($type);
    })->name('test.api.throw');

    $this->user = User::factory()->create();
});

it('renders SlotNotAvailableException as 409 with code SLOT_NOT_AVAILABLE', function (): void {
    $this->actingAs($this->user)
        ->getJson('/api/_test/throw/slot')
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'SLOT_NOT_AVAILABLE')
        ->assertJsonStructure(['error' => ['code', 'message']]);
});

it('renders AnticipationWindowViolationException as 422 with code ANTICIPATION_WINDOW_VIOLATION', function (): void {
    $this->actingAs($this->user)
        ->getJson('/api/_test/throw/anticipation')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ANTICIPATION_WINDOW_VIOLATION');
});

it('renders PatientOverlapException as 422 with code PATIENT_OVERLAP', function (): void {
    $this->actingAs($this->user)
        ->getJson('/api/_test/throw/overlap')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'PATIENT_OVERLAP');
});

it('renders UnauthorizedActorException as 403 with code UNAUTHORIZED_ACTOR', function (): void {
    $this->actingAs($this->user)
        ->getJson('/api/_test/throw/unauthorized_actor')
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'UNAUTHORIZED_ACTOR');
});

it('renders CancellationWindowViolationException as 422 with code CANCELLATION_WINDOW_VIOLATION', function (): void {
    $this->actingAs($this->user)
        ->getJson('/api/_test/throw/cancellation_window')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'CANCELLATION_WINDOW_VIOLATION');
});

it('renders InvalidStateTransitionException as 422 with code INVALID_STATE_TRANSITION', function (): void {
    $this->actingAs($this->user)
        ->getJson('/api/_test/throw/invalid_transition')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'INVALID_STATE_TRANSITION');
});

it('renders Laravel AuthorizationException as 403 with code FORBIDDEN', function (): void {
    $this->actingAs($this->user)
        ->getJson('/api/_test/throw/forbidden')
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('renders ModelNotFoundException as 404 with code NOT_FOUND', function (): void {
    $this->actingAs($this->user)
        ->getJson('/api/_test/throw/not_found')
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'NOT_FOUND');
});

it('renders ValidationException as 422 with code VALIDATION_ERROR + details keyed by field', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson('/api/_test/throw/validation')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');

    // The handler must surface the field-keyed validation errors in
    // `error.details` so the client can render inline form errors.
    $details = $response->json('error.details');
    expect($details)->toBeArray();
    expect($details)->toHaveKey('name');
});

it('renders an unmapped exception as 500 with code INTERNAL_ERROR', function (): void {
    $this->actingAs($this->user)
        ->getJson('/api/_test/throw/internal')
        ->assertStatus(500)
        ->assertJsonPath('error.code', 'INTERNAL_ERROR');
});

/**
 * Coverage delta — agenda-test-coverage (item 4). REQ-API-4 §10:
 * NotFoundHttpException surfaces as 404 ROUTE_NOT_FOUND (route 404,
 * NOT resource 404).
 *
 * The ErrorResponse::resolve() arm for NotFoundHttpException
 * (app/Http/Responses/Api/ErrorResponse.php lines 107-109) already
 * returns [404, 'ROUTE_NOT_FOUND', ...]. RED is "test does not exist
 * yet" — the new scenario passes on first run. TDD exception
 * documented at T-COV-6.
 */
it('returns 404 ROUTE_NOT_FOUND for an unknown route', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/this-route-does-not-exist')
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'ROUTE_NOT_FOUND');
});
