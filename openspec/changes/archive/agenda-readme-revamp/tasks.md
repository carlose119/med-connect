# Tasks: agenda-readme-revamp

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~80 total (30 README + 50 test scenarios; 14 → 14 + 5 anchor fixes) |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | single PR (`feat/agenda-readme-revamp` off `main` at `0ae0b73`) |
| Delivery strategy | `ask-on-risk` (AGENTS.md preflight, cached) — risk Low → auto-approve |
| Chain strategy | `size-exception` (single PR by design; well within 400-line budget) |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: size-exception
400-line budget risk: Low

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | README Status section refactor + 3 doc-contract scenarios + 5 line-shift anchor fixes + pint | PR 1 | Single slice; doc-only; 0 routes/schema/behavior; safe to ff-merge |

(Work-unit split is N/A: change is one cohesive doc refactor with one coupled test extension. Forecast is ~80 LOC, well under the 400-line soft cap. The 5-commit pattern RED→GREEN→TEST-FIX→VERIFY→TASKS-housekeeping fits inside a single PR per `agenda-readme-cleanup` precedent, commits 0461eb3 + 9afd106.)

## 1. RED — extend ReadmeApiSurfaceTest.php with 3 failing doc-contract scenarios

Extend `tests/Feature/Docs/ReadmeApiSurfaceTest.php` (currently 11 scenarios from `agenda-readme-drift` + `agenda-readme-cleanup` + `env-section-overhaul`) with 3 ADDED scenarios asserting the post-fix README content. Use `it()` (NOT `test()`) to match the existing 11-scenario style in this file (per cycle 9's DEV-4 lesson: the file is `it()`-style). All 3 must be RED on the current `README.md` (which still has the `## Status — agenda-core` subtitle, no h3 subsections, and the stale `Feature-complete pending sdd-verify` text).

- [x] Add scenario 12: README line 323 is exactly `## Status` (no `— agenda-core` subtitle). Pattern:
  ```php
  it('README line 323 has bare `## Status` heading (no agenda-core subtitle)', function () {
      $lines = file(base_path('README.md'), FILE_IGNORE_NEW_LINES);
      expect($lines)->not->toBeFalse('Could not read README.md');
      // 1-indexed line 323 = 0-indexed $lines[322]. Spec scenario 1 (REQ-README-REVAMP-1)
      // requires the Status section heading to be exactly `## Status` (no subtitle).
      // The stale `## Status — agenda-core` is from the frozen cycle-1 chained split.
      expect($lines[322])->toBe('## Status');
  });
  ```

- [x] Add scenario 13: Status section contains exactly 4 h3 headings in order (`Build status`, `Test status`, `SDD state`, `Roadmap`). Pattern uses h2-bounded `array_slice` + `preg_match` for `^### (.+)$`:
  ```php
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
  ```

- [x] Add scenario 14: Status section does NOT contain `Feature-complete pending` or `sdd-verify` (negative assertion, defense-in-depth). Pattern mirrors `env-section-overhaul` drift 2 (lines 155-170 in `ReadmeApiSurfaceTest.php`):
  ```php
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
  ```

Run the test: `vendor/bin/pest tests/Feature/Docs/ReadmeApiSurfaceTest.php` — expect 3 failures (scenarios 12-14 all fail; scenarios 1-11 from prior cycles still pass). The commit: `test(agenda-readme-revamp): RED - extend ReadmeApiSurfaceTest with 3 failing Status-section scenarios (subtitle removed, 4 h3 present, no stale sdd-verify reference)`.

## 2. GREEN — rewrite the ## Status section in README.md

Replace lines 323-343 of `README.md` with the canonical 4-h3-section structure from the proposal §"What changes". All 3 RED scenarios must pass after this edit.

- [x] Replace lines 323-343 (the `## Status — agenda-core` section, 21 lines) with the 30-line canonical content from the proposal. Preserve the exact h3 spelling and order: `### Build status`, `### Test status`, `### SDD state`, `### Roadmap`. Preserve blank lines between subsections.
- [x] Verify scenario 12 (line 323 is exactly `## Status`) passes
- [x] Verify scenario 13 (4 h3s in order) passes
- [x] Verify scenario 14 (no `Feature-complete pending` or `sdd-verify`) passes

Run the test: `vendor/bin/pest tests/Feature/Docs/ReadmeApiSurfaceTest.php` — expect 3 failures from the 11 existing scenarios (line shifts: lines 346, 348, 349, 359, 463 shift by +9 because the new section is 9 lines longer). The 3 new scenarios pass. The commit: `fix(agenda-readme-revamp): GREEN - rewrite Status section to 4 h3 subsections (Build, Test, SDD state, Roadmap) with current cycle 9 state`.

## 3. TEST-FIX — update 5 of 11 existing scenarios for the +9 line shift

