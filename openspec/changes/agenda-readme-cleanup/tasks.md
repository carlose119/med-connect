# Tasks: agenda-readme-cleanup

## 1. RED — add 5 failing doc-contract test scenarios

Extend `tests/Feature/Docs/ReadmeApiSurfaceTest.php` (currently 3 scenarios from `agenda-readme-drift`) with 5 ADDED scenarios asserting the post-fix README content. Each scenario is a line-precise grep check, following the existing 3 scenarios from `agenda-readme-drift` (obs #52).

- [ ] Add scenario 4: README line 71 contains `13 from PR 1+2` (not `15 from PR 1+2`)
- [ ] Add scenario 5: README line 304 contains `136+4 on SQLite` and `140 on MariaDB` (not the stale `42 / 44` numbers)
- [ ] Add scenario 6: README line 314 and line 349 both contain `Node 20.16+` (aligned wording)
- [ ] Add scenario 7: README line 283 and line 464 both contain `med-connect.test` (not `med-connect.local`)
- [ ] Add scenario 8: README line 360 contains `18 routes` (not `19 routes`) AND the endpoint table has 18 rows (currently 17, missing `GET /api/doctors/{doctor}`)

**Pattern** (one example; the other 4 follow the same structure):
```php
test('README line 71 has correct migration count breakdown', function () {
    $lines = file(base_path('README.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read README.md');
    expect($lines[70])->toContain('13 from PR 1+2');
});
```

Run the test: `vendor/bin/pest tests/Feature/Docs/ReadmeApiSurfaceTest.php` — expect 5 failures (scenarios 4-8 all fail because the README still has the old content; scenarios 1-3 from `agenda-readme-drift` still pass).

## 2. GREEN — apply 5 README line-level edits

Edit `README.md` to make all 5 RED scenarios pass.

- [ ] Edit line 71: `15 from PR 1+2` → `13 from PR 1+2`
- [ ] Edit line 304: `All 42 cases on SQLite (default), 44 on MariaDB` → `All 136+4 cases on SQLite (default), 140 on MariaDB`
- [ ] Edit line 313-314: align to `Node 20.16+ (prints warning, build succeeds)`
- [ ] Edit line 349: align to `Node 20.16+ (Vite 7 prints warning below 20.19; build still succeeds) or 22.12+`
- [ ] Edit line 283: `med-connect.local` → `med-connect.test`
- [ ] Edit line 464: `med-connect.local` → `med-connect.test`
- [ ] Edit line 360: `19 routes` → `18 routes` (and ensure breakdown reads `15 public + 3 auth`)
- [ ] Add a new row to the endpoint table (between row 11 `GET /api/doctors` and row 12 `GET /api/doctors/{id}/slots`): `| 11b | GET    | \`/api/doctors/{doctor}\`                            | any                | Single doctor detail. |` (preserve column padding)

Run the test: `vendor/bin/pest tests/Feature/Docs/ReadmeApiSurfaceTest.php` — expect 0 failures (all 8 scenarios pass: 3 from `agenda-readme-drift` + 5 new).

## 3. VERIFY — full test suite + route count + cumulative state

- [ ] Run full test suite on SQLite: `php artisan test --testsuite=Feature,Unit` (or `vendor/bin/pest`) — expect 141 passed (136+5) + 4 skipped (MariaDB-only race tests)
- [ ] Run full test suite on MariaDB: switch `.env` to MariaDB and re-run — expect 145 passed (140+5) + 0 skipped
- [ ] Verify route count: `php artisan route:list --path=api | wc -l` — expect 18 (3 auth + 15 public)
- [ ] Verify the new `agenda/readme-cleanup` sub-capability spec is in `openspec/changes/agenda-readme-cleanup/specs/agenda/readme-cleanup/spec.md` (created by sdd-spec phase before sdd-apply)
- [ ] Verify no other spec/canonical file was touched
- [ ] Commit, ff-merge to main (no push, per AGENTS.md)
- [ ] Final commit on main: `chore(archive): sync agenda-readme-cleanup ADDED scenarios into canonical` (created by sdd-archive phase)
