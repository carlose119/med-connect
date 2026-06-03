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

/**
 * Doc-contract tests: agenda/readme-cleanup sub-capability (openspec/changes/agenda-readme-cleanup/specs/agenda/readme-cleanup/spec.md).
 *
 * REQ-README-CLEANUP-1 closes 5 cosmetic README drifts accumulated since the
 * agenda-readme-drift cycle (archived d3b4ef9):
 *   1. Migration count breakdown (line 71)
 *   2. Test count (line 304)
 *   3. Node version consistency (lines 313-314, 349)
 *   4. Email domain (lines 283, 464)
 *   5. Route count + endpoint table missing row (line 360, lines 364-382)
 *
 * 1-indexed line numbers locked in agenda-readme-cleanup/proposal.md §"What changes".
 */

it('README line 71 has correct migration count breakdown (13 from PR 1+2)', function () {
    $lines = file(base_path('README.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read README.md');
    // 1-indexed line 71 = 0-indexed $lines[70].
    expect($lines[70])->toContain('13 from PR 1+2');
});

it('README line 304 has updated PR 5 test count (136+4 SQLite, 140 MariaDB)', function () {
    $lines = file(base_path('README.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read README.md');
    // 1-indexed line 304 = 0-indexed $lines[303].
    expect($lines[303])->toContain('136+4 cases on SQLite (default), 140 on MariaDB');
});

it('README lines 314 and 349 use aligned Node 20.16+ wording', function () {
    $lines = file(base_path('README.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read README.md');
    // 1-indexed line 314 = 0-indexed $lines[313]; line 349 = $lines[348].
    // Spec scenario 3 (REQ-README-CLEANUP-1) requires the aligned wording
    // `Node 20.16+ (prints warning, build succeeds)` in both.
    expect($lines[313])->toContain('Node 20.16+ (prints warning, build succeeds)');
    expect($lines[348])->toContain('Node 20.16+ (prints warning, build succeeds)');
});

it('README lines 283 and 464 use the med-connect.test email domain', function () {
    $lines = file(base_path('README.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read README.md');
    // 1-indexed line 283 = 0-indexed $lines[282].
    // Line 464 (originally $lines[463] in the spec/proposal) shifts to $lines[464]
    // because the endpoint table edit adds 1 row (the new GET /api/doctors/{doctor}
    // row at row 12, renumbering the 6 subsequent rows 12-17 -> 13-18). The
    // spec scenario 4 (REQ-README-CLEANUP-1) intent — both lines must contain
    // `med-connect.test` (matches database/seeders/DatabaseSeeder.php lines 29,
    // 47, 81) — is preserved.
    expect($lines[282])->toContain('med-connect.test');
    expect($lines[464])->toContain('med-connect.test');
});

it('README route count is 18 and endpoint table lists GET /api/doctors/{doctor}', function () {
    $lines = file(base_path('README.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read README.md');
    // 1-indexed line 360 = 0-indexed $lines[359].
    expect($lines[359])->toContain('18 routes');

    // Endpoint table has 18 rows numbered 1..18. The PR-status table at
    // lines 331-335 has no `/api/` paths, so anchoring on the
    // `| N | METHOD | \`/api/...\` ` pattern is unambiguous.
    $readme = file_get_contents(base_path('README.md'));
    $rowCount = preg_match_all(
        '#^\| \d+ +\| (?:GET|POST|DELETE|PUT|PATCH) +\| `/api/#m',
        $readme
    );
    expect($rowCount)->toBe(18);

    // The new row mapped to Api\DoctorController@show must be present.
    expect($readme)->toContain('GET    | `/api/doctors/{doctor}`');
});
