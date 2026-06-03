# Tasks: env-section overhaul

## 1. RED — add 3 failing doc-contract test scenarios

Extend `tests/Feature/Docs/ReadmeApiSurfaceTest.php` (currently 8 scenarios from `agenda-readme-drift` + `agenda-readme-cleanup`) with 3 ADDED scenarios asserting the post-fix README content. Each scenario is a line-precise grep check, following the existing 8 scenarios (obs #76).

- [x] Add scenario 9: README line 8 contains `Laravel 13 (PHP 8.3+)` (not the stale `PHP 8.4+` claim)
- [x] Add scenario 10: README line 347 contains `PHP 8.3+ (per composer.json)` (not the stale `PHP 8.4+` claim with the factually-wrong parenthetical). Sub-assertion: the env section (lines 340-360) does NOT contain `property hooks` or `asymmetric visibility` (negative assertion enforcing the removal of the factually-wrong parenthetical).
- [x] Add scenario 11: README line 350 does NOT contain `greenfield before that needs no DB` (deletion assertion; the phrase is removed entirely)

**Pattern** (one example; the other 2 follow the same structure):
```php
test('README line 8 has correct Stack section PHP claim', function () {
    $lines = file(base_path('README.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read README.md');
    expect($lines[7])->toContain('Laravel 13 (PHP 8.3+)');
});
```

**Pattern for drift 2's negative assertion** (the only non-trivial scenario):
```php
test('README env section does not claim PHP 8.4 features', function () {
    $lines = file(base_path('README.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read README.md');
    expect($lines[346])->toContain('PHP 8.3+ (per composer.json)');
    // Negative assertion: env section must not mention PHP 8.4 features
    $envSection = implode("\n", array_slice($lines, 340, 20));
    expect($envSection)->not->toContain('property hooks');
    expect($envSection)->not->toContain('asymmetric visibility');
});
```

Run the test: `vendor/bin/pest tests/Feature/Docs/ReadmeApiSurfaceTest.php` — expect 3 failures (scenarios 9-11 all fail because the README still has the old content; scenarios 1-8 from prior cycles still pass).

## 2. GREEN — apply 3 README line-level edits

Edit `README.md` to make all 3 RED scenarios pass.

- [x] Edit line 8: `Laravel 13 (PHP 8.4+)` → `Laravel 13 (PHP 8.3+)`
- [x] Edit line 347: `PHP 8.4+ (the project pins to features available in 8.4 — e.g. property hooks, asymmetric visibility)` → `PHP 8.3+ (per composer.json)`
- [x] Edit line 350: remove the `greenfield before that needs no DB` clause (read the surrounding context to identify the exact substring to remove; the result should still be a valid English sentence)

Run the test: `vendor/bin/pest tests/Feature/Docs/ReadmeApiSurfaceTest.php` — expect 0 failures (all 11 scenarios pass: 8 from prior cycles + 3 new).

## 3. VERIFY — full test suite + cumulative state

- [x] Run full test suite on SQLite: `php artisan test --testsuite=Feature,Unit` (or `vendor/bin/pest`) — expect 144 passed (141+3) + 4 skipped (MariaDB-only race tests)
- [x] Run full test suite on MariaDB: switch `.env` to MariaDB and re-run — expect 148 passed (145+3) + 0 skipped
- [x] Verify route count: `php artisan route:list --path=api | wc -l` — expect 18 (3 auth + 15 public, unchanged)
- [x] Verify the new `agenda/env-section-overhaul` sub-capability spec is in `openspec/changes/env-section-overhaul/specs/agenda/env-section-overhaul/spec.md` (created by sdd-spec phase before sdd-apply)
- [x] Verify no other spec/canonical file was touched
- [ ] Commit, ff-merge to main (no push, per AGENTS.md)
- [ ] Final commit on main: `chore(archive): sync env-section-overhaul ADDED scenarios into canonical` (created by sdd-archive phase)
