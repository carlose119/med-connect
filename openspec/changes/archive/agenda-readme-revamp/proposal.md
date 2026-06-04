# Change: agenda-readme-revamp

## Why

The `README.md` `## Status — agenda-core` section (lines 323-343) is a frozen snapshot of the agenda-core chained split (cycle 1, 9 cycles ago). It claims `**Feature-complete pending sdd-verify.**` for a cycle that long since archived (commits 0a17b3c, e2ecc74, 8 cycles ago), and the 5-row PR table references the obsolete `feat/filament-panels` branch as the active chain link. The section no longer reflects the project's actual state at cycle 9: 13 capabilities, 34 reqs, 138 scenarios, 18 routes, 9 archived changes, 147 passed + 4 skipped tests (per obs #98).

Subsequent cycles (agenda-readme-drift, agenda-readme-cleanup, env-section-overhaul, agenda-prd-update) closed cosmetic drifts in OTHER README sections (lines 7, 70, 282, 303, 313, 346, 348, 349, 359, 463) but left the Status section untouched. It is now the most stale block in the file.

sdd-explore proposed 4 options: A (text-only update, ~5 LOC), B (single-block refactor, ~12 LOC), C (table refactor, ~15 LOC), D (h3 split, ~30 LOC). The user picked **Option D** — split the section into 4 h3 subsections (Build status / Test status / SDD state / Roadmap). The explore's recommendation was Option C for its lower LOC and tighter visual structure, but Option D is acceptable: it organizes heterogeneous information (counts, claims, navigation, history) for visual scanning, and the README already uses h3s heavily in the Filament v5 section (6 h3s, lines 225-321), the Agenda PR 4 section (2 h3s, lines 177-211), and the REST API section (7 h3s, lines 362-516). Option D is therefore MORE aligned with the existing convention than the explore's framing suggested — the README is fundamentally an h2/h3 document.

## What changes

3 README drifts closed in the `## Status` section (line numbers verified against current `README.md` at `0ae0b73`):

1. **Stale `agenda-core` subtitle (line 323)**: `## Status — agenda-core` → `## Status`. The section has not been about agenda-core for 8 cycles; the subtitle is a frozen chained-split artifact.

2. **Flat prose structure (lines 323-343)**: the 21-line flat block of prose + 5-row PR table + 2 trailing paragraphs is split into 4 h3 subsections. Each h3 is 3-5 lines; total section is ~30 lines (content-additive, not just structural).

3. **Stale `Feature-complete pending sdd-verify` text + obsolete `sdd-verify` next-step paragraph (lines 325-343)**: the prose `**Feature-complete pending \`sdd-verify\`.** All five PRs of the chained split are landed on \`feat/filament-panels\` ...` AND the trailing next-step paragraph `Next step is \`sdd-verify\` ... then \`sdd-archive\` to sync the delta specs ...` are removed. The new content is a closed-form description of cycle 9 state, not a frozen in-progress snapshot.

**Total LOC**: ~30 (well under 400-line review budget per D1; soft budget 30 LOC per explore forecast).

**Canonical content** (locked at proposal time; will be applied verbatim in GREEN):

```markdown
## Status

### Build status
- 18 routes (3 auth + 15 public); unchanged since `agenda-readme-cleanup`
- 13 migrations + 12 Eloquent models + 13 factories (from `agenda-core`)
- Filament v5 panels: `/admin` (UserResource, SpecialtyResource) + `/doctor` (dashboard only)

### Test status
- SQLite: 147 passed + 4 skipped (571 assertions)
- MariaDB: 151 passed + 0 projected (env unavailable this session; 4 skipped are MariaDB-only race tests)
- 0 canonical spec drift (`git diff main~N..HEAD -- openspec/specs/` returns 0 lines)

### SDD state
- 13 capabilities, 34 reqs, 138 scenarios (per `agenda-prd-update` archive-report)
- 9 archived changes; 0 active changes (only `archive/` directory under `openspec/changes/`)
- Drift-closure pattern: cycles 4-9 closed README/PRD drifts; this cycle (10) is structural refactor

### Roadmap
- [x] `agenda-core` — 13 migrations, 12 models, 13 factories, Sanctum, RBAC, state machine
- [x] `agenda-http` — 18 routes, 3 auth + 15 public, Sanctum bearer
- [x] `agenda-test-coverage` — test slice normalization
- [x] `agenda-readme-drift` — 5 `/api/me` references closed
- [x] `agenda-spec-drift` — `agenda/api` spec brought into compliance
- [x] `agenda-api-dedup` — duplicate `GET /api/auth/me` scenario removed
- [x] `agenda-readme-cleanup` — 5 cosmetic README drifts
- [x] `env-section-overhaul` — 3 env-section drifts (PHP, parenthetical, greenfield)
- [x] `agenda-prd-update` — 3 AGENTS.md drifts (PHP, Pest, unique partial index)
```

