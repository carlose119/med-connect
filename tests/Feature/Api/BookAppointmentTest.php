<?php

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDoctors;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class, CreatesDoctors::class);

/**
 * PR 2 — agenda-http — POST /api/appointments (REQ-API-2 + REQ-API-7
 * + REQ-API-8 + REQ-API-9 + the booking contract from
 * agenda-core/booking/spec.md).
 *
 * RED at this commit: the POST /api/appointments route does not
 * exist yet, so every scenario returns 404 (with error.code =
 * ROUTE_NOT_FOUND per the PR 1 exception handler). All 4 assertions
 * must fail.
 *
 * Once T-API-11 lands (AppointmentController@store +
 * BookAppointmentRequest + AppointmentResource + the route), all 4
 * scenarios must pass.
 */

beforeEach(function (): void {
    // Create a doctor + a published schedule for "now + 3 days at 10:00".
    // The 3-day buffer guarantees the anticipation check (2h minimum)
    // passes trivially.
    [$this->doctorUser, $this->doctor, ] = $this->createDoctorWithToken(
        CarbonImmutable::now()->addDays(3),
    );

    // The doctor's schedule was seeded by the trait for
    // `now()->addDays(3)->dayOfWeekIso` with a 09:00-12:00 window in
    // 30-minute slots. Build the canonical "10:00 local" start that
    // the trait schedules guarantee.
    $this->targetDay = CarbonImmutable::now()->addDays(3)->setTime(10, 0);
});

it('returns 201 with the appointment resource on the happy path', function (): void {
    [$user, $patient, ] = $this->createPatientWithToken();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/appointments', [
            'doctor_id' => $this->doctor->id,
            'start_time' => $this->targetDay->toIso8601String(),
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.state', 'pending')
        ->assertJsonPath('data.doctor_id', $this->doctor->id)
        ->assertJsonPath('data.patient_id', $patient->id)
        ->assertJsonStructure(['data' => ['id', 'doctor_id', 'patient_id', 'state', 'start_time', 'end_time']]);

    // The DB must hold exactly one appointment for (doctor, patient).
    expect(Appointment::query()
        ->where('doctor_id', $this->doctor->id)
        ->where('patient_id', $patient->id)
        ->count())->toBe(1);
});

it('returns 422 VALIDATION_ERROR when doctor_id does not exist', function (): void {
    [$user, , ] = $this->createPatientWithToken();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/appointments', [
            'doctor_id' => 99999,
            'start_time' => $this->targetDay->toIso8601String(),
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');

    $details = $response->json('error.details');
    expect($details)->toBeArray();
    expect($details)->toHaveKey('doctor_id');
    expect($details['doctor_id'])->toBeArray();
});

it('returns 409 SLOT_NOT_AVAILABLE when the slot is already booked', function (): void {
    // First booking as a patient — succeeds and creates a row that
    // blocks the slot. The patient actor is the "self-service" path
    // so the controller resolves patient_id from $user->patient->id
    // without needing a `patient_id` in the body.
    [$firstUser, , ] = $this->createPatientWithToken();
    $this->actingAs($firstUser, 'sanctum')
        ->postJson('/api/appointments', [
            'doctor_id' => $this->doctor->id,
            'start_time' => $this->targetDay->toIso8601String(),
        ])->assertStatus(201);

    // Second booking (different patient, same slot) must collide via
    // the slot-lookup guard in BookAppointmentAction.
    [$user, , ] = $this->createPatientWithToken();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/appointments', [
            'doctor_id' => $this->doctor->id,
            'start_time' => $this->targetDay->toIso8601String(),
        ]);

    $response->assertStatus(409)
        ->assertJsonPath('error.code', 'SLOT_NOT_AVAILABLE');
});

it('returns 422 ANTICIPATION_WINDOW_VIOLATION when start_time is inside the 2h window', function (): void {
    [$user, , ] = $this->createPatientWithToken();

    // 1h in the future. The 2h anticipation check must reject it
    // even though the slot exists in the published list.
    $nearFuture = CarbonImmutable::now()->addHour()->setMinute(0)->setSecond(0);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/appointments', [
            'doctor_id' => $this->doctor->id,
            'start_time' => $nearFuture->toIso8601String(),
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'ANTICIPATION_WINDOW_VIOLATION');
});
