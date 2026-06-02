<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * PR 1 — agenda-http — Sanctum bearer auth + ResolveTimezone
 * integration on /api/auth/me (REQ-API-1 + REQ-API-2 + REQ-API-5).
 *
 * The route group [ResolveTimezone, auth:sanctum] must:
 *   - reject requests without a token (401 with code UNAUTHENTICATED)
 *   - accept requests with a valid Sanctum token (200 + the user)
 *   - honor the ?tz= override without throwing (200 with the override
 *     resolved; the format-level assertion is in PR 3)
 *
 * TDD exception (T-API-7, per tasks.md): red+green ship in one
 * commit because the route registration is trivial and the test
 * verifies both halves. Splitting into 2 commits would be artificial.
 *
 * PR 4 update: the canonical path is now /api/auth/me (was /api/me
 * in PR 1 + PR 3). The /api/me placeholder was retired in T-API-46.
 */
it('returns 401 UNAUTHENTICATED when no Sanctum token is provided', function (): void {
    $response = $this->getJson('/api/auth/me');

    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});

it('returns 200 with the user when a valid Sanctum token is provided', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/auth/me')
        ->assertStatus(200)
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email);
});

it('honors the ?tz= query param on the /api/auth/me route (200, no throw)', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/auth/me?tz=America/New_York')
        ->assertStatus(200)
        ->assertJsonPath('data.id', $user->id);
});

it('rejects an invalid ?tz= value with 422 INVALID_TIMEZONE', function (): void {
    $user = User::factory()->create();

    // The middleware order is [ResolveTimezone, auth:sanctum] so the
    // tz check runs first (N7: 401 must also be in the requested TZ).
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/auth/me?tz=Atlantis/Mu')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'INVALID_TIMEZONE');
});
