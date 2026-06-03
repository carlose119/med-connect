<?php

declare(strict_types=1);

/**
 * Doc-contract test: the agenda/api spec MUST reference the canonical
 * /api/auth/me route, not the retired placeholder /api/me, in REQ-API-7.
 *
 * REQ-SPEC-DRIFT-1 (openspec/changes/agenda-spec-drift):
 *   "The scenario at lines 313-316 of openspec/specs/agenda/api/spec.md MUST
 *    use the canonical GET /api/auth/me route in both its heading and its
 *    When clause, matching the route already documented at line 213 and the
 *    route the codebase exposes."
 *
 * Backed by the `agenda/api-spec-drift` delta spec
 * (openspec/changes/agenda-spec-drift/specs/agenda/api/spec.md).
 *
 * Sibling of tests/Feature/Docs/ReadmeApiSurfaceTest.php, which closes the
 * same drift family in README.md (agenda-readme-drift archive at d3b4ef9).
 */

// 1-indexed line numbers, locked in agenda-spec-drift/proposal.md §Scope.
const SPEC_DRIFT_STALE_LINES = [313, 315];

it('uses canonical /api/auth/me in the scenario heading at line 313', function () {
    $lines = file(base_path('openspec/specs/agenda/api/spec.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read openspec/specs/agenda/api/spec.md');

    $content = $lines[SPEC_DRIFT_STALE_LINES[0] - 1];

    expect($content)
        ->toContain('/api/auth/me')
        ->not->toContain('/api/me ');
});

it('uses canonical /api/auth/me in the When clause at line 315', function () {
    $lines = file(base_path('openspec/specs/agenda/api/spec.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read openspec/specs/agenda/api/spec.md');

    $content = $lines[SPEC_DRIFT_STALE_LINES[1] - 1];

    expect($content)
        ->toContain('/api/auth/me')
        ->not->toContain('/api/me`');
});

it('preserves the rest of the spec at lines 300-340', function () {
    $lines = file(base_path('openspec/specs/agenda/api/spec.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read openspec/specs/agenda/api/spec.md');

    // Surrounding lines that the fix MUST NOT touch.
    $frozen = [
        312 => '',
        314 => '- **Given** any authenticated user',
        316 => '- **Then** the response is `200` and the body is `{"data":{"id","name","email","role"}}`',
        318 => '#### Scenario: GET /api/specialties returns 200 with the active specialty list',
        319 => '- **Given** an authenticated user',
        320 => '- **When** the client calls `GET /api/specialties?active=true`',
    ];

    foreach ($frozen as $lineNumber => $expected) {
        expect($lines[$lineNumber - 1])->toBe(
            $expected,
            "Line {$lineNumber} drifted from its pre-fix content"
        );
    }
});
