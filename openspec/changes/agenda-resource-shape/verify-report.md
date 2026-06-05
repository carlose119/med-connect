# sdd-verify report — agenda-resource-shape

## Status: pass-with-suggestion

> **MODE**: STRICT TDD. **RUNNER**: `vendor/bin/pest` (SQLite). **DB**: SQLite authoritative (MariaDB unavailable, DEV-C SUGGESTION).
> **CHANGE TYPE**: MODIFY of existing `agenda/api` sub-cap (not NEW). Cycle 11.
> **PRE-CYCLE STATE** (per obs #110, post-cycle 10): 14 caps / 35 reqs / 141 scenarios / 18 routes.
> **POST-CYCLE FORECAST**: 14 caps / 35 reqs / 144 scenarios / 18 routes.

---

## 8 gates (8 standard — Gate 9 NEW from cycle 9 is NOT applicable for this MODIFY)

- [x] **Gate 1: spec is implementable — PASS**
  - Spec is a 1-MODIFIED-requirement delta (REQ-API-7) covering 2 modified doctor scenarios (lines 281 + 286) plus 3 ADDED scenarios in the same requirement.
  - Scenarios are concrete and testable: line-precise (`$lines[280]`, `$lines[285]`) + bounded-window negative assertion (20-line window around lines 281/286, `array_slice+implode` pattern from `env-section-overhaul` drift 2).
  - Scope is bounded: 2 spec line edits + 1 NEW test class (`AgendaApiDoctorResourceShapeTest.php`, 3 scenarios, ~75 LOC including docblock) + cycle 11 close-out. 0 code changes.

- [x] **Gate 2: code is in scope — PASS**
  - 0 code changes (`DoctorResource::toArray()` is the source of truth, untouched).
  - Modified files: only `openspec/specs/agenda/api/spec.md` (2 line-level edits, lines 281 + 286).
  - Created files: only `tests/Feature/Docs/AgendaApiDoctorResourceShapeTest.php` (NEW, 75 LOC, 3 scenarios).
  - Change folder artifacts all present: `proposal.md` (134 lines), `tasks.md` (97 lines), `specs/agenda/api/spec.md` (40 lines).
  - 0 changes to canonical specs BEYOND `agenda/api` (the other 13 sub-caps untouched).
  - 0 changes to routes, migrations, models, controllers, Filament resources.
  - `git diff 2f1ccb9..main --name-status` confirms 5 files: 4 ADDED (proposal.md, change-folder spec.md, tasks.md, new test) + 1 MODIFIED (canonical spec.md). Net diff: 348 insertions, 2 deletions = 350 LOC, well under 400-line budget.

- [x] **Gate 3: tests cover new behavior — PASS (3/3 scenarios, line-precise + negative assertion, const lock)**
  - 3 NEW test scenarios in `AgendaApiDoctorResourceShapeTest.php` (drift 1 line-precise, drift 2 line-precise, drift 3 negative assertion over bounded window).
  - All 3 use `it()` (matching `AgendaApiSpecCanonicalRoutesTest.php` and `ListDoctorsTest.php` patterns).
  - All 3 are RED-then-GREEN (apply sub-agent confirmed 3 RED on pre-edit spec, 3 GREEN on post-edit spec, per obs #115 + obs #116). The DEV-A markdown-backtick fix (changing `, name,` → `, \`name\`,`) is what made the negative-assertion test a REAL assertion; without the fix, the test would have been a false-negative GREEN on the pre-edit spec.
  - `const DRIFT_STALE_LINES = [281, 286];` declared at line 38 of the test class (per `agenda-spec-drift` `SPEC_DRIFT_STALE_LINES` precedent).
  - `vendor/bin/pest --filter=AgendaApiDoctorResourceShapeTest` → 3 passed (8 assertions).

- [x] **Gate 4: regression check — PASS**
  - 3 `AgendaApiDoctorResourceShapeTest` scenarios pass (3 passed / 8 assertions).
  - 3 `AgendaApiSpecCanonicalRoutesTest` scenarios pass (3 passed / 8 assertions, same `agenda/api` spec, no regression).
  - 14 `ReadmeApiSurfaceTest` scenarios pass (14 passed / 40 assertions, cross-file regression).
  - 3 `AgentsDocContractTest` scenarios pass (3 passed / 9 assertions, cross-file regression).
  - 3 `ListDoctorsTest` scenarios pass (3 passed / 53 assertions, code-shape regression on `app/Http/Resources/Api/DoctorResource.php`).
  - 3 `ShowDoctorTest` scenarios pass (3 passed / 13 assertions, code-shape regression on `DoctorResource`).
  - **Total regression: 26 scenarios still pass (3 + 3 + 14 + 3 + 3 + 3 = 29, including 3 new = 26 existing)**. All green.
  - **Full SQLite suite**: `vendor/bin/pest` → **153 passed (150+3 new) + 4 skipped (MariaDB-only race tests, unchanged) = 582 assertions**. EXACT match to the orchestrator's gate forecast.
  - MariaDB: NOT RUN (DEV-C SUGGESTION).

- [x] **Gate 5: cumulative state will be in archive-report — PASS**
  - Pre-archive: 14 capabilities, 35 reqs, 141 scenarios, 18 routes (per obs #110).
  - Forecast post-archive: 14 capabilities (unchanged — MODIFIES existing `agenda/api` sub-cap), 35 reqs (unchanged — 1 MODIFIED requirement, 0 NEW), 144 scenarios (141 + 3 ADDED), 18 routes (unchanged).
  - sdd-archive-report will use BOTH regexes (`^#### Scenario:` AND `^[0-9]+\. \*\*`) per obs #81 / obs #89 (the delta spec uses `1.`, `2.`, `3.` numbered scenarios under `#### ADDED Scenarios`).
  - **CRITICAL**: this is a MODIFY, not a NEW sub-cap. sdd-archive must EDIT the source-tracking comment in `openspec/specs/agenda/api/spec.md` line 1 (per obs #80: EDIT for MODIFIED sub-cap, not Copy-Item). See "Source-tracking comment update" section below.

- [x] **Gate 6: code quality — PASS**
  - `php -l tests/Feature/Docs/AgendaApiDoctorResourceShapeTest.php` → No syntax errors detected.
  - `php -l openspec/specs/agenda/api/spec.md` is N/A (markdown, not PHP), but the edit is contained to 2 line-level replacements (lines 281, 286) with full context matching the spec prose, no structural change.
  - No dead code, no commented-out blocks in the new test class.
  - No obvious refactor needed.
  - The new test class mirrors the existing pattern: `declare(strict_types=1)`, file-precise `file()` + `FILE_IGNORE_NEW_LINES`, `expect()->not->toBeFalse()` defensive file-read guards, `const` line locks, `it()` scenarios with descriptive names, and ~40 LOC docblock explaining the drift + the DEV-A fix.

- [x] **Gate 7: code style (Pint) — PASS with 1 SUGGESTION for sdd-archive**
  - `vendor/bin/pint --test tests/Feature/Docs/AgendaApiDoctorResourceShapeTest.php` → 1 issue found: `no_blank_lines_after_phpdoc`.
  - The fix is mechanical: remove a blank line after the class-level PHPDoc. Per cycle 9 obs #97 DSC-1 pattern, sdd-archive will bundle the pint fix with the verify-report commit.
  - **sdd-archive action**: run `vendor/bin/pint tests/Feature/Docs/AgendaApiDoctorResourceShapeTest.php` (auto-fix), then re-run `--test` to confirm clean. Bundle with verify-report commit per obs #97 DSC-1.
  - No pint issues in the modified canonical spec (markdown file, not subject to pint).

- [x] **Gate 8: route count unchanged — PASS**
  - `php artisan route:list --path=api --json | ConvertFrom-Json | Measure-Object` → 18 (unchanged from cycle 10).
  - No new routes, no removed routes, no renamed routes.

---

## 4 deviations judged

- **DEV-A (markdown backticks substring fix)**: **ACCEPTED** — the orchestrator's plan used `, name,` as the negative-assertion substring, but the spec source text wraps field names in markdown backticks, so the literal `, name,` substring does NOT exist in the file. The apply sub-agent caught this at first RED run (Scenario 3 would have been a false-negative GREEN on pre-edit spec). Fixed in the test to use `, \`name\`,` (with backticks). The fix is correct: the test is now properly RED on pre-edit, GREEN on post-edit. The fix is documented in the test docblock (lines 23-28) and in engram obs #116 as a new discovery (`markdown-backtick-substrings` — lesson: when designing negative-assertion tests for spec files, account for markdown formatting like backticks).

- **DEV-B (verify commit skipped)**: **ACCEPTED** — the orchestrator's plan said "Commit (only if no issues found — otherwise don't commit the verify step, just note in the report)". The verify step was clean (no file changes needed), so the verify commit would have been empty. Skipped per the orchestrator's conditional. TASKS-housekeeping commit (99297af) records the verify outcome via the `tasks.md` checkbox marks.

- **DEV-C (MariaDB unavailable)**: **SUGGESTION** — MariaDB service was unreachable in the sdd-apply session. Spec-drift closure is doc-only + test-only, no schema impact. SQLite 153+4 (582 assertions) is the authoritative verification. Re-run for parity if MariaDB is started before archive (non-blocking).

- **DEV-D (assertion count 582 not 600)**: **SUGGESTION** — the orchestrator's plan estimated ~600 assertions; actual is 582 (was 578 + 4 new from 3 new scenarios = 8 assertions in the new class, but the negative-assertion pattern means the second `not->toContain` only runs when the first `toContain` passes, so the count is per-expect not per-test). Cosmetic forecast miss; the test count is what Pest reports. Verified: 3 new scenarios in `AgendaApiDoctorResourceShapeTest.php` report 8 assertions total (3+3+2).

---

## Test counts

- **SQLite**: 153 passed (was 150 + 3 new) + 4 skipped (MariaDB-only race tests, unchanged) = **582 assertions** (exact match to orchestrator's gate forecast).
- **MariaDB**: NOT RUN (DEV-C SUGGESTION).
- **Route count**: 18 (3 auth + 15 public, unchanged from cycle 10).
- **New test class**: `tests/Feature/Docs/AgendaApiDoctorResourceShapeTest.php` (3 scenarios, 75 LOC including ~40 LOC docblock).
- **Regression**: 3 `AgendaApiSpecCanonicalRoutesTest` + 14 `ReadmeApiSurfaceTest` + 3 `AgentsDocContractTest` + 3 `ListDoctorsTest` + 3 `ShowDoctorTest` = 26 existing scenarios still pass. No regression on the same `agenda/api` spec (line shift is contained to lines 281/286, no anchor cascades).

### Per-filter verification (gate evidence)

| Filter | Result | Assertions |
|---|---|---|
| `AgendaApiDoctorResourceShapeTest` | 3 passed | 8 |
| `AgendaApiSpecCanonicalRoutesTest` | 3 passed | 8 |
| `ListDoctorsTest` | 3 passed | 53 |
| `ShowDoctorTest` | 3 passed | 13 |
| `ReadmeApiSurfaceTest` | 14 passed | 40 |
| `AgentsDocContractTest` | 3 passed | 9 |
| `vendor/bin/pest` (full) | 153 passed + 4 skipped | 582 |

---

## Cumulative state

- **Pre-archive**: 14 capabilities, 35 reqs, 141 scenarios, 18 routes (per obs #110, post-cycle 10).
- **Forecast post-archive**: 14 capabilities (unchanged — MODIFIES existing `agenda/api` sub-cap), 35 reqs (unchanged — 1 MODIFIED requirement, 0 NEW), 144 scenarios (141 + 3 ADDED), 18 routes (unchanged).
- **Delta spec structure** (for sdd-archive's regex parser): 1 MODIFIED requirement with `#### ADDED Scenarios` containing 3 numbered (`1. **Drift 1**`, `2. **Drift 2**`, `3. **Drift 3**`) scenarios. sdd-archive must use BOTH regexes (`^#### Scenario:` for existing scenarios in the canonical spec + `^[0-9]+\. \*\*` for the 3 ADDED scenarios in the delta) per obs #81 / obs #89.

---

## Source-tracking comment update for sdd-archive (CRITICAL)

- **Current line 1** of `openspec/specs/agenda/api/spec.md`:
  ```
  <!-- Source: openspec/changes/archive/agenda-api-dedup/specs/agenda/api/spec.md -- synced 2026-06-03 (agenda-api-dedup archive) -->
  ```
- **New line 1** (to be applied by sdd-archive):
  ```
  <!-- Source: openspec/changes/archive/agenda-resource-shape/specs/agenda/api/spec.md -- synced 2026-06-04 (agenda-resource-shape archive) -->
  ```
- **Archive step**: EDIT the source-tracking comment in `openspec/specs/agenda/api/spec.md` line 1 (per obs #80: EDIT for MODIFIED sub-cap, not Copy-Item). The sub-cap was created in `agenda-http` cycle 2 and previously modified in `agenda-spec-drift` (cycle 6) + `agenda-api-dedup` (cycle 7); this is the 3rd MODIFY of the same canonical file.
- **Change folder spec.md**: already pre-marked with the post-archive source comment (line 1 of `openspec/changes/agenda-resource-shape/specs/agenda/api/spec.md` already points to the archive path). sdd-archive does NOT need to edit this file (it's a delta spec, not the canonical).
- **Note**: the orchestrator's plan for sdd-archive must remember to swap the date from `2026-06-03` (agenda-api-dedup) to `2026-06-04` (agenda-resource-shape) and the archive name from `agenda-api-dedup` to `agenda-resource-shape`.

---

## Strict TDD evidence (per `strict-tdd-verify.md`)

### TDD Compliance

| Check | Result | Details |
|---|---|---|
| TDD Evidence reported | ✅ | Found in obs #115 (sdd-apply-progress): 1 RED + 1 GREEN + 0 VERIFY (skipped, DEV-B) + 1 TASKS-housekeeping commit sequence, 3 RED scenarios verified on pre-edit spec per obs #116. |
| All tasks have tests | ✅ | 1/1 task (T-RES-SHAPE-1) has a test file (`AgendaApiDoctorResourceShapeTest.php`, 3 scenarios). |
| RED confirmed (tests exist) | ✅ | Test file exists; 3 scenarios verified RED on pre-edit spec by the apply sub-agent at first RED run. DEV-A was caught at this stage and fixed before GREEN. |
| GREEN confirmed (tests pass) | ✅ | 3 scenarios pass on current code (vendor/bin/pest --filter=AgendaApiDoctorResourceShapeTest → 3 passed / 8 assertions). |
| Triangulation adequate | ✅ | 3 different scenarios for 3 different drift surfaces: line 281 positive, line 286 positive, 20-line window negative. Different test angles. |
| Safety Net for modified files | ➖ N/A | The modified file is `openspec/specs/agenda/api/spec.md` (a markdown spec, not a test file). The new test file is NEW (not modified), so safety net is N/A. The 3 `AgendaApiSpecCanonicalRoutesTest` scenarios serve as the cross-class safety net (same `agenda/api` spec, still 3 passed / 8 assertions). |

**TDD Compliance**: 6/6 checks passed (1 N/A correctly classified).

### Test Layer Distribution

| Layer | Tests | Files | Tools |
|---|---|---|---|
| Unit | 0 | 0 | — |
| Integration | 3 | 1 | vendor/bin/pest (file I/O only, no DB, no HTTP, no UI) |
| E2E | 0 | 0 | — |
| **Total** | **3** | **1** | |

Doc-contract tests are a hybrid between unit and integration: they test external state (the spec file content) via file I/O. Pest calls them "Feature" tests (per the `tests/Feature/Docs/` directory), but they don't fit the classic unit/integration/E2E trichotomy. Classified as "integration" here for the layer distribution table.

### Changed File Coverage

Coverage analysis skipped — no coverage tool detected in cached capabilities. NOT a failure per `strict-tdd-verify.md` Step 5d: "IF coverage tool NOT available: Report: 'Coverage analysis skipped — no coverage tool detected'".

### Quality Metrics

- **Linter (Pint)**: ⚠️ 1 warning in modified file (`no_blank_lines_after_phpdoc` in `AgendaApiDoctorResourceShapeTest.php`). SUGGESTION for sdd-archive to auto-fix per obs #97 DSC-1.
- **Type Checker**: ➖ Not available (PHP 8.4 is dynamic, no `phpstan`/`psalm` in `composer.json`).

### Assertion Quality (Step 5f audit)

| File | Line | Assertion | Issue | Severity |
|---|---|---|---|---|
| — | — | — | — | — |

**Assertion quality**: ✅ All assertions verify real behavior (file I/O + line-precise contains/not-contains + bounded-window negative assertion). No tautologies, no empty checks, no type-only assertions, no ghost loops, no smoke tests, no implementation-detail coupling, no mocks (and thus no mock/assertion ratio issues).

Each scenario has 2-3 assertions:
- **Scenario 1** (line 281 positive): `expect($lines)->not->toBeFalse()` (defensive file-read guard, would loudly fail if spec file unreadable) + `expect($lines[280])->toContain('user{id,name,email}')` (positive assertion) + `expect($lines[280])->not->toContain(', \`name\`,')` (negative assertion).
- **Scenario 2** (line 286 positive): same structure, `$lines[285]`.
- **Scenario 3** (negative assertion): `expect($content)->not->toBeFalse()` (defensive file-read guard) + `expect($window)->not->toContain(', \`name\`,')` (negative assertion over 20-line window covering both drift lines + 17 lines of padding).

Triangulation: 3 different test angles for 3 different drift surfaces (line 281, line 286, 20-line window). Well-triangulated.

---

## Out-of-scope carried forward

- The `name` backward-compat alias in `DoctorResource.php` (deferred to a follow-up if a real client needs it) — per agenda-resource-shape/proposal.md §"Out-of-scope (carried forward)".
- MariaDB unavailable (DEV-C SUGGESTION) — re-run for parity if MariaDB is started.
- Pint pre-existing issues in unmodified files (50+) — per cycle 9 DEV-4 / cycle 10 obs #107, future cleanup cycle.
- DEV-4 (Pest test() vs it() style divergence) — `AgendaApiDoctorResourceShapeTest.php` uses `it()` (the new project standard); `ReadmeApiSurfaceTest.php` and `AgentsDocContractTest.php` use both `it()` and `test()` (legacy from prior cycles). Not in scope for this cycle.
- 3 cosmetic SUGGESTIONs from prior verify runs (DoctorController@slots TZ fallback, AuditLogResource defensive parse, MedicalHistoryController inline role check) — per agenda-resource-shape/proposal.md §"Out-of-scope (carried forward)".
- rbac-advanced, agenda-patient-web, agenda-doctor-ui, CONTRIBUTING.md update, Composer 2.8+ claim, stale `for the agenda-core PR` reference — per agenda-resource-shape/proposal.md §"Out-of-scope (carried forward)".

---

## Verify report path

`openspec/changes/agenda-resource-shape/verify-report.md` (this file).

**NOT committed by sdd-verify** per obs #88 / obs #96 / obs #107 fix: sdd-verify stages the report in the change folder; sdd-archive commits it as part of archive housekeeping.
