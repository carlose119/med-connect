<?php

declare(strict_types=1);

/**
 * Doc-contract test: the agenda/api spec REQ-API-7 MUST list exactly one
 * scenario for the GET /api/auth/me endpoint, with the canonical form
 * (Given "an authenticated user" + When `... with the bearer token`).
 *
 * REQ-API-DEDUP-1 (openspec/changes/agenda-api-dedup):
 *   "The agenda/api spec REQ-API-7 MUST list exactly one scenario for
 *    GET /api/auth/me. The post-archive duplicate created by the
 *    agenda-spec-drift cycle (any-authenticated-user form, no bearer
 *    token) is REMOVED. The kept scenario at the original line 213
 *    (an-authenticated-user form, with the bearer token) is the canonical
 *    form and is preserved."
 *
 * The test uses scenario-heading-anchored regex (`^#### Scenario: GET
 * /api/auth/me`), NOT the raw `GET /api/auth/me` substring (which would
 * also match each scenario's When clause and inflate the count).
 *
 * Backed by the `agenda/api-dedup` delta spec
 * (openspec/changes/agenda-api-dedup/specs/agenda/api/spec.md).
 *
 * Sibling of tests/Feature/Docs/ReadmeApiSurfaceTest.php, which closes the
 * same drift family in README.md (agenda-readme-drift archive at d3b4ef9).
 */
it('has exactly 1 GET /api/auth/me scenario in REQ-API-7', function () {
    $spec = file_get_contents(base_path('openspec/specs/agenda/api/spec.md'));
    expect($spec)->not->toBeFalse('Could not read openspec/specs/agenda/api/spec.md');

    // Scenario-heading-anchored: each `#### Scenario: GET /api/auth/me`
    // heading counts as exactly one scenario. The raw `GET /api/auth/me`
    // substring would also match the When clause of each scenario, which
    // would inflate the count and break the dedup contract.
    $count = preg_match_all('/^#### Scenario: GET \/api\/auth\/me/m', $spec);

    expect($count)->toBe(1, "Expected exactly 1 GET /api/auth/me scenario heading in REQ-API-7, found {$count}");
});

it('preserves the canonical scenario with the bearer token (kept scenario)', function () {
    $spec = file_get_contents(base_path('openspec/specs/agenda/api/spec.md'));
    expect($spec)->not->toBeFalse('Could not read openspec/specs/agenda/api/spec.md');

    // The kept scenario is the canonical Sanctum form: it MUST mention
    // `with the bearer token` in its When clause. Regression anchor: this
    // test passes on main and after GREEN.
    expect($spec)
        ->toContain('#### Scenario: GET /api/auth/me returns 200 with the current user')
        ->toContain('- **When** the client calls `GET /api/auth/me` with the bearer token')
        ->toContain('- **Given** an authenticated user');
});

it('does not have a second GET /api/auth/me scenario with the any-authenticated-user form', function () {
    $spec = file_get_contents(base_path('openspec/specs/agenda/api/spec.md'));
    expect($spec)->not->toBeFalse('Could not read openspec/specs/agenda/api/spec.md');

    // The post-archive duplicate is uniquely identified by the Given
    // clause `any authenticated user` (no article `an`) followed by a When
    // clause `the client calls \`GET /api/auth/me\`` that does NOT include
    // `with the bearer token`. The kept scenario uses `an authenticated
    // user` (with the article) AND `with the bearer token`.
    expect($spec)
        ->not->toContain("- **Given** any authenticated user\n- **When** the client calls `GET /api/auth/me`");
});
