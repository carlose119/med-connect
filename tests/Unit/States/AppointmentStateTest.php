<?php

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->doctorUser = User::factory()->doctor()->create();
    $this->doctor = Doctor::factory()->for($this->doctorUser)->create();

    $this->patientUser = User::factory()->patient()->create();
    $this->patient = Patient::factory()->for($this->patientUser)->create();

    $this->admin = User::factory()->admin()->create();

    $this->appointment = Appointment::factory()
        ->for($this->doctor)
        ->for($this->patient)
        ->create([
            'state' => 'pending',
            'start_time' => now()->addHours(48),
            'end_time' => now()->addHours(48)->addMinutes(30),
        ]);
});

it('casts the state column to the AppointmentState enum', function () {
    expect($this->appointment->state)->toBeInstanceOf(\App\States\AppointmentState::class);
});

it('starts a fresh appointment in the Pending state class', function () {
    expect($this->appointment->state)->toBeInstanceOf(\App\States\Appointment\Pending::class);
});

it('lets the assigned doctor confirm a pending appointment', function () {
    $this->appointment->state->transitionTo(
        \App\States\Appointment\Confirmed::class,
        $this->doctorUser,
    );
    expect($this->appointment->state)->toBeInstanceOf(\App\States\Appointment\Confirmed::class);
});

it('rejects a patient attempting to self-confirm', function () {
    expect(fn () => $this->appointment->state->transitionTo(
        \App\States\Appointment\Confirmed::class,
        $this->patientUser,
    ))->toThrow(\App\Exceptions\Domain\UnauthorizedActorException::class);
});

it('rejects a different (unassigned) doctor attempting to confirm', function () {
    $otherDoctorUser = User::factory()->doctor()->create();
    expect(fn () => $this->appointment->state->transitionTo(
        \App\States\Appointment\Confirmed::class,
        $otherDoctorUser,
    ))->toThrow(\App\Exceptions\Domain\UnauthorizedActorException::class);
});

it('lets the assigned doctor complete a confirmed appointment', function () {
    DB::table('appointments')->where('id', $this->appointment->id)->update(['state' => 'confirmed']);
    $this->appointment->refresh();

    $this->appointment->state->transitionTo(
        \App\States\Appointment\Completed::class,
        $this->doctorUser,
    );
    expect($this->appointment->state)->toBeInstanceOf(\App\States\Appointment\Completed::class);
});

it('lets a patient cancel a pending appointment inside the 24h window', function () {
    $this->appointment->state->transitionTo(
        \App\States\Appointment\Cancelled::class,
        $this->patientUser,
    );
    expect($this->appointment->state)->toBeInstanceOf(\App\States\Appointment\Cancelled::class);
});

it('rejects a patient cancelling outside the 24h window', function () {
    DB::table('appointments')->where('id', $this->appointment->id)->update([
        'start_time' => now()->addHours(12),
        'end_time' => now()->addHours(12)->addMinutes(30),
    ]);
    $this->appointment->refresh();

    expect(fn () => $this->appointment->state->transitionTo(
        \App\States\Appointment\Cancelled::class,
        $this->patientUser,
    ))->toThrow(\App\Exceptions\Domain\CancellationWindowViolationException::class);
});

it('rejects any transition out of a terminal state', function () {
    DB::table('appointments')->where('id', $this->appointment->id)->update(['state' => 'cancelled']);
    $this->appointment->refresh();

    expect(fn () => $this->appointment->state->transitionTo(
        \App\States\Appointment\Pending::class,
        $this->doctorUser,
    ))->toThrow(\App\Exceptions\Domain\InvalidStateTransitionException::class);
});
