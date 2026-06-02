<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class);

/**
 * PR 4 — agenda-http — GET /api/auth/me (REQ-API-7 scenario 4 +
 * UserResource shape contract).
 *
 * The canonical current-user path is now /api/auth/me. The PR 1
 * /api/me placeholder was a closure; PR 3 promoted it to a typed
 * `MeController@show`; PR 4 renames the route to /api/auth/me and
 * moves the controller to `AuthController@me` (the same body, same
 * UserResource shape).
 *
 * The 3 scenarios cover the role variation of the UserResource
 * (patient via the patient fixture, doctor + admin via the
 * UserFactory states). The 401 path is covered by
 * `AuthSanctumTest::it_returns_401_UNAUTHENTICATED_when_no_Sanctum_token_is_provided`
 * (which was updated in PR 4 to call /api/auth/me).
 */
it('returns 200 with the user resource shape for a patient actor', function (): void {
    [$user, , ] = $this->createPatientWithToken();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/auth/me');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'role'],
        ])
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.role', 'patient');

    // Must NOT leak any field beyond the canonical shape.
    $payload = $response->json('data');
    expect($payload)->not->toHaveKey('password');
    expect($payload)->not->toHaveKey('remember_token');
    expect($payload)->not->toHaveKey('email_verified_at');
    expect($payload)->not->toHaveKey('created_at');
    expect($payload)->not->toHaveKey('updated_at');
});

it('returns 200 with the user resource shape for a doctor actor', function (): void {
    $user = User::factory()->doctor()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/auth/me');

    $response->assertStatus(200)
        ->assertJsonPath('data.role', 'doctor');
});

it('returns 200 with the user resource shape for an admin actor', function (): void {
    $user = User::factory()->admin()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/auth/me');

    $response->assertStatus(200)
        ->assertJsonPath('data.role', 'admin');
});
