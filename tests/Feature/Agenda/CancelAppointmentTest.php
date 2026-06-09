<?php

use App\Actions\CancelAppointmentAction;
use App\Exceptions\Domain\CancellationWindowViolationException;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\States\Appointment\Cancelled;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->doctorUser = User::factory()->doctor()->create();
    $this->doctor = Doctor::factory()->for($this->doctorUser)->create();

    $this->patientUser = User::factory()->patient()->create();
    $this->patient = Patient::factory()->for($this->patientUser)->create();
});

/**
 * PR 4 red: CancelAppointmentAction is the only domain action that
 * can be called by the patient, the doctor, or an admin. Each actor
 * path has a different business rule:
 *
 *   - Patient inside the 24h window (now() < start_time - 24h): allowed
 *   - Patient outside the 24h window: CancellationWindowViolationException
 *   - Doctor: always allowed (no time restriction)
 *   - Admin: always allowed (no time restriction)
 *
 * The action reuses the PR 3 `CancelAppointmentTransition` for the
 * actor/authorization checks and the actual state change. The
 * action's own job is the 24h anticipación pre-check (it throws
 * before dispatching the transition) and the cancellation_reason
 * write.
 */
it('lets a patient cancel inside the 24h window (now+48h) and records cancellation_reason', function () {
    $appointment = Appointment::factory()
        ->for($this->doctor)
        ->for($this->patient)
        ->create([
            'state' => 'pending',
            'start_time' => now()->addHours(48),
            'end_time' => now()->addHours(48)->addMinutes(30),
        ]);

    $action = app(CancelAppointmentAction::class);
    $cancelled = $action($appointment->id, $this->patientUser, 'Patient changed mind.');

    expect($cancelled->state)->toBeInstanceOf(Cancelled::class)
        ->and($cancelled->cancellation_reason)->toBe('Patient changed mind.');
});

it('rejects a patient cancelling outside the 24h window (now+12h)', function () {
    $appointment = Appointment::factory()
        ->for($this->doctor)
        ->for($this->patient)
        ->create([
            'state' => 'pending',
            'start_time' => now()->addHours(12),
            'end_time' => now()->addHours(12)->addMinutes(30),
        ]);

    $action = app(CancelAppointmentAction::class);

    expect(fn () => $action($appointment->id, $this->patientUser, 'Too late.'))
        ->toThrow(CancellationWindowViolationException::class);
});

it('lets the assigned doctor cancel at any time, even inside the 24h window', function () {
    $appointment = Appointment::factory()
        ->for($this->doctor)
        ->for($this->patient)
        ->create([
            'state' => 'pending',
            'start_time' => now()->addHours(2),
            'end_time' => now()->addHours(2)->addMinutes(30),
        ]);

    $action = app(CancelAppointmentAction::class);
    $cancelled = $action($appointment->id, $this->doctorUser, 'Doctor unavailable.');

    expect($cancelled->state)->toBeInstanceOf(Cancelled::class)
        ->and($cancelled->cancellation_reason)->toBe('Doctor unavailable.');
});
