<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDoctors;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class, CreatesDoctors::class);

/**
 * Post-PR-3 patch — agenda-http — GET /api/doctors/{doctor}
 * (REQ-API-7 + the doctor directory show endpoint in design.md §5).
 *
 * RED at this commit: the GET /api/doctors/{doctor} route is not yet
 * registered, so every scenario returns 404 (with error.code =
 * ROUTE_NOT_FOUND per the PR 1 exception handler). Both assertions
 * must fail.
 *
 * Once the show method lands (DoctorController@show + the route
 * registration), both scenarios must pass.
 *
 * Authz: doctors are public — Sanctum auth required, no per-row
 * authorization (any authenticated user can see any doctor).
 */
beforeEach(function (): void {
    [$this->patient] = $this->createPatientWithToken();
    [, $this->doctor] = $this->createDoctorWithToken();
});

it('returns 200 with the doctor resource for an authenticated user', function (): void {
    $response = $this->actingAs($this->patient, 'sanctum')
        ->getJson("/api/doctors/{$this->doctor->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $this->doctor->id)
        ->assertJsonPath('data.user_id', $this->doctor->user_id)
        ->assertJsonPath('data.specialty_id', $this->doctor->specialty_id)
        ->assertJsonPath('data.license_number', $this->doctor->license_number)
        ->assertJsonPath('data.user.id', $this->doctor->user->id)
        ->assertJsonPath('data.user.name', $this->doctor->user->name)
        ->assertJsonPath('data.specialty.id', $this->doctor->specialty->id)
        ->assertJsonPath('data.specialty.name', $this->doctor->specialty->name);
});

it('returns 401 with the standard envelope when not authenticated', function (): void {
    $response = $this->getJson("/api/doctors/{$this->doctor->id}");

    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});

/**
 * Coverage delta — agenda-test-coverage (item 11, REQ-API-7 §13).
 *
 * GET /api/doctors/{id} for a missing doctor returns 404 NOT_FOUND
 * (NOT ROUTE_NOT_FOUND — the route exists, only the resource is
 * missing).
 *
 * The ErrorResponse::resolve() arms for ModelNotFoundException
 * (lines 89-91) and the Laravel-prepareException recovery arm for
 * NotFoundHttpException whose previous is ModelNotFoundException
 * (lines 99-101) both return [404, 'NOT_FOUND', ...]. The route
 * /api/doctors/{doctor} exists, so Laravel never throws the bare
 * NotFoundHttpException — the ModelNotFoundException from
 * route-model binding wins.
 *
 * RED is "test does not exist yet". The new scenario passes on
 * first run. TDD exception documented at T-COV-14.
 */
it('returns 404 NOT_FOUND for a missing doctor', function (): void {
    $response = $this->actingAs($this->patient, 'sanctum')
        ->getJson('/api/doctors/999999');

    $response->assertStatus(404)
        ->assertJsonPath('error.code', 'NOT_FOUND');
});
