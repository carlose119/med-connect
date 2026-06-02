<?php

use App\Models\Appointment;
use App\Models\MedicalHistory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDoctors;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class, CreatesDoctors::class);

/**
 * PR 3 — agenda-http — GET /api/medical-histories/{id} (REQ-API-6).
 *
 * RED at this commit: the route doesn't exist. All 3 scenarios fail.
 *
 * Authz (delegates to the controller; the existing app has no
 * dedicated MedicalHistoryPolicy, so the controller uses an inline
 * role check):
 *   - self patient  → 200
 *   - other patient → 403 FORBIDDEN
 *   - assigned doctor → 200
 */

beforeEach(function (): void {
    [$this->patientUser, $this->patient, ] = $this->createPatientWithToken();
    $this->history = MedicalHistory::factory()->for($this->patient)->create();
});

it('returns 200 for the patient who owns the medical history', function (): void {
    $response = $this->actingAs($this->patientUser, 'sanctum')
        ->getJson("/api/medical-histories/{$this->history->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $this->history->id)
        ->assertJsonPath('data.patient_id', $this->patient->id);
});

it('returns 403 FORBIDDEN for a different patient', function (): void {
    [$otherUser, , ] = $this->createPatientWithToken();

    $response = $this->actingAs($otherUser, 'sanctum')
        ->getJson("/api/medical-histories/{$this->history->id}");

    $response->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('returns 200 for the assigned doctor (has appointment with the patient)', function (): void {
    [$doctorUser, $doctor, ] = $this->createDoctorWithToken();

    Appointment::factory()
        ->for($doctor)
        ->for($this->patient)
        ->create([
            'state' => 'confirmed',
            'start_time' => CarbonImmutable::now()->addDays(2)->setTime(10, 0),
            'end_time' => CarbonImmutable::now()->addDays(2)->setTime(10, 30),
        ]);

    $response = $this->actingAs($doctorUser, 'sanctum')
        ->getJson("/api/medical-histories/{$this->history->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $this->history->id);
});
