<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class);

/**
 * PR 4 — agenda-http — POST /api/auth/login (REQ-API-7 scenarios 1 + 2).
 *
 * RED at this commit: the route does not exist yet, so both
 * scenarios return 404 (with `error.code = ROUTE_NOT_FOUND` per the
 * PR 1 exception handler). The happy-path assertion looks for
 * `data.user.id` / `data.token`; the failure-path assertion looks
 * for `error.code = UNAUTHENTICATED`. Both must fail until T-API-41
 * lands the controller + request + route.
 *
 * Once T-API-41 lands (`AuthController@login` + `LoginRequest` +
 * the `POST /api/auth/login` route registered outside the
 * `auth:sanctum` group but inside the `ResolveTimezone` group), both
 * scenarios must pass.
 */

it('returns 200 with {data.user, data.token} for valid credentials', function (): void {
    $user = User::factory()->patient()->create([
        'password' => bcrypt('correct-password'),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email', 'role'],
                'token',
            ],
        ])
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonPath('data.user.role', 'patient');

    // The token is a Sanctum plaintext (format: "id|secret"); the
    // test only asserts it's a non-empty string, not a specific value
    // (Sanctum tokens are randomly generated and not deterministic).
    $token = $response->json('data.token');
    expect($token)->toBeString();
    expect($token)->not->toBeEmpty();
});

it('returns 401 UNAUTHENTICATED for bad credentials', function (): void {
    $user = User::factory()->patient()->create([
        'password' => bcrypt('correct-password'),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});
