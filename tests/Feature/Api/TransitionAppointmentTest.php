<?php

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDoctors;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class, CreatesDoctors::class);

/**
 * PR 3 — agenda-http — POST
 * /api/appointments/{id}/transitions/{confirm|complete|no-show}
 * (REQ-API-2 + the state machine + transitions from agenda-core PR 3).
 *
 * RED at this commit: the 3 transition routes are not registered.
 * All 6 scenarios fail (each transition is tested with a happy + a
 * wrong-actor scenario = 6 total, parameterised via
 * dataset('transitions')).
 *
 * Once T-API-37 lands (AppointmentTransitionController +
 * AppointmentTransitionRequest + the 3 routes), all 6 must pass.
 *
 * Authz (per the transition class semantics — these are enforced
 * by the transition itself, not by a separate policy):
 *   - confirm: assigned doctor only. Patients get
 *     UnauthorizedActorException → 403 UNAUTHORIZED_ACTOR.
 *   - complete: assigned doctor only. Patients get
 *     UnauthorizedActorException → 403 UNAUTHORIZED_ACTOR.
 *   - no-show: assigned doctor OR admin. Patients get
 *     UnauthorizedActorException → 403 UNAUTHORIZED_ACTOR.
 */
dataset('transitions', [
    'confirm' => 'confirm',
    'complete' => 'complete',
    'no-show' => 'no-show',
]);

it('lets the assigned doctor transition the appointment (happy path)', function (string $action): void {
    // Build a doctor + a patient + an appointment in the source state
    // required for the action.
    [$doctorUser, $doctor] = $this->createDoctorWithToken();
    [, $patient] = $this->createPatientWithToken();

    $start = CarbonImmutable::now()->addDays(2)->setTime(10, 0);

    // Source state depends on the action.
    $sourceState = match ($action) {
        'confirm' => 'pending',
        'complete', 'no-show' => 'confirmed',
    };

    $appointment = Appointment::factory()
        ->for($doctor)
        ->for($patient)
        ->create([
            'state' => $sourceState,
            'start_time' => $start,
            'end_time' => $start->copy()->addMinutes(30),
        ]);

    $expected = match ($action) {
        'confirm' => 'confirmed',
        'complete' => 'completed',
        'no-show' => 'no_show',
    };

    $response = $this->actingAs($doctorUser, 'sanctum')
        ->postJson("/api/appointments/{$appointment->id}/transitions/{$action}");

    $response->assertStatus(200)
        ->assertJsonPath('data.state', $expected)
        ->assertJsonPath('data.id', $appointment->id);

    // The DB row must reflect the new state.
    $appointment->refresh();
    expect($appointment->state::$name)->toBe($expected);
})->with('transitions');

it('rejects a patient attempting the transition with UNAUTHORIZED_ACTOR', function (string $action): void {
    [, $doctor] = $this->createDoctorWithToken();
    [$patientUser, $patient] = $this->createPatientWithToken();

    $start = CarbonImmutable::now()->addDays(2)->setTime(10, 0);

    $sourceState = match ($action) {
        'confirm' => 'pending',
        'complete', 'no-show' => 'confirmed',
    };

    $appointment = Appointment::factory()
        ->for($doctor)
        ->for($patient)
        ->create([
            'state' => $sourceState,
            'start_time' => $start,
            'end_time' => $start->copy()->addMinutes(30),
        ]);

    $response = $this->actingAs($patientUser, 'sanctum')
        ->postJson("/api/appointments/{$appointment->id}/transitions/{$action}");

    $response->assertStatus(403)
        ->assertJsonPath('error.code', 'UNAUTHORIZED_ACTOR');

    // The DB state must NOT have changed.
    $appointment->refresh();
    expect($appointment->state::$name)->toBe($sourceState);
})->with('transitions');
