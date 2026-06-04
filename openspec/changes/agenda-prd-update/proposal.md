# Change: agenda-prd-update

## Why

The `openspec/AGENTS.md` (the project's PRD and workflow contract) has accumulated 3 drifts since the `env-section-overhaul` cycle (archived in `6455b86`) closed the README.md version of these drifts. AGENTS.md is a different file from README.md (it's the developer contract, not the user-facing docs), and was explicitly out of scope for `env-section-overhaul` (per the proposal §"Out-of-scope" bullet `agenda-prd-update` and obs #90 §"Out-of-scope follow-ups").

The 3 drifts are:

1. **PHP version claim (line 69)**: overstates the `composer.json` `^8.3` constraint to `^8.4+`. Same fix as `env-section-overhaul` but in AGENTS.md.
2. **Pest version claim (line 75)**: claims Pest 3 but the project actually uses Pest 4.7.1.
3. **Unique partial index syntax (line 58)**: CORRECTNESS drift. The PRD describes only the PostgreSQL form, but the actual migration `database/migrations/2026_06_01_000007_create_appointments_table.php` (lines 27-53) is driver-aware: MariaDB/MySQL use a generated `cancelled_marker` column + UNIQUE KEY, while PostgreSQL/SQLite use a partial index with `WHERE`. The current PRD is misleading and would cause a future agent to apply only the PostgreSQL form when porting to MariaDB.

This change is a mechanical line-level cleanup. No behavior change, no functional gap, no design decision. It extends the established `env-section-overhaul` doc-contract test pattern with a NEW test class `tests/Feature/Docs/AgentsDocContractTest.php` (separate from `ReadmeApiSurfaceTest.php` which is README-specific — per-file separation convention).

## What changes

3 AGENTS.md drifts closed (line numbers verified against current `openspec/AGENTS.md` at `6455b86`):

1. **Stack section PHP claim (line 69)**: `Backend: Laravel 13 (PHP 8.4+)` → `Backend: Laravel 13 (PHP 8.3+)`. Matches `composer.json` `"php": "^8.3"` (line 9). **COSMETIC drift**. LOC: 1.

2. **Stack section Pest claim (line 75)**: `Pest 3 (modern Laravel default)` → `Pest 4 (modern Laravel default)`. Matches `composer.json` `"pestphp/pest": "4.7.1"` (line 22). **COSMETIC drift**. LOC: 1.

3. **Unique partial index syntax (line 58)**: `Unique partial index (doctor_id, start_time) WHERE status != 'cancelled'` → driver-aware description. The new wording MUST mention both forms:
   - MariaDB/MySQL: generated `cancelled_marker` column + UNIQUE KEY on `(doctor_id, start_time, cancelled_marker)`
   - PostgreSQL/SQLite: partial unique index `(doctor_id, start_time) WHERE status != 'cancelled'`
   - The implementation is verified in `database/migrations/2026_06_01_000007_create_appointments_table.php` lines 27-53.
   - **CORRECTNESS drift** (not just cosmetic). The new wording is more than a one-character edit; it must describe the driver-aware pattern. LOC: 1 (a longer line, but a single line edit).

**Total LOC**: ~3.

**Test pattern**: NEW test class `tests/Feature/Docs/AgentsDocContractTest.php` (mirrors `ReadmeApiSurfaceTest.php` for README.md, but specifically for AGENTS.md — per-file separation). 3 ADDED scenarios, one per closed drift. After this change: 3 scenarios in the new class, 1 new sub-capability with 1 req and 3 ADDED scenarios.

**Test scenario for drift 3** must include assertions on the WORDS that appear in the new wording. The test should verify the line-precise content (not just the absence of the old wording), because the new wording is the contract for future migrations. The test scenario is more than a regex check; it's a content-validation check (multiple sub-string assertions to validate both driver forms).

## Impact

- **Affected files**: `openspec/AGENTS.md` (only) + `tests/Feature/Docs/AgentsDocContractTest.php` (NEW)
- **Affected specs**: 1 new sub-capability `agenda/prd-update` (parallel to `agenda/env-section-overhaul`, Option A)
- **Affected routes**: 0 (no route changes; this is a doc-only change)
- **Affected tests**: 0 behavior changes; 3 ADDED doc-contract test scenarios in a NEW test class
- **Breaking changes**: none

**Capabilities** (per `sdd-propose` SKILL.md contract with sdd-spec):
- **New**: `agenda/prd-update` — doc-contract assertions covering 3 AGENTS.md drifts (1 req, 3 ADDED scenarios, identical pattern to `agenda/env-section-overhaul` but for AGENTS.md).
- **Modified**: None.

**Out-of-scope** (deferred to other changes):
- The remaining 10 architectural decisions in the PRD — verified correct against migrations and canonical specs (per obs #91)
- Local environment section (PHP 8.4.4, Composer 2.8.11, Node 20.16.0, etc.) — verified live this session
- Sub-agent contract — `.atl/skill-registry.md` exists
- Layout section — `config.yaml` is a forward-looking statement, not a drift
- `agenda-readme-revamp` — Status section structural
- `agenda-resource-shape` — DoctorResource shape spec drift
- `rbac-advanced` — Filament Shield integration
- `agenda-patient-web` — future patient-facing web
- `CONTRIBUTING.md` update — document commit pattern
- `for the agenda-core PR` reference (README line 350) — stale but out of scope for this change
- Composer 2.8+ claim (README line 348) — borderline, no `engines` constraint

## Approach

Single-PR, 3-5 commit pattern (RED → GREEN → VERIFY, possibly +TEST-FIX if no-amend forces it), identical to the `env-section-overhaul` cycle (obs #87, PR `feat/env-section-overhaul`, 4 commits):

- **PR branch**: `feat/agenda-prd-update` (off `main` at `6455b86`)
- **Commit 1 (RED)**: create `tests/Feature/Docs/AgentsDocContractTest.php` with 3 failing test scenarios asserting the new (post-fix) AGENTS.md content. Expect 3 failures on first run.
- **Commit 2 (GREEN)**: apply 3 AGENTS.md line-level edits
- **Commit 3 (VERIFY, possibly with TEST-FIX)**: run `vendor/bin/pest tests/Feature/Docs/AgentsDocContractTest.php` to verify all 3 scenarios pass. If a TEST-FIX is needed (e.g., line number drift between proposal and RED), create a separate commit (no-amend rule).
- **Commit 4 (TASKS-housekeeping)**: mark the 3 tasks in `tasks.md` as `[x]` and track the change folder per obs #66 (matches the env-section-overhaul cycle's 4-commit pattern).

**Test pattern**: line-precise grep checks, identical to `ReadmeApiSurfaceTest.php` but for AGENTS.md. Each scenario asserts the new AGENTS.md content (not the old) — RED phase has them all failing, GREEN phase has them all passing. Drift 3's scenario asserts the driver-aware wording (both MariaDB and PostgreSQL/SQLite forms must appear in the new wording) via 4 sub-string assertions: `MariaDB` (or `MySQL`), `PostgreSQL` (or `SQLite`), `cancelled_marker`, and `WHERE status !=`.

**No design decision needed** — this is a mechanical change with no new behavior, no new routes, no new entities, no new architecture. The proposal's design space is the same as `env-section-overhaul`: doc-contract tests enforce a line-precise match between AGENTS.md claims and the actual project state.

**No spec change** beyond the new sub-capability `agenda/prd-update` (1 req, 3 ADDED scenarios, pattern identical to `agenda/env-section-overhaul`).

**Rollback plan**: `git revert <merge-commit>`. The change is doc-only; no data migration, no schema, no behavior. Reverting the merge commit fully restores the prior AGENTS.md state and removes the 3 new test scenarios and the new test class.

## Success criteria

- All 3 ADDED test scenarios in `AgentsDocContractTest.php` pass (drift 3's scenario asserts the driver-aware wording via 4 sub-string assertions)
- `openspec/AGENTS.md` has no further drifts against the actual project state for the 3 enumerated drift families
- Cumulative state after archive: 13 capabilities (12 + 1 new), 34 reqs (33 + 1 new), 138 scenarios (135 + 3 ADDED), 18 routes (unchanged)
  - Baseline: 12/33/135/18 per obs #90 (env-section-overhaul archive-report, CORRECTED from prior undercount via obs #89)
- 1 PR `feat/agenda-prd-update` merged to main via ff-merge
- 1 archive commit on main: `chore(archive): sync agenda-prd-update ADDED scenarios into canonical`
- Test counts after archive: 147 passed + 4 skipped SQLite / 151 passed + 0 skipped MariaDB (144+3 / 148+3)

## Risks

- **Low**: Mechanical line-level change. The only risk is line-number drift if AGENTS.md is edited between this proposal and the RED commit (mitigation: verify line numbers in the RED commit and adjust if shifted).
- **Low**: New sub-capability creation follows the established pattern (parallel to `agenda/env-section-overhaul`), so the doc-contract test pattern is well-trodden.
- **Low**: New test class creation follows the pattern of `ReadmeApiSurfaceTest.php`. If the test class is misplaced (e.g., wrong directory), the test runner may not pick it up — verify the test class is in `tests/Feature/Docs/` and uses the right namespace.
- **Low**: Drift 3's correctness fix is more nuanced than a simple version bump. The new wording must accurately describe the driver-aware pattern from the migration. The test scenario's content assertions must cover both MariaDB and PostgreSQL/SQLite forms.
- **None**: No behavior change, no breaking change, no new dependency, no migration.
