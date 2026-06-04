<?php

declare(strict_types=1);

/**
 * Doc-contract test: the agenda/api spec REQ-API-7 lines 281 + 286 MUST
 * describe the DoctorResource wire shape with `name` nested inside `user`
 * (NOT as a flat top-level `name` field).
 *
 * REQ-API-7 (openspec/changes/agenda-resource-shape):
 *   "The GET /api/doctors and GET /api/doctors/{id} endpoints MUST return
 *    a wire shape that nests `name` inside `user` (not at the top level),
 *    reflecting the actual DoctorResource::toArray() output: id, user_id,
 *    specialty_id, license_number, bio, user{id,name,email},
 *    specialty{id,name,slug}."
 *
 * The test uses line-precise file reads (1-indexed line N = $lines[N-1]),
 * NOT the raw `user{id,name,email}` substring (which would also match the
 * nested shape itself and inflate the count). The negative assertion uses
 * a bounded 20-line window around the drift lines (array_slice+implode
 * pattern from env-section-overhaul drift 2, ReadmeApiSurfaceTest.php
 * lines 155-174) to defend against substring leakage from elsewhere in
 * REQ-API-7. The negative-assertion substring is `, `name`,` (with
 * markdown backticks) — the spec source text wraps the field name in
 * backticks for inline code, so the raw `, name,` substring from the
 * proposal/spec prose is not present in the actual file. The corrected
 * substring makes the test a REAL assertion (RED on pre-edit spec, GREEN
 * on post-edit spec).
 *
 * The line numbers are locked at 281 + 286 via DRIFT_STALE_LINES to make
 * the test fail loudly if the spec shifts (per agenda-spec-drift
 * SPEC_DRIFT_STALE_LINES precedent).
 *
 * Sibling of tests/Feature/Docs/AgendaApiSpecCanonicalRoutesTest.php,
 * which closes a different drift in the same agenda/api spec.
 */

const DRIFT_STALE_LINES = [281, 286];

it('agenda/api REQ-API-7 line 281 describes the list row wire shape with user nested', function () {
    $lines = file(base_path('openspec/specs/agenda/api/spec.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read openspec/specs/agenda/api/spec.md');
    // 1-indexed line 281 = 0-indexed $lines[280]. The list-row Then clause
    // MUST include the nested user shape (user{id,name,email}) and MUST NOT
    // include the old flat `, `name`,` claim between `id` and the next
    // field (markdown backticks surround the field name in the spec).
    expect($lines[280])->toContain('user{id,name,email}');
    expect($lines[280])->not->toContain(', `name`,');
});

it('agenda/api REQ-API-7 line 286 describes the detail body wire shape with user nested', function () {
    $lines = file(base_path('openspec/specs/agenda/api/spec.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read openspec/specs/agenda/api/spec.md');
    // 1-indexed line 286 = 0-indexed $lines[285]. The detail-body Then
    // clause MUST include the nested user shape and MUST NOT include the
    // old flat `, `name`,` claim between `id` and the next field.
    expect($lines[285])->toContain('user{id,name,email}');
    expect($lines[285])->not->toContain(', `name`,');
});

it('agenda/api REQ-API-7 doctor scenarios do not claim a top-level name field', function () {
    $content = file_get_contents(base_path('openspec/specs/agenda/api/spec.md'));
    expect($content)->not->toBeFalse('Could not read openspec/specs/agenda/api/spec.md');
    // Negative assertion (defense-in-depth): a 20-line window around the
    // drift lines (1-indexed 276-295 = 0-indexed 275..294, so
    // array_slice offset 275 length 20) MUST NOT contain the literal
    // `, `name`,` substring — the old flat top-level name claim. The
    // substring is unique to lines 281 + 286 in the current spec (the
    // `,name,` inside `user{id,name,email}` is NOT in backticks, so it
    // doesn't match). The window covers both drift lines + 17 lines of
    // padding.
    $lines = explode("\n", $content);
    $window = implode("\n", array_slice($lines, 275, 20));
    expect($window)->not->toContain(', `name`,');
});
