<?php

use App\Models\Prescription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDoctors;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class, CreatesDoctors::class);

/**
 * PR 3 — agenda-http — GET /api/prescriptions (REQ-API-6).
 *
 * RED at this commit: the route doesn't exist. All 3 scenarios fail.
 *
 * Role-scope rules:
 *   - patient  → only their own prescriptions (patient_id = $user->patient->id)
 *   - doctor   → only prescriptions they issued (doctor_id = $user->doctor->id)
 *   - admin    → all prescriptions
 */
beforeEach(function (): void {
    [$this->docAUser, $this->docA] = $this->createDoctorWithToken();
    [$this->docBUser, $this->docB] = $this->createDoctorWithToken();
    [$this->patientAUser, $this->patientA] = $this->createPatientWithToken();
    [$this->patientBUser, $this->patientB] = $this->createPatientWithToken();

    $base = CarbonImmutable::now()->addDays(2)->setTime(10, 0);

    // 2 prescriptions for patientA (one from each doctor)
    Prescription::factory()->for($this->docA)->for($this->patientA)->create([
        'issued_at' => $base,
    ]);
    Prescription::factory()->for($this->docB)->for($this->patientA)->create([
        'issued_at' => $base->copy()->addHours(2),
    ]);

    // 1 prescription for patientB (from docB)
    Prescription::factory()->for($this->docB)->for($this->patientB)->create([
        'issued_at' => $base->copy()->addHours(4),
    ]);
});

it('returns paginated prescriptions scoped to the patient', function (): void {
    $response = $this->actingAs($this->patientAUser, 'sanctum')
        ->getJson('/api/prescriptions');

    $response->assertStatus(200)
        ->assertJsonPath('meta.total', 2);

    foreach ($response->json('data') as $row) {
        expect($row['patient_id'])->toBe($this->patientA->id);
    }
});

it('returns paginated prescriptions scoped to the doctor', function (): void {
    $response = $this->actingAs($this->docAUser, 'sanctum')
        ->getJson('/api/prescriptions');

    $response->assertStatus(200)
        ->assertJsonPath('meta.total', 1);

    foreach ($response->json('data') as $row) {
        expect($row['doctor_id'])->toBe($this->docA->id);
    }
});

it('returns the full unfiltered list for admins', function (): void {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/prescriptions');

    $response->assertStatus(200)
        ->assertJsonPath('meta.total', 3);
});
