<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class);

/**
 * PR 3 — agenda-http — GET /api/me (REQ-API-3 + UserResource shape).
 *
 * RED at this commit: the route still returns the placeholder
 * closure from PR 1 (`auth()->user()` directly, with NO resource
 * shaping, leaks password/remember_token/etc.). The test asserts
 * the new UserResource shape and fails because the leaked fields
 * (or absence of the new structure) don't match.
 *
 * Once T-API-25 lands (MeController@show + UserResource + route
 * replacement), all 3 scenarios must pass.
 */

it('returns 200 with the user resource shape for a patient actor', function (): void {
    [$user, , ] = $this->createPatientWithToken();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/me');

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
        ->getJson('/api/me');

    $response->assertStatus(200)
        ->assertJsonPath('data.role', 'doctor');
});

it('returns 200 with the user resource shape for an admin actor', function (): void {
    $user = User::factory()->admin()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/me');

    $response->assertStatus(200)
        ->assertJsonPath('data.role', 'admin');
});