**Test pattern**: extend `tests/Feature/Docs/ReadmeApiSurfaceTest.php` (currently 11 scenarios from `agenda-readme-drift` + `agenda-readme-cleanup` + `env-section-overhaul`) with 3 ADDED scenarios. After this change: 14 scenarios in the class, 1 new sub-capability with 1 req and 3 ADDED scenarios.

**Test scenario for drift 3** includes a negative assertion (defense-in-depth): the Status section does NOT contain `Feature-complete pending` or `sdd-verify`. Same pattern as `env-section-overhaul` drift 2 (lines 155-170, banning `property hooks`/`asymmetric visibility` from the env section). The 40-line window for the negative-assertion scan starts at line 323 (1-indexed) and is wide enough to cover the new ~30-line section plus padding.

## Impact

- **Affected files**: `README.md` (only) + `tests/Feature/Docs/ReadmeApiSurfaceTest.php` (extended with 3 ADDED scenarios)
- **Affected specs**: 1 new sub-capability `agenda/readme-revamp` (parallel to `agenda/readme-cleanup`, `agenda/env-section-overhaul`, `agenda/prd-update`)
- **Affected routes**: 0 (no route changes; this is a doc-only change)
- **Affected tests**: 0 behavior changes; 3 ADDED doc-contract test scenarios (1 with a negative assertion, 1 with an h3-enumeration check, 1 line-precise)
- **Breaking changes**: none

**Capabilities** (per `sdd-propose` SKILL.md contract with sdd-spec):
- **New**: `agenda/readme-revamp` — doc-contract assertions covering 3 Status-section drifts (1 req, 3 ADDED scenarios, identical pattern to `agenda/env-section-overhaul`).
- **Modified**: None.

**Out-of-scope** (deferred to other changes):
- `agenda-resource-shape` (DoctorResource shape spec drift) — separate change
- `rbac-advanced` (Filament Shield integration) — separate change
- `agenda-doctor-ui` (Filament doctor "view my appointments" UI) — separate change
- `agenda-patient-web` (future patient-facing web) — separate change
- `CONTRIBUTING.md` update — document 4-5 commit pattern
- Composer 2.8+ claim (README line 348) — borderline, no `engines` constraint
- Stale `for the agenda-core PR` reference (README line 350) — separate, narrow fix
- 3 cosmetic SUGGESTIONs from prior verify runs (DoctorController@slots TZ fallback, AuditLogResource defensive parse, MedicalHistoryController inline role check)
- DEV-4 (Pest test() vs it() style divergence) — future cleanup cycle

## Approach

