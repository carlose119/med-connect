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
    // 1-indexed line 314 = 0-indexed $lines[313] (unchanged: before line 323).
    // 1-indexed line 349 shifts to $lines[357] after the +9 line shift from the
    // 21→30 line refactor of the ## Status section (agenda-readme-revamp cycle).
    // Spec scenario 3 (REQ-README-CLEANUP-1) requires the aligned wording
    // `Node 20.16+ (prints warning, build succeeds)` in both.
    expect($lines[313])->toContain('Node 20.16+ (prints warning, build succeeds)');
    expect($lines[357])->toContain('Node 20.16+ (prints warning, build succeeds)');
});

it('README lines 283 and 464 use the med-connect.test email domain', function () {
    $lines = file(base_path('README.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read README.md');
    // 1-indexed line 283 = 0-indexed $lines[282] (unchanged: before line 323).
    // 1-indexed line 464 (originally $lines[463] in the spec/proposal) shifted to
    // $lines[464] when the agenda-readme-cleanup endpoint table edit added 1 row,
    // and now shifts to $lines[473] after the +9 line shift from the
    // 21→30 line refactor of the ## Status section (agenda-readme-revamp cycle).
    // The spec scenario 4 (REQ-README-CLEANUP-1) intent — both lines must contain
    // `med-connect.test` (matches database/seeders/DatabaseSeeder.php lines 29,
    // 47, 81) — is preserved.
    expect($lines[282])->toContain('med-connect.test');
    expect($lines[473])->toContain('med-connect.test');
});

it('README route count is 18 and endpoint table lists GET /api/doctors/{doctor}', function () {
    $lines = file(base_path('README.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read README.md');
    // 1-indexed line 360 shifts to $lines[368] after the +9 line shift from the
    // 21→30 line refactor of the ## Status section (agenda-readme-revamp cycle).
    expect($lines[368])->toContain('18 routes');

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

/**
 * Doc-contract tests: agenda/env-section-overhaul sub-capability
 * (openspec/changes/env-section-overhaul/specs/agenda/env-section-overhaul/spec.md).
 *
 * REQ-ENV-SECTION-OVERHAUL-1 closes 3 env-section drifts accumulated since
 * the agenda-readme-cleanup cycle (archived cb1f2d3):
 *   1. Stack section PHP claim overstates composer.json `^8.3` (line 8)
 *   2. Environment section PHP claim + factually-wrong parenthetical
 *      (line 347, includes a negative assertion banning
 *      `property hooks` and `asymmetric visibility` from the env section)
 *   3. Stale "greenfield before that needs no DB" phraseology (line 350)
 *
 * 1-indexed line numbers locked in env-section-overhaul/proposal.md §"What changes".
 */

it('README line 8 has correct Stack section PHP claim (PHP 8.3+, not 8.4+)', function () {
    $lines = file(base_path('README.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read README.md');
    // 1-indexed line 8 = 0-indexed $lines[7]. Spec scenario 1 (REQ-ENV-SECTION-OVERHAUL-1)
    // requires the Stack section to claim `Laravel 13 (PHP 8.3+)` matching composer.json.
    expect($lines[7])->toContain('Laravel 13 (PHP 8.3+)');
});

it('README line 347 has correct Environment PHP claim and env section omits PHP 8.4 features', function () {
    $lines = file(base_path('README.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read README.md');
    // 1-indexed line 347 shifts to $lines[355] after the +9 line shift from the
    // 21→30 line refactor of the ## Status section (agenda-readme-revamp cycle).
    // Spec scenario 2 (REQ-ENV-SECTION-OVERHAUL-1) requires the env section to
    // claim `PHP 8.3+ (per composer.json)` (not the stale
    // `PHP 8.4+ (the project pins to features available in 8.4 ...)` claim).
    expect($lines[355])->toContain('PHP 8.3+ (per composer.json)');

    // Negative assertion (defense-in-depth): the env section (a 20-line window
    // around line 347) MUST NOT contain the factually-wrong phraseology that
    // claims PHP 8.4 features are used. Verified 0 matches for `property hooks`
    // and `asymmetric visibility` in app/ (per proposal §"What changes" drift 2).
    // The window start index shifts from 340 to 349 to track the +9 line shift.
    $envSection = implode("\n", array_slice($lines, 349, 20));
    expect($envSection)->not->toContain('property hooks');
    expect($envSection)->not->toContain('asymmetric visibility');
});

it('README line 350 omits the stale greenfield phraseology', function () {
    $lines = file(base_path('README.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read README.md');
    // 1-indexed line 350 shifts to $lines[358] after the +9 line shift from the
    // 21→30 line refactor of the ## Status section (agenda-readme-revamp cycle).
    // The previous anchor ($lines[349]) silently tested a roadmap bullet, which
    // did not contain the greenfield phrase and thus passed by accident. The
    // TEST-FIX restores the actual env-section assertion. Spec scenario 3
    // (REQ-ENV-SECTION-OVERHAUL-1) requires the env section to NOT contain
    // `greenfield before that needs no DB` — that phrase is from the
    // pre-agenda-core era; after agenda-core was archived (commits 0a17b3c,
    // e2ecc74) the project always needs a DB.
    expect($lines[358])->not->toContain('greenfield before that needs no DB');
});

/**
 * Doc-contract tests: agenda/readme-revamp sub-capability
 * (openspec/changes/agenda-readme-revamp/specs/agenda/readme-revamp/spec.md).
 *
 * REQ-README-REVAMP-1 closes 3 drifts in the README.md ## Status section
 * (lines 323-343) accumulated since the agenda-core chained split was
 * archived 9 cycles ago (commits 0a17b3c, e2ecc74):
 *   1. Stale `## Status — agenda-core` subtitle (line 323)
 *   2. Flat 21-line prose block with no per-concern organization
 *   3. Stale `**Feature-complete pending sdd-verify.**` text + obsolete
 *      `sdd-verify` next-step paragraph (lines 325-343)
 *
 * 1-indexed line numbers locked in agenda-readme-revamp/proposal.md §"What changes".
 */

it('README line 323 has bare `## Status` heading (no agenda-core subtitle)', function () {
    $lines = file(base_path('README.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read README.md');
    // 1-indexed line 323 = 0-indexed $lines[322]. Spec scenario 1 (REQ-README-REVAMP-1)
    // requires the Status section heading to be exactly `## Status` (no subtitle).
    // The stale `## Status — agenda-core` is from the frozen cycle-1 chained split.
    expect($lines[322])->toBe('## Status');
});

it('README Status section has 4 h3 subsections in order (Build, Test, SDD state, Roadmap)', function () {
    $lines = file(base_path('README.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read README.md');
    // Status section starts at 1-indexed line 323 (0-indexed 322).
    // Bound the section by finding the next h2 (## ...) heading.
    $startIndex = 322;
    $endIndex = count($lines);
    for ($i = $startIndex + 1; $i < count($lines); $i++) {
        if (preg_match('/^## [^#]/', $lines[$i])) {
            $endIndex = $i;
            break;
        }
    }
    $section = array_slice($lines, $startIndex, $endIndex - $startIndex);
    $headings = [];
    foreach ($section as $line) {
        if (preg_match('/^### (.+)$/', $line, $m)) {
            $headings[] = trim($m[1]);
        }
    }
    // Spec scenario 2 (REQ-README-REVAMP-1) requires exactly 4 h3s in this
    // exact order: Build status, Test status, SDD state, Roadmap.
    expect($headings)->toBe(['Build status', 'Test status', 'SDD state', 'Roadmap']);
});

it('README Status section omits stale `Feature-complete pending sdd-verify` text', function () {
    $lines = file(base_path('README.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read README.md');
    // Slice a 40-line window starting at line 323 (0-indexed 322) to bound
    // the Status section. The 40-line window covers the new ~30-line
    // section (lines 323-352) plus 8 lines of padding.
    $statusSection = implode("\n", array_slice($lines, 322, 40));
    // Spec scenario 3 (REQ-README-REVAMP-1) requires the Status section
    // to NOT contain the stale `Feature-complete pending` and `sdd-verify`
    // text from the frozen cycle-1 chained split.
    expect($statusSection)->not->toContain('Feature-complete pending');
    expect($statusSection)->not->toContain('sdd-verify');
});
