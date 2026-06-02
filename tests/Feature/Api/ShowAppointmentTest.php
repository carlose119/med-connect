<?php

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDoctors;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class, CreatesDoctors::class);

/**
 * PR 3 — agenda-http — GET /api/appointments/{id} (REQ-API-7 + the
 * AppointmentPolicy@view gate from agenda-core PR 3).
 *
 * RED at this commit: the GET /api/appointments/{appointment} route
 * is not yet registered, so every scenario returns 404 (with
 * error.code = ROUTE_NOT_FOUND per the PR 1 exception handler). All
 * 3 assertions must fail.
 *
 * Once T-API-19 lands (AppointmentController@show + the route), all
 * 3 scenarios must pass.
 *
 * Authz (AppointmentPolicy@view):
 *   - admin          → 200
 *   - assigned doctor → 200
 *   - assigned patient → 200
 *   - other patient   → 403 FORBIDDEN
 */

beforeEach(function (): void {
    [, $this->doctor, ] = $this->createDoctorWithToken();
    [$this->ownerUser, $this->ownerPatient, ] = $this->createPatientWithToken();
    [, $this->otherPatient, ] = $this->createPatientWithToken();

    $this->appointment = Appointment::factory()
        ->for($this->doctor)
        ->for($this->ownerPatient)
        ->create([
            'state' => 'pending',
            'start_time' => CarbonImmutable::now()->addDays(2)->setTime(10, 0),
            'end_time' => CarbonImmutable::now()->addDays(2)->setTime(10, 30),
        ]);
});

it('returns 200 with the appointment resource for the assigned patient', function (): void {
    $response = $this->actingAs($this->ownerUser, 'sanctum')
        ->getJson("/api/appointments/{$this->appointment->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $this->appointment->id)
        ->assertJsonPath('data.state', 'pending')
        ->assertJsonPath('data.doctor_id', $this->doctor->id)
        ->assertJsonPath('data.patient_id', $this->ownerPatient->id);
});

it('returns 403 FORBIDDEN for a non-owner patient', function (): void {
    $otherUser = $this->otherPatient->user;

    $response = $this->actingAs($otherUser, 'sanctum')
        ->getJson("/api/appointments/{$this->appointment->id}");

    $response->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('returns 200 with the appointment resource for the assigned doctor', function (): void {
    [, $doctor, ] = $this->createDoctorWithToken();
    $appt = Appointment::factory()
        ->for($doctor)
        ->for($this->ownerPatient)
        ->create([
            'state' => 'pending',
            'start_time' => CarbonImmutable::now()->addDays(2)->setTime(11, 0),
            'end_time' => CarbonImmutable::now()->addDays(2)->setTime(11, 30),
        ]);

    $doctorUser = $doctor->user;

    $response = $this->actingAs($doctorUser, 'sanctum')
        ->getJson("/api/appointments/{$appt->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $appt->id)
        ->assertJsonPath('data.doctor_id', $doctor->id);
});
