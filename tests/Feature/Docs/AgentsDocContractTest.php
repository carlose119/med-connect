<?php

declare(strict_types=1);

/**
 * Doc-contract test: openspec/AGENTS.md (the project's PRD and workflow
 * contract) MUST accurately reflect the current project state for 3 specific
 * items. Drift in any claim is a test failure.
 *
 * REQ-PRD-UPDATE-1 (openspec/changes/agenda-prd-update/specs/agenda/prd-update/spec.md):
 *   Closes 3 drifts accumulated since the env-section-overhaul cycle
 *   (archived 6455b86) which closed the README.md version of these drifts.
 *   AGENTS.md is a different file from README.md (it's the developer contract,
 *   not the user-facing docs), and was explicitly out of scope for that cycle.
 *
 *   1. Stack section PHP claim (line 69) — overstates composer.json `^8.3` to `^8.4+`.
 *   2. Stack section Pest claim (line 75) — claims Pest 3 but the project uses Pest 4.7.1.
 *   3. Unique partial index syntax (line 58) — CORRECTNESS drift: only the
 *      PostgreSQL/SQLite form is described, but the actual migration is
 *      driver-aware (MariaDB/MySQL: generated cancelled_marker + UNIQUE KEY).
 *
 * 1-indexed line numbers locked in agenda-prd-update/proposal.md §"What changes".
 */
it('AGENTS.md line 69 has correct Stack section PHP claim (PHP 8.3+, not 8.4+)', function () {
    $lines = file(base_path('openspec/AGENTS.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read AGENTS.md');
    // 1-indexed line 69 = 0-indexed $lines[68]. Spec scenario 1 (REQ-PRD-UPDATE-1)
    // requires the Stack section to claim `Laravel 13 (PHP 8.3+)` matching composer.json.
    expect($lines[68])->toContain('Laravel 13 (PHP 8.3+)');
});

it('AGENTS.md line 75 has correct Stack section Pest version (Pest 4, not Pest 3)', function () {
    $lines = file(base_path('openspec/AGENTS.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read AGENTS.md');
    // 1-indexed line 75 = 0-indexed $lines[74]. Spec scenario 2 (REQ-PRD-UPDATE-1)
    // requires the Stack section to claim `Pest 4 (modern Laravel default)` matching
    // composer.json `"pestphp/pest": "4.7.1"`.
    expect($lines[74])->toContain('Pest 4 (modern Laravel default)');
});

it('AGENTS.md line 58 has correct driver-aware unique partial index description', function () {
    $lines = file(base_path('openspec/AGENTS.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read AGENTS.md');
    // 1-indexed line 58 = 0-indexed $lines[57]. Spec scenario 3 (REQ-PRD-UPDATE-1)
    // requires the new wording to be driver-aware, mentioning BOTH:
    //   - MariaDB/MySQL: generated `cancelled_marker` column + UNIQUE KEY
    //     (verified in database/migrations/2026_06_01_000007_create_appointments_table.php)
    //   - PostgreSQL/SQLite: partial unique index with `WHERE status !=`
    // The 4 sub-string assertions below are content validation, not absence-of-old:
    // removing any of the 4 from the new wording fails the test.
    $line = $lines[57];
    expect($line)->toContain('MariaDB');
    expect($line)->toContain('PostgreSQL');
    expect($line)->toContain('cancelled_marker');
    expect($line)->toContain('WHERE status !=');
});