Single-PR, 4-commit pattern (RED → GREEN → VERIFY → TASKS-housekeeping), identical to the `agenda-prd-update` cycle (obs #97, 4 commits: 0427081, 6326aeb, 78f1479, a8f33f9):

- **PR branch**: `feat/agenda-readme-revamp` (off `main` at `0ae0b73`)
- **Commit 1 (RED)**: extend `tests/Feature/Docs/ReadmeApiSurfaceTest.php` with 3 ADDED failing scenarios asserting the post-fix README content. Use `it()` (NOT `test()`) to match the existing 11-scenario style in this file (per cycle 9's DEV-4 lesson). Use a 40-line window (323-362) for the negative-assertion scan to avoid false positives from the next section.
  - Scenario 12: README line 323 is exactly `## Status` (no subtitle)
  - Scenario 13: Status section contains exactly 4 h3 headings in order: `Build status`, `Test status`, `SDD state`, `Roadmap` (extracted via h2-bounded `array_slice` + `preg_match` for `^### (.+)$`)
  - Scenario 14: Status section does NOT contain `Feature-complete pending` or `sdd-verify` (negative assertion, `array_slice+implode` over 40-line window)
- **Commit 2 (GREEN)**: replace lines 323-343 of `README.md` with the new 4-h3-section structure (canonical content above)
- **Commit 3 (TEST-FIX)**: the +9 line shift from the 21→30 line refactor will shift 4 of the 11 existing scenarios' anchor lines (lines 346, 348, 349, 359, 463 in the existing scenarios). Update them in a separate commit (per the no-amend rule and `agenda-readme-cleanup` precedent: obs #76, commit 0461eb3 `test(agenda-readme-cleanup): fix row-count regex and line 464 shift discovered during GREEN verify`).
- **Commit 4 (VERIFY)**: run `vendor/bin/pest tests/Feature/Docs/ReadmeApiSurfaceTest.php` to verify all 14 scenarios pass. Then run full suite on SQLite. Verify route count unchanged at 18.
- **Commit 5 (TASKS-housekeeping)**: mark all 3 tasks in `tasks.md` as `[x]` and track the change folder per obs #66 (matches the `agenda-prd-update` 4-5 commit pattern).

**Test pattern details**:

- **Drift 1 (line-precise check)**: `expect($lines[322])->toBe('## Status')` — uses `toBe` (exact match) not `toContain` because the line must be exactly the bare heading, no trailing subtitle, no trailing whitespace.
- **Drift 2 (h3 enumeration + order)**: extract all `^### (.+)$` lines from the Status section (bounded by the next h2), then assert the array equals `['Build status', 'Test status', 'SDD state', 'Roadmap']`. The `===` order check is the contract.
- **Drift 3 (negative assertion)**: `array_slice($lines, 322, 40)` over the Status section window, then `expect($section)->not->toContain('Feature-complete pending')` and `->not->toContain('sdd-verify')`. The 40-line window is wide enough for the new ~30-line section (323-352) plus padding to line 362.

**No design decision needed beyond the user's Option D choice** — the proposal's design space is structural only: which subsections to use, in what order, with what content. Option D is the user's authoritative pick.

**No spec change** beyond the new sub-capability `agenda/readme-revamp` (1 req, 3 ADDED scenarios, pattern identical to `agenda/env-section-overhaul`).

**Rollback plan**: `git revert <merge-commit>`. The change is doc-only; no data migration, no schema, no behavior. Reverting the merge commit fully restores the prior README state and removes the 3 new test scenarios and any TEST-FIX updates to the 4 affected existing scenarios.

## Success criteria

- All 3 ADDED test scenarios in `ReadmeApiSurfaceTest.php` pass
- All 11 existing `ReadmeApiSurfaceTest.php` scenarios still pass after TEST-FIX line-shift updates
- `README.md` `## Status` section reflects cycle 9 state (13 caps / 34 reqs / 138 scenarios / 18 routes / 9 archived cycles)
- Cumulative state after archive: 14 capabilities (13 + 1 new), 35 reqs (34 + 1 new), 141 scenarios (138 + 3 ADDED), 18 routes (unchanged)
  - Baseline: 13/34/138/18 per obs #98 (agenda-prd-update archive-report)
- 1 PR `feat/agenda-readme-revamp` merged to main via ff-merge
- 1 archive commit on main: `chore(archive): sync agenda-readme-revamp ADDED scenarios into canonical`
- Test counts after archive: 150 passed (147+3) + 4 skipped SQLite / 154 passed (151+3) + 0 skipped MariaDB (projected; MariaDB env unavailable in this session)
- Route count: 18 unchanged
- ~30 LOC of README change (well under 400-line review budget)

## Risks

- **Low**: Mechanical structural change. The only risk is line-number drift if the README is edited between this proposal and the RED commit (mitigation: verify line numbers in the RED commit and adjust if shifted).
- **Low**: The h3-enumeration test pattern is new (no prior cycle used it). Mitigated by the `===` array assertion being deterministic and the `preg_match` pattern being simple (`/^### (.+)$/`).
- **Low**: The 4-h3 split adds visual structure but is content-additive, not a content replacement. Risk of inconsistency between pre-cycle and post-cycle content; mitigated by the canonical content being locked in this proposal §"What changes".
- **Low**: +9 line shift from 21→30 refactor will move 4 of 11 existing scenarios' anchor lines; TEST-FIX commit handles it (per `agenda-readme-cleanup` precedent, commit 0461eb3).
- **Low**: Pint auto-fix during sdd-verify (1 fix likely; sdd-archive will bundle it with verify-report per obs #95 DSC-1).
- **None**: No behavior change, no breaking change, no new dependency, no migration.
