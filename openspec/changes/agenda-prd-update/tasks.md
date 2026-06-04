# Tasks: agenda-prd-update

## 1. RED — create new test class with 3 failing doc-contract test scenarios

Create `tests/Feature/Docs/AgentsDocContractTest.php` (NEW test class, parallel to `ReadmeApiSurfaceTest.php` but for AGENTS.md — per-file separation convention). Add 3 failing test scenarios asserting the post-fix AGENTS.md content.

- [x] Create the new test class file at `tests/Feature/Docs/AgentsDocContractTest.php` with the right namespace and use statements
- [x] Add scenario 1: AGENTS.md line 69 contains `Laravel 13 (PHP 8.3+)` (not the stale `PHP 8.4+` claim)
- [x] Add scenario 2: AGENTS.md line 75 contains `Pest 4 (modern Laravel default)` (not the stale `Pest 3` claim)
- [x] Add scenario 3: AGENTS.md line 58 contains BOTH `MariaDB` (or `MySQL`) AND `PostgreSQL` (or `SQLite`) AND `cancelled_marker` AND `WHERE status != 'cancelled'` (the driver-aware wording, asserted via 4 sub-string checks)

**Pattern** (one example for the simple cases; drift 3 follows a different pattern):
```php
test('AGENTS.md line 69 has correct Stack section PHP claim', function () {
    $lines = file(base_path('openspec/AGENTS.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read AGENTS.md');
    expect($lines[68])->toContain('Laravel 13 (PHP 8.3+)');
});
```

**Pattern for drift 3** (more complex; asserts multiple sub-strings for the driver-aware wording):
```php
test('AGENTS.md line 58 has correct driver-aware unique partial index description', function () {
    $lines = file(base_path('openspec/AGENTS.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read AGENTS.md');
    $line = $lines[57];
    // The new wording must mention both driver forms (defense-in-depth)
    expect($line)->toContain('MariaDB');
    expect($line)->toContain('PostgreSQL');
    expect($line)->toContain('cancelled_marker');
    expect($line)->toContain('WHERE status !=');
});
```

Run the test: `vendor/bin/pest tests/Feature/Docs/AgentsDocContractTest.php` — expect 3 failures (the new scenarios all fail because AGENTS.md still has the old content).

## 2. GREEN — apply 3 AGENTS.md line-level edits

Edit `openspec/AGENTS.md` to make all 3 RED scenarios pass.

- [x] Edit line 69: `Backend: Laravel 13 (PHP 8.4+)` → `Backend: Laravel 13 (PHP 8.3+)`
- [x] Edit line 75: `Pest 3 (modern Laravel default)` → `Pest 4 (modern Laravel default)`
- [x] Edit line 58: replace `Unique partial index (doctor_id, start_time) WHERE status != 'cancelled'` with a driver-aware description. Suggested wording: `Driver-aware unique constraint on (doctor_id, start_time) where status != 'cancelled': MariaDB/MySQL use a generated cancelled_marker column + UNIQUE KEY; PostgreSQL/SQLite use a partial unique index with WHERE`

Run the test: `vendor/bin/pest tests/Feature/Docs/AgentsDocContractTest.php` — expect 0 failures (all 3 scenarios pass).

## 3. VERIFY — full test suite + cumulative state

- [x] Run full test suite on SQLite: `vendor/bin/pest` — expect 147 passed (144+3) + 4 skipped (MariaDB-only race tests, unchanged)
- [ ] Run full test suite on MariaDB: switch `.env` to MariaDB and re-run — expect 151 passed (148+3) + 0 skipped (NOTE: MariaDB service was unavailable in this session; SQLite 147 + 4 skipped matches the contract — 4 MariaDB-only race tests are unchanged from baseline)
- [x] Verify the new `agenda/prd-update` sub-capability spec is in `openspec/changes/agenda-prd-update/specs/agenda/prd-update/spec.md` (created by sdd-spec phase before sdd-apply)
- [x] Verify no other spec/canonical file was touched (`git diff 6455b86..HEAD -- openspec/specs/` should return 0 lines)
- [x] Verify route count: 18 unchanged (`php artisan route:list --path=api --json | ConvertFrom-Json | Measure-Object` per obs #87 deviation note)
- [ ] Commit, ff-merge to main (no push, per AGENTS.md)
- [ ] Final commit on main: `chore(archive): sync agenda-prd-update ADDED scenarios into canonical` (created by sdd-archive phase)
- [x] Mark this `tasks.md` file's [x] items (housekeeping, per obs #66 / obs #87 4-commit pattern)
