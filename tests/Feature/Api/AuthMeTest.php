<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class);

/**
 * PR 4 — agenda-http — GET /api/auth/me (REQ-API-7 scenario 4 +
 * REQ-API-1 scenario 2).
 *
 * RED at this commit: the canonical /api/auth/me route does not
 * exist yet. The /api/me route from PR 1 + PR 3 is in place but
 * is being retired in T-API-46; the canonical path per the spec
 * is /api/auth/me. Both scenarios fail with 404 (ROUTE_NOT_FOUND):
 *
 *   - happy path: asserts data.id / data.name / data.email /
 *     data.role == 'patient'. The /api/auth/me route does not
 *     exist.
 *   - 401 not authenticated: asserts 401 UNAUTHENTICATED. The
 *     /api/auth/me route does not exist, so 404.
 *
 * Once T-API-45 lands (AuthController@me + the GET /api/auth/me
 * route inside the auth:sanctum group), both scenarios must pass.
 */

it('returns 200 with the current user resource for an authenticated patient', function (): void {
    [$user, , ] = $this->createPatientWithToken();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/auth/me');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'role'],
        ])
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', $user->name)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.role', 'patient');

    // The UserResource deny-list contract must hold on /api/auth/me
    // just like on /api/me — no password, no remember_token, no
    // email_verified_at, no timestamps.
    $payload = $response->json('data');
    expect($payload)->not->toHaveKey('password');
    expect($payload)->not->toHaveKey('remember_token');
    expect($payload)->not->toHaveKey('email_verified_at');
    expect($payload)->not->toHaveKey('created_at');
    expect($payload)->not->toHaveKey('updated_at');
});

it('returns 200 with role=admin for an admin actor', function (): void {
    $user = User::factory()->admin()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/auth/me')
        ->assertStatus(200)
        ->assertJsonPath('data.role', 'admin')
        ->assertJsonPath('data.id', $user->id);
});

it('returns 401 UNAUTHENTICATED when not authenticated', function (): void {
    // No Authorization header — the route is gated by auth:sanctum
    // and must return 401 via the PR 1 exception handler.
    $this->getJson('/api/auth/me')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});
