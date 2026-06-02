<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class);

/**
 * PR 4 — agenda-http — Expired Sanctum token returns 401 TOKEN_EXPIRED
 * (REQ-API-1 scenario 3).
 *
 * RED at this commit: the ErrorResponse helper does not distinguish
 * TOKEN_EXPIRED from UNAUTHENTICATED. The AuthenticationException
 * branch always returns 'UNAUTHENTICATED' (lines 65-67 in the
 * current ErrorResponse.php). The test asserts the spec-required
 * code TOKEN_EXPIRED and fails.
 *
 * Once T-API-48 lands (ErrorResponse resolves TOKEN_EXPIRED for
 * expired Sanctum tokens by inspecting personal_access_tokens via
 * Laravel\Sanctum\PersonalAccessToken::findToken()), the test must
 * pass.
 *
 * Implementation note: Sanctum's HasApiTokens::createToken() does
 * NOT set expires_at by default. The test manually updates
 * personal_access_tokens.expires_at to a past timestamp via
 * DB::table(...)->update([...]) so the production code path
 * exercises the expired branch.
 */
it('returns 401 TOKEN_EXPIRED for a Sanctum token whose expires_at is in the past', function (): void {
    [$user, , $token] = $this->createPatientWithToken();

    // Sanity check: the freshly-minted token has expires_at == null
    // (Sanctum's default; long-lived bearer tokens).
    $row = DB::table('personal_access_tokens')->first();
    expect($row->expires_at)->toBeNull();

    // Set expires_at to a past timestamp. This is the precondition
    // for the TOKEN_EXPIRED branch in ErrorResponse.
    DB::table('personal_access_tokens')->update(['expires_at' => now()->subDay()]);

    // We send the bearer header explicitly (not actingAs) so the
    // production code path goes through Sanctum's token lookup and
    // expires_at check.
    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/auth/me');

    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'TOKEN_EXPIRED');
});
