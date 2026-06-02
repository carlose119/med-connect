<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class);

/**
 * PR 4 — agenda-http — POST /api/auth/logout (REQ-API-7 scenario 3).
 *
 * RED at this commit: the route does not exist yet, so the
 * scenario returns 404 (with `error.code = ROUTE_NOT_FOUND` per the
 * PR 1 exception handler). The test asserts status 204 + asserts
 * the personal_access_tokens table is empty (the token was
 * deleted). Both must fail until T-API-43 lands
 * `AuthController@logout` + the `POST /api/auth/logout` route.
 */
it('returns 204 and deletes the current access token from personal_access_tokens', function (): void {
    [$user, , $token] = $this->createPatientWithToken();

    // Sanity check: the fixture minted one token.
    expect(DB::table('personal_access_tokens')->count())->toBe(1);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/auth/logout');

    $response->assertStatus(204);
    expect($response->getContent())->toBe('');

    // The token row must be gone. This is the real assertion:
    // calling the route must hit the DB and remove the row.
    expect(DB::table('personal_access_tokens')->count())->toBe(0);
});

it('returns 401 UNAUTHENTICATED when not authenticated', function (): void {
    // No Authorization header — the route is gated by auth:sanctum
    // and must return 401 via the PR 1 exception handler.
    $this->postJson('/api/auth/logout')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});
