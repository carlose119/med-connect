<?php

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDoctors;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class, CreatesDoctors::class);

/**
 * PR 3 — agenda-http — GET /api/patients/{id} (REQ-API-6 + the
 * PatientPolicy@view gate from agenda-core PR 3).
 *
 * RED at this commit: the route doesn't exist. All 4 scenarios fail.
 *
 * Authz (PatientPolicy@view):
 *   - admin                  → 200
 *   - doctor (any)           → 200 (doctors can view every patient)
 *   - self patient           → 200
 *   - other patient          → 403 FORBIDDEN
 */

it('returns 200 when the patient calls for their own profile', function (): void {
    [$user, $patient, ] = $this->createPatientWithToken();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/patients/{$patient->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $patient->id)
        ->assertJsonPath('data.user_id', $user->id);
});

it('returns 200 when an admin requests the patient', function (): void {
    [, $patient, ] = $this->createPatientWithToken();
    $admin = \App\Models\User::factory()->admin()->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson("/api/patients/{$patient->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $patient->id);
});

it('returns 200 when the doctor has an appointment with the patient', function (): void {
    [$doctorUser, $doctor, ] = $this->createDoctorWithToken();
    [, $patient, ] = $this->createPatientWithToken();

    // Create a confirmed appointment linking the doctor to the patient.
    Appointment::factory()
        ->for($doctor)
        ->for($patient)
        ->create([
            'state' => 'confirmed',
            'start_time' => CarbonImmutable::now()->addDays(2)->setTime(10, 0),
            'end_time' => CarbonImmutable::now()->addDays(2)->setTime(10, 30),
        ]);

    $response = $this->actingAs($doctorUser, 'sanctum')
        ->getJson("/api/patients/{$patient->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $patient->id);
});

it('returns 403 FORBIDDEN for a different patient', function (): void {
    [, $patient, ] = $this->createPatientWithToken();
    [$otherUser, , ] = $this->createPatientWithToken();

    $response = $this->actingAs($otherUser, 'sanctum')
        ->getJson("/api/patients/{$patient->id}");

    $response->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN');
});
