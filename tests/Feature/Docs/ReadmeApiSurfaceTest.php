<?php

declare(strict_types=1);

/**
 * Doc-contract test: README.md API surface must match the canonical routes
 * documented in openspec/specs/agenda/api/spec.md REQ-API-7.
 *
 * REQ-README-DRIFT-1 (openspec/changes/agenda-readme-drift):
 *   "The README.md file at the project root MUST NOT contain any reference
 *    to the retired placeholder route /api/me ... it MUST use the canonical
 *    paths from REQ-API-7: POST /api/auth/login, POST /api/auth/logout, and
 *    GET /api/auth/me."
 *
 * Backed by the `agenda/readme-drift` delta spec (openspec/changes/agenda-readme-drift/specs/agenda/readme-drift/spec.md).
 */

// 1-indexed line numbers, locked in agenda-readme-drift/proposal.md §Scope.
const README_DRIFT_STALE_LINES = [368, 394, 417, 421, 510];

it('contains no retired /api/me references in README.md at lines 368, 394, 417, 421, 510', function () {
    $lines = file(base_path('README.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read README.md');

    foreach (README_DRIFT_STALE_LINES as $lineNumber) {
        // 1-indexed in the spec; arrays are 0-indexed.
        $content = $lines[$lineNumber - 1];

        // Line-precise check (not substring) so the legitimate
        // `/api/medical-histories/{id}` on line 380 is not a false positive.
        expect($content)
            ->not->toContain('/api/me');
    }
});

it('lists the canonical auth routes in the endpoint table', function () {
    $readme = file_get_contents(base_path('README.md'));

    // Exact pipe-and-padding form per README lines 366-368.
    expect($readme)->toContain('POST   | `/api/auth/login`');
    expect($readme)->toContain('POST   | `/api/auth/logout`');
    expect($readme)->toContain('GET    | `/api/auth/me`');
});

it('uses canonical /api/auth/me in curl examples', function () {
    $readme = file_get_contents(base_path('README.md'));

    // Expect 3 hits: auth flow (line 394) + default-TZ (line 417) + override-TZ (line 421).
    // - `[\s\S]*?` (any char including newlines, non-greedy) captures multi-line
    //   curl commands that use `\` line-continuation (the default-TZ and
    //   override-TZ curls span 2 lines each).
    // - `https?://[^\s\n]+/api/auth/me\b` requires an actual URL, so the
    //   `POST /api/auth/login` curl in the Curl examples section does not
    //   produce a false positive by bleeding into the PR 3 prose mention of
    //   `/api/auth/me` further down.
    $hits = preg_match_all('#curl[\s\S]*?https?://[^\s\n]+/api/auth/me\b#', $readme);
    expect($hits)->toBeGreaterThanOrEqual(3);
});
