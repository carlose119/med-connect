<?php

use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDoctors;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class, CreatesDoctors::class);

/**
 * PR 3 — agenda-http — GET /api/doctors (REQ-API-6 + the directory
 * spec at design.md §5 #11).
 *
 * RED at this commit: the GET /api/doctors route does not exist yet.
 * All 3 assertions must fail.
 *
 * Once T-API-21 lands (DoctorController@index + ListDoctorsRequest +
 * DoctorResource + the route), all 3 scenarios must pass.
 */
beforeEach(function (): void {
    // Two specialties. CreatesDoctors defaults to 'general-medicine';
    // we create a second one explicitly for the filter test.
    $this->specialtyA = Specialty::firstOrCreate(
        ['slug' => 'general-medicine'],
        ['name' => 'General Medicine', 'is_active' => true],
    );
    $this->specialtyB = Specialty::firstOrCreate(
        ['slug' => 'cardiology'],
        ['name' => 'Cardiology', 'is_active' => true],
    );

    // 3 doctors in specialtyA, 2 doctors in specialtyB
    for ($i = 0; $i < 3; $i++) {
        $this->createDoctorWithToken();
    }
    for ($i = 0; $i < 2; $i++) {
        $user = User::factory()->doctor()->create();
        Doctor::factory()->for($user)->for($this->specialtyB)->create();
    }
});

it('returns a paginated list of all active doctors', function (): void {
    [$patient] = $this->createPatientWithToken();

    $response = $this->actingAs($patient, 'sanctum')
        ->getJson('/api/doctors');

    $response->assertStatus(200)
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            'links' => ['first', 'last', 'prev', 'next'],
        ]);

    $data = $response->json('data');
    expect($data)->toBeArray();
    expect(count($data))->toBe(5);

    foreach ($data as $row) {
        expect($row)->toHaveKey('id');
        expect($row)->toHaveKey('user_id');
        expect($row)->toHaveKey('specialty_id');
        expect($row)->toHaveKey('license_number');
        expect($row)->toHaveKey('user');
    }
});

it('filters the list by ?specialty_id=', function (): void {
    [$patient] = $this->createPatientWithToken();

    $response = $this->actingAs($patient, 'sanctum')
        ->getJson("/api/doctors?specialty_id={$this->specialtyB->id}");

    $response->assertStatus(200)
        ->assertJsonPath('meta.total', 2);

    $data = $response->json('data');
    expect($data)->toBeArray();
    expect(count($data))->toBe(2);
    foreach ($data as $row) {
        expect($row['specialty_id'])->toBe($this->specialtyB->id);
    }
});

it('respects per_page for the doctors list', function (): void {
    [$patient] = $this->createPatientWithToken();

    $response = $this->actingAs($patient, 'sanctum')
        ->getJson('/api/doctors?per_page=2');

    $response->assertStatus(200)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.last_page', 3);

    $data = $response->json('data');
    expect(count($data))->toBe(2);
});