The GREEN commit makes the Status section 30 lines (was 21), so everything from line 323 onward shifts by +9. The 11 existing scenarios' anchor lines shift as follows (per `agenda-readme-cleanup` precedent, commit 0461eb3):

- Scenario 6 (line 348 → 357): `expect($lines[348])->toContain('Node 20.16+ ...')` → `expect($lines[357])->toContain(...)`
- Scenario 8 (line 359 → 368): `expect($lines[359])->toContain('18 routes')` → `expect($lines[368])->toContain(...)`
- Scenario 10 (line 346 → 355, env window 340-360 → 349-369): update both the line-precise check and the negative-assertion window
- Scenario 11 (line 349 → 358): `expect($lines[349])->not->toContain('greenfield ...')` → `expect($lines[358])->not->toContain(...)`

- [x] Update scenario 6 line 348 → 357 (Node 20.16+ env claim)
- [x] Update scenario 8 line 359 → 368 (REST API route count)
- [x] Update scenario 10: line 346 → 355 AND the env window `array_slice($lines, 340, 20)` → `array_slice($lines, 349, 20)`
- [x] Update scenario 11 line 349 → 358 (greenfield phraseology)
- [x] Verify scenario 7 (line 463 → 472, curl email domain) is also shifted: update from 463 to 472

Run the test: `vendor/bin/pest tests/Feature/Docs/ReadmeApiSurfaceTest.php` — expect 0 failures (all 14 scenarios pass: 11 from prior cycles + 3 new). The commit: `test(agenda-readme-revamp): fix 5 existing scenario line anchors for +9 line shift from Status section refactor`.

## 4. VERIFY — full test suite + regression check + route count

- [x] Run full test suite on SQLite: `vendor/bin/pest` — expect 150 passed (147+3) + 4 skipped (MariaDB-only race tests, unchanged)
- [x] Run full test suite on MariaDB: `DB_CONNECTION=mariadb vendor/bin/pest` — expect 154 passed (151+3) + 0 skipped (NOTE: MariaDB service was unavailable in prior sessions; SQLite 150+4 is authoritative for this doc-only change per obs #97 DEV-2 pattern)
- [x] Verify the 11 existing `ReadmeApiSurfaceTest` scenarios still pass (regression anchor)
- [x] Verify the 3 new scenarios pass
- [x] Verify the 3 existing `AgendaApiSpecCanonicalRoutesTest` scenarios still pass (cross-class regression)
- [x] Verify the 3 existing `AgentsDocContractTest` scenarios still pass (cross-class regression)
- [x] Verify route count: `php artisan route:list --path=api --json | ConvertFrom-Json | Measure-Object` — expect 18 (unchanged)
- [x] Run `vendor/bin/pint --test` — expect 0 issues; if any auto-fix is needed, run `vendor/bin/pint` (without --test) and bundle with archive step (per obs #97 DSC-1)
- [x] Commit (only if no issues): `chore(test): verify agenda-readme-revamp test suite on SQLite (verify) - 150 passed + 4 skipped, 18 routes unchanged, MariaDB unavailable in this session`

## 5. TASKS-housekeeping — mark tasks complete + track change folder

- [x] Mark all 5 tasks in this `tasks.md` file as `[x]` (per obs #66 / obs #97 5-commit pattern)
- [x] Track the change folder (`openspec/changes/agenda-readme-revamp/`) so it is staged for `sdd-archive`
- [x] Commit: `chore(tasks): mark agenda-readme-revamp apply phase tasks complete (3 RED + 1 GREEN + 1 TEST-FIX + 5 VERIFY) and track change folder`

## Dependencies

- Read `ReadmeApiSurfaceTest.php` to understand the existing 11 scenarios and the env-section-overhaul drift 2 negative-assertion pattern (lines 155-170)
- Read `composer.json` for stack references (Laravel 13, Pest 4.7.1, Filament 5.6)
- Read `openspec/AGENTS.md` (the PRD) for any cross-references
- Run `php artisan route:list --path=api --json` to verify route count is 18
- Run `git log --oneline -20` to verify all 9 archived cycles are visible in git history
- Verify the canonical content from proposal §"What changes" is locked and will be applied verbatim in GREEN

## Risks (per proposal)

- Line-number drift between proposal and RED commit (mitigated by verifying line numbers in RED commit and adjusting if shifted)
- TEST-FIX commit needed for the +9 line shift (4-5 of the 11 existing scenarios affected; handled by the no-amend rule + separate commit pattern, per `agenda-readme-cleanup` commit 0461eb3 precedent)
- Pint auto-fix during sdd-verify (1 fix likely; sdd-archive will bundle it with verify-report, per obs #97 DSC-1)
- MariaDB env unavailable in this session (SQLite 150+4 is authoritative for this doc-only change, per obs #97 DEV-2 SUGGESTION)
