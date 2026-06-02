<?php

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDoctors;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class, CreatesDoctors::class);

/**
 * PR 2 — agenda-http — DELETE /api/appointments/{id} (REQ-API-2 +
 * REQ-API-7 + the cancel contract from agenda-core/cancel/spec.md).
 *
 * RED at this commit: the DELETE /api/appointments/{appointment}
 * route is not yet registered (it lands in T-API-13), so every
 * scenario returns 404 (with error.code = ROUTE_NOT_FOUND per the
 * PR 1 exception handler). All 3 assertions must fail.
 *
 * Once T-API-13 lands (AppointmentController@cancel +
 * CancelAppointmentRequest + the route), all 3 scenarios must pass.
 */

beforeEach(function (): void {
    // Doctor + schedule so a fixture appointment can be created with
    // a known doctor_id and start_time. The cancel endpoint doesn't
    // need the schedule; we only need a valid Appointment row to
    // cancel.
    [, $this->doctor, ] = $this->createDoctorWithToken();
});

it('lets a patient cancel inside the 24h window (now+48h) and records cancellation_reason', function (): void {
    [$user, $patient, ] = $this->createPatientWithToken();

    $appointment = Appointment::factory()
        ->for($this->doctor)
        ->for($patient)
        ->create([
            'state' => 'pending',
            'start_time' => CarbonImmutable::now()->addHours(48),
            'end_time' => CarbonImmutable::now()->addHours(48)->addMinutes(30),
        ]);

    $response = $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/appointments/{$appointment->id}", [
            'reason' => 'Patient unavailable',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.state', 'cancelled')
        ->assertJsonPath('data.cancellation_reason', 'Patient unavailable');
});

it('rejects a patient cancelling outside the 24h window (now+12h) with CANCELLATION_WINDOW_VIOLATION', function (): void {
    [$user, $patient, ] = $this->createPatientWithToken();

    $appointment = Appointment::factory()
        ->for($this->doctor)
        ->for($patient)
        ->create([
            'state' => 'pending',
            'start_time' => CarbonImmutable::now()->addHours(12),
            'end_time' => CarbonImmutable::now()->addHours(12)->addMinutes(30),
        ]);

    $response = $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/appointments/{$appointment->id}", [
            'reason' => 'Too late',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'CANCELLATION_WINDOW_VIOLATION');
});

it('lets the assigned doctor cancel at any time, even inside the 24h window', function (): void {
    [$doctorUser, $doctor, ] = $this->createDoctorWithToken();
    [, $patient, ] = $this->createPatientWithToken();

    $appointment = Appointment::factory()
        ->for($doctor)
        ->for($patient)
        ->create([
            'state' => 'pending',
            'start_time' => CarbonImmutable::now()->addHours(2),
            'end_time' => CarbonImmutable::now()->addHours(2)->addMinutes(30),
        ]);

    $response = $this->actingAs($doctorUser, 'sanctum')
        ->deleteJson("/api/appointments/{$appointment->id}", [
            'reason' => 'Doctor unavailable',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.state', 'cancelled')
        ->assertJsonPath('data.cancellation_reason', 'Doctor unavailable');
});
