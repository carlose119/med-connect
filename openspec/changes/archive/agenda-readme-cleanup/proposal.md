# Change: agenda-readme-cleanup

## Why

The README.md has accumulated 5 cosmetic drifts since the `agenda-readme-drift` cycle (archived in `d3b4ef9`) closed 5 stale `/api/me` references. Subsequent cycles (agenda-spec-drift, agenda-api-dedup) shipped more functionality but the README still references stale state (PR 5 test counts, Node version claims, route counts, seed email domains, migration counts). These drifts are misleading for new contributors and break the README's role as the canonical "how to run this" doc.

This change is a mechanical line-level cleanup. No behavior change, no functional gap, no design decision. It extends the established `agenda-readme-drift` doc-contract test pattern (line-precise grep checks in `tests/Feature/Docs/ReadmeApiSurfaceTest.php`).

## What changes

5 cosmetic README drifts closed (line numbers verified against current `README.md` at `4b2bb51`):

1. **Migration count breakdown** (line 71): `15 from PR 1+2` → `13 from PR 1+2`. The 2 Laravel default migrations (`0001_01_01_000001_create_cache_table.php` and `0001_01_01_000002_create_jobs_table.php`) are not from PR 1+2; total 16 is correct.
2. **Filament PR 5 test count** (line 304): `All 42 cases on SQLite (default), 44 on MariaDB` → `136+4 on SQLite, 140 on MariaDB`. Stale from PR 5; 6 cycles have shipped since.
3. **Node version inconsistency** (line 313-314 vs line 349): align both to `Node 20.16+ (prints warning, build succeeds)`. `node --version` reports v20.16.0 and the build works; the `Node 20.19+` claim in the Environment section is a Vite 7 upstream requirement that does not apply to this project's actual node version.
4. **Email domain `.local` → `.test`** (lines 283, 464): align with `database/seeders/DatabaseSeeder.php` (lines 29, 47, 81) which creates `admin@med-connect.test`, `doctor@med-connect.test`, and `patient@med-connect.test`. **Note**: orchestrator's launch prompt cited line 282; verified actual is line 283 (line 282 is `# http://127.0.0.1:8000/doctor  →  log in as a user with role=doctor`, line 283 is the `med-connect.local` reference).
5. **Route count + endpoint table missing row** (line 360, lines 364-382): `19 routes` → `18 routes` (3 auth + 15 public) and add the missing `GET /api/doctors/{doctor}` row to the endpoint table (mapped to `Api\DoctorController@show`, between `/api/doctors` row 11 and `/api/doctors/{id}/slots` row 12).

**Total LOC**: ~10.

**Test pattern**: extend `tests/Feature/Docs/ReadmeApiSurfaceTest.php` (currently 3 scenarios from `agenda-readme-drift`) with 5 ADDED scenarios. After this change: 8 scenarios in the class, 1 new sub-capability with 1 req and 5 ADDED scenarios.

## Impact

- **Affected files**: `README.md` (only) + `tests/Feature/Docs/ReadmeApiSurfaceTest.php` (extended with 5 ADDED scenarios)
- **Affected specs**: 1 new sub-capability `agenda/readme-cleanup` (parallel to `agenda/readme-drift`)
- **Affected routes**: 0 (no route changes; this is a doc-only change)
- **Affected tests**: 0 behavior changes; 5 ADDED doc-contract test scenarios
- **Breaking changes**: none

**Capabilities** (per `sdd-propose` SKILL.md contract with sdd-spec):
- **New**: `agenda/readme-cleanup` — doc-contract assertions covering 5 cosmetic README drifts (1 req, 5 ADDED scenarios, identical pattern to `agenda/readme-drift`).
- **Modified**: None.

**Out-of-scope** (deferred to other changes):
- "Status — agenda-core" section (line 323-343): outdated stylistically, too large → `agenda-readme-revamp` follow-up
- PHP 8.4+ claim (line 347): borderline drift → follow-up env-section overhaul
- `/api/me` references (already closed in `agenda-readme-drift`)
- `agenda-resource-shape` (DoctorResource shape spec drift) — separate change
- `rbac-advanced` (Filament Shield integration) — separate change
- `agenda-patient-web` (future patient-facing web) — separate change

## Approach

Single-PR, 3-commit pattern (RED → GREEN → VERIFY), identical to the `agenda-readme-drift` cycle (obs #52, PR `feat/readme-drift`, commits `ff6818f` → `823e77b` → `b1b980f`):

- **PR branch**: `feat/readme-cleanup` (off `main` at `4b2bb51`)
- **Commit 1 (RED)**: add 5 failing test scenarios to `tests/Feature/Docs/ReadmeApiSurfaceTest.php` asserting the new (post-fix) README content. Expect 5 failures on first run (the existing 3 pass because the prior cycle already aligned the `/api/me` family).
- **Commit 2 (GREEN)**: apply 5 README line-level edits
- **Commit 3 (VERIFY)**: run `vendor/bin/pest tests/Feature/Docs/ReadmeApiSurfaceTest.php` and `php artisan route:list --path=api` to verify all 8 scenarios pass and the route count is still 18

**Test pattern**: line-precise grep checks, identical to the existing 3 scenarios in `ReadmeApiSurfaceTest.php` (obs #52). Each scenario asserts the new README content (not the old) — RED phase has them all failing, GREEN phase has them all passing.

**No design decision needed** — this is a mechanical change with no new behavior, no new routes, no new entities, no new architecture. The proposal's design space is the same as `agenda-readme-drift`: doc-contract tests enforce a line-precise match between README claims and the actual project state.

**No spec change** beyond the new sub-capability `agenda/readme-cleanup` (1 req, 5 ADDED scenarios, pattern identical to `agenda/readme-drift`).

**Rollback plan**: `git revert <merge-commit>`. The change is doc-only; no data migration, no schema, no behavior. Reverting the merge commit fully restores the prior README state and removes the 5 new test scenarios.

## Success criteria

- All 5 ADDED test scenarios in `ReadmeApiSurfaceTest.php` pass
- `README.md` has no further cosmetic drifts against the actual project state for the 5 enumerated drift families
- Cumulative state after archive: 11 capabilities (10 + 1 new), 32 reqs (31 + 1 new), 129 scenarios (124 + 5 ADDED), 18 routes (unchanged)
  - **Note**: orchestrator's launch prompt cited 129 (124 + 5). Sub-agent's deviation message claimed 130 (125 + 5), cross-referencing obs #54 (agenda-readme-drift archive, 2 cycles ago, 125). Verified against obs #68 (agenda-api-dedup archive, 1 cycle ago, 124): correct cumulative = 129. The launch prompt number was correct.
- 1 PR `feat/readme-cleanup` merged to main via ff-merge
- 1 archive commit on main: `chore(archive): sync agenda-readme-cleanup ADDED scenarios into canonical`

## Risks

- **Low**: Mechanical line-level change. The only risk is line-number drift if the README is edited between this proposal and the RED commit (mitigation: verify line numbers in the RED commit and adjust if shifted).
- **Low**: New sub-capability creation follows the established pattern (parallel to `agenda/readme-drift`), so the doc-contract test pattern is well-trodden.
- **None**: No behavior change, no breaking change, no new dependency, no migration.
