<?php

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDoctors;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class, CreatesDoctors::class);

/**
 * PR 3 — agenda-http — GET /api/appointments (REQ-API-6 + REQ-API-7
 * + the role-scope rules from design.md §"Architecture > Auth + RBAC").
 *
 * RED at this commit: the GET /api/appointments route does not exist
 * yet, so every scenario returns 404 (with error.code =
 * ROUTE_NOT_FOUND per the PR 1 exception handler). All 4 assertions
 * must fail.
 *
 * Once T-API-17 lands (AppointmentController@index +
 * ListAppointmentsRequest + the route), all 4 scenarios must pass.
 *
 * Scope rules:
 *   - patient  → only their own appointments (patient_id = $user->patient->id)
 *   - doctor   → only their own appointments (doctor_id = $user->doctor->id)
 *   - admin    → all appointments
 *
 * Pagination rules:
 *   - default per_page = 20
 *   - max per_page = 100
 *   - envelope shape = LengthAwarePaginator (`{data, links, meta}`)
 *   - orderBy start_time ASC (stable pagination)
 */

beforeEach(function (): void {
    // Two doctors, two patients, six appointments interleaved so the
    // role-scope assertions can't be fooled by ordering side effects.
    [$this->docAUser, $this->docA, ] = $this->createDoctorWithToken();
    [$this->docBUser, $this->docB, ] = $this->createDoctorWithToken();

    [$this->patientAUser, $this->patientA, ] = $this->createPatientWithToken();
    [$this->patientBUser, $this->patientB, ] = $this->createPatientWithToken();

    $base = CarbonImmutable::now()->addDays(2)->setTime(10, 0);

    // 3 appointments for patientA (across both doctors)
    Appointment::factory()->for($this->docA)->for($this->patientA)->create([
        'state' => 'pending',
        'start_time' => $base->copy()->addHours(0),
        'end_time' => $base->copy()->addHours(0)->addMinutes(30),
    ]);
    Appointment::factory()->for($this->docB)->for($this->patientA)->create([
        'state' => 'confirmed',
        'start_time' => $base->copy()->addHours(2),
        'end_time' => $base->copy()->addHours(2)->addMinutes(30),
    ]);
    Appointment::factory()->for($this->docA)->for($this->patientA)->create([
        'state' => 'cancelled',
        'start_time' => $base->copy()->addHours(4),
        'end_time' => $base->copy()->addHours(4)->addMinutes(30),
    ]);

    // 3 appointments for patientB (with both doctors)
    Appointment::factory()->for($this->docA)->for($this->patientB)->create([
        'state' => 'pending',
        'start_time' => $base->copy()->addHours(6),
        'end_time' => $base->copy()->addHours(6)->addMinutes(30),
    ]);
    Appointment::factory()->for($this->docB)->for($this->patientB)->create([
        'state' => 'confirmed',
        'start_time' => $base->copy()->addHours(8),
        'end_time' => $base->copy()->addHours(8)->addMinutes(30),
    ]);
    Appointment::factory()->for($this->docB)->for($this->patientB)->create([
        'state' => 'completed',
        'start_time' => $base->copy()->addHours(10),
        'end_time' => $base->copy()->addHours(10)->addMinutes(30),
    ]);
});

it('returns a paginated list scoped to the patient for patient actors', function (): void {
    $response = $this->actingAs($this->patientAUser, 'sanctum')
        ->getJson('/api/appointments');

    $response->assertStatus(200)
        ->assertJsonPath('meta.total', 3)        // patientA has 3 appointments
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonPath('meta.current_page', 1);

    $data = $response->json('data');
    expect($data)->toBeArray();
    expect(count($data))->toBe(3);

    // Every returned appointment must belong to patientA (NOT patientB).
    foreach ($data as $row) {
        expect($row['patient_id'])->toBe($this->patientA->id);
        expect($row['patient_id'])->not->toBe($this->patientB->id);
    }
});

it('returns a paginated list scoped to the doctor for doctor actors', function (): void {
    $response = $this->actingAs($this->docAUser, 'sanctum')
        ->getJson('/api/appointments');

    $response->assertStatus(200)
        ->assertJsonPath('meta.total', 3);       // docA has 3 appointments (2 patientA + 1 patientB)

    $data = $response->json('data');
    expect($data)->toBeArray();
    expect(count($data))->toBe(3);

    foreach ($data as $row) {
        expect($row['doctor_id'])->toBe($this->docA->id);
        expect($row['doctor_id'])->not->toBe($this->docB->id);
    }
});

it('returns the full unfiltered list for admin actors', function (): void {
    $admin = \App\Models\User::factory()->admin()->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/appointments');

    $response->assertStatus(200)
        ->assertJsonPath('meta.total', 6);       // all 6 appointments

    $data = $response->json('data');
    expect($data)->toBeArray();
    expect(count($data))->toBe(6);
});

it('respects per_page with the LengthAwarePaginator envelope', function (): void {
    $response = $this->actingAs($this->patientAUser, 'sanctum')
        ->getJson('/api/appointments?per_page=2');

    $response->assertStatus(200)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
            'links' => ['first', 'last', 'prev', 'next'],
        ]);

    $data = $response->json('data');
    expect($data)->toBeArray();
    expect(count($data))->toBe(2);
});
