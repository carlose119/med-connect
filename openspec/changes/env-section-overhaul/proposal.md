# Change: env-section overhaul

## Why

The README.md has accumulated 3 env-section drifts since the `agenda-readme-cleanup` cycle (archived in `cb1f2d3`) closed the previous family of cosmetic README drifts. Subsequent cycles did not update the env section, leaving:

1. A PHP version claim that overstates the composer.json constraint (`^8.4+` vs `^8.3`).
2. A factually-wrong parenthetical claim about PHP 8.4 features (property hooks, asymmetric visibility) — verified 0 matches in `app/`.
3. A stale "greenfield" phraseology from the pre-`agenda-core` era.

These drifts are misleading for new contributors and break the README's role as the canonical "how to run this" doc. The PHP version claim appears in BOTH the Stack section (line 8) and the Environment section (line 347); both need updating.

This change is a mechanical line-level cleanup. No behavior change, no functional gap, no design decision. It extends the established `agenda-readme-cleanup` doc-contract test pattern (line-precise grep checks in `tests/Feature/Docs/ReadmeApiSurfaceTest.php`).

## What changes

3 env-section drifts closed (line numbers verified against current `README.md` at `cb1f2d3`):

1. **Stack section PHP claim (line 8)**: `Laravel 13 (PHP 8.4+)` → `Laravel 13 (PHP 8.3+)`. Matches `composer.json` `^8.3`. LOC: 1.

2. **Environment section PHP claim + parenthetical (line 347)**: `PHP 8.4+ (the project pins to features available in 8.4 — e.g. property hooks, asymmetric visibility)` → `PHP 8.3+ (per composer.json)`. Drops the factually-wrong parenthetical (0 PHP 8.4 features used in `app/`; the actual features are PHP 8.0-8.2 such as constructor property promotion, named arguments, enums). LOC: 1.

3. **Stale "greenfield" phraseology (line 350)**: drop the `greenfield before that needs no DB` clause. `agenda-core` is archived (commits 0a17b3c, e2ecc74), the project always needs a DB now. LOC: 1.

**Total LOC**: ~3.

**Test pattern**: extend `tests/Feature/Docs/ReadmeApiSurfaceTest.php` (currently 8 scenarios from `agenda-readme-drift` + `agenda-readme-cleanup`) with 3 ADDED scenarios. After this change: 11 scenarios in the class, 1 new sub-capability with 1 req and 3 ADDED scenarios.

**Test scenario for drift 2** must include a sub-assertion: the env section does NOT contain `property hooks` or `asymmetric visibility` (to enforce that the factually-wrong claim is permanently removed). This is more than a line-precise check; it's a "negative assertion" against the deleted phrase.

## Impact

- **Affected files**: `README.md` (only) + `tests/Feature/Docs/ReadmeApiSurfaceTest.php` (extended with 3 ADDED scenarios)
- **Affected specs**: 1 new sub-capability `agenda/env-section-overhaul` (parallel to `agenda/readme-cleanup`)
- **Affected routes**: 0 (no route changes; this is a doc-only change)
- **Affected tests**: 0 behavior changes; 3 ADDED doc-contract test scenarios (1 includes a negative assertion)
- **Breaking changes**: none

**Capabilities** (per `sdd-propose` SKILL.md contract with sdd-spec):
- **New**: `agenda/env-section-overhaul` — doc-contract assertions covering 3 env-section drifts (1 req, 3 ADDED scenarios, identical pattern to `agenda/readme-cleanup`).
- **Modified**: None.

**Out-of-scope** (deferred to other changes):
- Composer 2.8+ claim (line 348) — no `engines` constraint, borderline
- `agenda-prd-update` — same `PHP 8.4+` drift in `openspec/AGENTS.md` line 69 (the PRD); separate change
- `agenda-core/verify-report.md` (historical artifact in archive) — immutable
- `agenda-readme-revamp` — Status section structural
- `agenda-resource-shape` — DoctorResource shape spec drift
- `rbac-advanced` — Filament Shield integration
- `agenda-patient-web` — future patient-facing web
- CONTRIBUTING.md update — document 5-commit pattern

## Approach

Single-PR, 3-5 commit pattern (RED → GREEN → VERIFY, possibly +TEST-FIX if no-amend forces it), identical to the `agenda-readme-cleanup` cycle (obs #76, PR `feat/readme-cleanup`, 5 commits):

- **PR branch**: `feat/env-section-overhaul` (off `main` at `cb1f2d3`)
- **Commit 1 (RED)**: add 3 failing test scenarios to `tests/Feature/Docs/ReadmeApiSurfaceTest.php` asserting the new (post-fix) README content. Expect 3 failures on first run (the existing 8 pass because the prior cycles already aligned those drift families).
- **Commit 2 (GREEN)**: apply 3 README line-level edits
- **Commit 3 (VERIFY, possibly with TEST-FIX)**: run `vendor/bin/pest tests/Feature/Docs/ReadmeApiSurfaceTest.php` to verify all 11 scenarios pass. If a TEST-FIX is needed (e.g., line number drift), create a separate commit (no-amend rule).

**Test pattern**: line-precise grep checks, identical to the existing 8 scenarios in `ReadmeApiSurfaceTest.php` (obs #76). Each scenario asserts the new README content (not the old) — RED phase has them all failing, GREEN phase has them all passing. Drift 2's scenario also includes a negative assertion (env section does NOT contain `property hooks` or `asymmetric visibility`).

**No design decision needed** — this is a mechanical change with no new behavior, no new routes, no new entities, no new architecture. The proposal's design space is the same as `agenda-readme-cleanup`: doc-contract tests enforce a line-precise match between README claims and the actual project state.

**No spec change** beyond the new sub-capability `agenda/env-section-overhaul` (1 req, 3 ADDED scenarios, pattern identical to `agenda/readme-cleanup`).

**Rollback plan**: `git revert <merge-commit>`. The change is doc-only; no data migration, no schema, no behavior. Reverting the merge commit fully restores the prior README state and removes the 3 new test scenarios.

## Success criteria

- All 3 ADDED test scenarios in `ReadmeApiSurfaceTest.php` pass (the drift 2 scenario's negative assertion passes)
- `README.md` has no further env-section drifts against the actual project state for the 3 enumerated drift families
- Cumulative state after archive: 12 capabilities (11 + 1 new), 33 reqs (32 + 1 new), 132 scenarios (129 + 3 ADDED), 18 routes (unchanged)
  - Baseline: 11/32/129/18 per obs #79 (agenda-readme-cleanup archive-report)
- 1 PR `feat/env-section-overhaul` merged to main via ff-merge
- 1 archive commit on main: `chore(archive): sync env-section-overhaul ADDED scenarios into canonical`

## Risks

- **Low**: Mechanical line-level change. The only risk is line-number drift if the README is edited between this proposal and the RED commit (mitigation: verify line numbers in the RED commit and adjust if shifted).
- **Low**: New sub-capability creation follows the established pattern (parallel to `agenda/readme-cleanup`), so the doc-contract test pattern is well-trodden.
- **Low**: Drift 2's negative assertion is a slightly different test pattern (line-precise check + negative assertion). Mitigated by the line-precise check covering the main edit; the negative assertion is a defense-in-depth check.
- **None**: No behavior change, no breaking change, no new dependency, no migration.
