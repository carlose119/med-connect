# Tasks: agenda-readme-drift

> Chain strategy: `stacked-to-main`. Cut `feat/readme-drift` from `main` (at `b8eb5e2`, post-`agenda-test-coverage` archive); FF-merge when the verification gate is green. No tracker branch.
> Review budget: **400 lines / PR**.
> TDD required: the RED commit (T-README-1) MUST FAIL on the current `main` README (which still has 5 stale `/api/me` references); the GREEN commit (T-README-2) MUST MAKE IT PASS by replacing the 5 strings.
> Test runner: `vendor/bin/pest` (Pest). No DB migrations, no new routes, no controllers — pure doc + 1 doc-contract test.
> Pre-existing baseline (from `agenda-test-coverage` archive): SQLite 130 passed + 3 skipped / MariaDB 133 passed. This change adds 3 scenarios in 1 new test class: expected **133 passed + 3 skipped** (SQLite) and **136 passed** (MariaDB).
> Out-of-scope (deferred to later changes): 5 cosmetic README drifts (Filament section, env section, Filament caveats), and the stale `GET /api/me` scenario at `openspec/specs/agenda/api/spec.md` lines 313-316 (tracked as `agenda-spec-drift`).
> TDD exception: per `work-unit-commits` skill, tests of existing behavior can land in a single RED+GREEN commit when the impl is trivial. **This change does NOT use that exception** — the RED and GREEN are separate commits to keep the doc-drift audit trail (red = "test asserts missing fix"; green = "fix applied, test passes"). The TDD discipline here is structural: the test IS the executable spec.

---

## Review Workload Forecast

| Field | Value |
|---|---|
| Estimated changed lines (hand-written) | ~35 LOC (20 test + 5 README + 10 metadata) |
| 400-line budget risk | **Low** — well under 400 |
| Chained PRs recommended | **No** — single PR, all 3 tasks are linearly dependent |
| Suggested split | Single PR (`feat/readme-drift` → `main`) |
| Delivery strategy | `ask-always` (per `openspec/AGENTS.md` preflight) |
| Chain strategy | `stacked-to-main` (locked in proposal) |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: stacked-to-main
400-line budget risk: Low

### Per-PR forecast

| PR | Title | Est. LOC (hand-written) | 400-line budget? | RED / GREEN / VERIFY commits |
|---|---|---|---|---|
| PR 1 | README drift fix (3 ADDED scenarios + 5 README string replacements) | ~35 | **Yes** (well under) | 1 red / 1 green / 1 verify = 3 commits |

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|---|---|---|---|
| 1 | Close 5 stale README references; add executable doc contract | PR 1 | base: `main` (at `b8eb5e2`); 1 test file NEW, 1 README MOD |

---

## PR 1 — README drift fix (1 PR, 3 commits, ~35 LOC)

### Phase 1: RED — write the failing doc-contract test

- [ ] **T-README-1 [RED]** — `test(docs): ReadmeApiSurfaceTest asserts no /api/me references + canonical auth routes`
  - File: `tests/Feature/Docs/ReadmeApiSurfaceTest.php` (NEW, ~20 LOC, 3 scenarios)
  - **Location**: new subdirectory `tests/Feature/Docs/`. Mirrors `tests/Feature/Schema/AppointmentsUniqueIndexTest.php` (also a non-HTTP contract test under Feature/). Picked Feature over Unit because the test uses `base_path()` (Laravel helper that needs the app bootstrapped via `Pest.php`'s `->extend(TestCase::class)->in('Feature')`).
  - **3 scenarios** (1:1 with `REQ-README-DRIFT-1`):
    1. `it('contains no retired /api/me references in README.md at lines 368, 394, 417, 421, 510')` — `file(base_path('README.md'), FILE_IGNORE_NEW_LINES)`; assert each of the 5 known stale lines does NOT contain `/api/me`. **Line-precise check** (not substring) so the legitimate `/api/medical-histories/{id}` substring on line 380 is not a false positive.
    2. `it('lists the canonical auth routes in the endpoint table')` — `expect($readme)->toContain('POST   | \`/api/auth/login\`')`; same for `/api/auth/logout` and `/api/auth/me` (exact pipe-and-padding form per line 366-368).
    3. `it('uses canonical /api/auth/me in curl examples')` — `preg_match_all('#curl[^\n]*?/api/auth/me\b#', $readme)` must be `>= 3` (auth flow line 394 + default-TZ line 417 + override-TZ line 421).
  - **No `uses(RefreshDatabase::class)`** — the test does not touch the DB. Pure filesystem read.
  - **MUST FAIL** at this commit: scenario 1 returns 5 stale matches; scenario 3 returns 0 curl hits to `/api/auth/me`; scenario 2 may already pass (the table row has the right format with the wrong path).
  - Commit: `test(docs): ReadmeApiSurfaceTest asserts no /api/me references + canonical auth routes (red)`
  - Test gate: `vendor/bin/pest --filter=ReadmeApiSurfaceTest` exits non-zero on this commit
  - Dependency: none

### Phase 2: GREEN — replace the 5 stale README references

- [ ] **T-README-2 [GREEN]** — `docs(readme): replace /api/me with /api/auth/me at lines 368, 394, 417, 421, 510`
  - File: `README.md` (MOD, 5 string replacements, ~5 LOC)
  - **5 replacements** (full context per occurrence to avoid ambiguity):
    1. **Line 368** (endpoint table row 3): `| 3  | GET    | \`/api/me\`                                           | any` → `| 3  | GET    | \`/api/auth/me\`                                      | any` (path 7 chars → 12 chars; trailing padding 43 → 38 spaces to preserve column alignment)
    2. **Line 394** (auth flow curl): `     http://127.0.0.1:8000/api/me` → `     http://127.0.0.1:8000/api/auth/me`
    3. **Line 417** (default-TZ curl): `curl -H "Authorization: Bearer $TOKEN" http://127.0.0.1:8000/api/me` → `curl -H "Authorization: Bearer $TOKEN" http://127.0.0.1:8000/api/auth/me`
    4. **Line 421** (override-TZ curl): `     "http://127.0.0.1:8000/api/me?tz=America/New_York"` → `     "http://127.0.0.1:8000/api/auth/me?tz=America/New_York"`
    5. **Line 510** (PR 3 prose): `covering the 10 read endpoints, the 3 transition endpoints, and the \`GET /api/me\` resource shape.` → `covering the 10 read endpoints, the 3 transition endpoints, and the \`GET /api/auth/me\` resource shape.`
  - **MUST PASS** at this commit: the 3 scenarios from T-README-1 all pass. Scenario 1 line-precise check: all 5 stale lines now have `/api/auth/me`. Scenario 2 endpoint table: the 3 auth rows have the canonical paths. Scenario 3 curl count: 3 curl examples now hit `/api/auth/me`.
  - **Out of scope** (per proposal §Risks): do NOT reformat other table columns, do NOT fix other README drift, do NOT touch the 5 cosmetic SUGGESTIONs.
  - Commit: `docs(readme): replace /api/me with /api/auth/me at lines 368, 394, 417, 421, 510 (green)`
  - Test gate: `vendor/bin/pest --filter=ReadmeApiSurfaceTest` exits 0 (3 scenarios pass)
  - Dependency: T-README-1

### Phase 3: VERIFY — run both drivers + audit the diff

- [ ] **T-README-3 [VERIFY]** — `chore(test): verify agenda-readme-drift test suite on both drivers`
  - File: none
  - Commit: `chore(test): verify agenda-readme-drift test suite on both drivers (verify)`
  - The PR 1 acceptance gate (all 6 must pass before merge):
    1. `vendor/bin/pest` (SQLite in-memory) → **133 passed + 3 skipped** (was 130+3; +3 new scenarios in `ReadmeApiSurfaceTest`)
    2. `DB_CONNECTION=mariadb php artisan migrate:fresh --env=testing 2>&1 | Out-Null` → exits 0 (no schema changes)
    3. `DB_CONNECTION=mariadb vendor/bin/pest` → **136 passed** (was 133; +3 new scenarios; the new test class is the only addition on MariaDB)
    4. `git diff README.md` → 5 lines changed, each a `/api/me` → `/api/auth/me` replacement (1 endpoint table + 3 curl + 1 prose)
    5. `grep -c '/api/me' README.md` (post-fix) → **1** (the only remaining match is inside `/api/medical-histories/{id}` on line 380, which is a legitimate unrelated path; the 5 stale refs are gone)
    6. `git log --oneline -3` shows the expected commit shape with RED → GREEN → VERIFY order preserved
  - Test gate: all 6 above pass
  - Dependency: T-README-2

---

## PR 1 expected commit log (bottom-to-top, 3 commits)

```
<hash> chore(test): verify agenda-readme-drift test suite on both drivers (verify)                       [T-README-3]
<hash> docs(readme): replace /api/me with /api/auth/me at lines 368, 394, 417, 421, 510 (green)           [T-README-2]
<hash> test(docs): ReadmeApiSurfaceTest asserts no /api/me references + canonical auth routes (red)         [T-README-1]
```

**Auditability check**: red→green→verify order is preserved. The red test exercises the missing doc state (5 stale refs + 0 curl hits to canonical); the green fix is a mechanical 5-string replace; the verify gate confirms both drivers pass + the diff is exactly the 5 expected changes.

---

## PR 1 verification (must all pass before merge)

1. `vendor/bin/pest` on SQLite → **133 passed + 3 skipped** (was 130+3; +3 new scenarios in `ReadmeApiSurfaceTest`)
2. `DB_CONNECTION=mariadb vendor/bin/pest` → **136 passed** (was 133; +3 new scenarios; the new test class is the only addition on MariaDB)
3. `php artisan migrate:fresh --env=testing` → exits 0 (no schema changes; docs-only)
4. `git diff README.md` → 5 lines changed (1 endpoint table + 3 curl + 1 prose), each a `/api/me` → `/api/auth/me` replacement
5. `grep -c '/api/me' README.md` (post-fix) → 1 (only `/api/medical-histories/{id}` on line 380 remains, a legitimate unrelated path)
6. `git log --oneline -3` shows the expected commit shape with RED → GREEN → VERIFY order preserved
7. **Re-run `sdd-verify` after merge**: should return `pass` (REQ-README-DRIFT-1 is now covered by the executable test contract)

---

## PR 1 known risks to verify

- **R-README-1** (Low, cosmetic): line 368's table column alignment. The path `\`/api/me\`` (11 chars) becomes `\`/api/auth/me\`` (16 chars); trailing padding must shrink by 5 spaces. Verified visually by `git diff README.md`.
- **R-README-2** (Low, regex): scenario 1's line-precise check at the 5 specific line numbers distinguishes the retired `/api/me` from the legitimate `/api/medical-histories/{id}` substring (line 380). Confirmed by `grep -c '/api/me' README.md` returning 1 (only `/api/medical-histories` remains, expected).
- **R-README-3** (Low, scope creep): the proposal explicitly excludes other README drift. The test only guards the 5 specific lines, not the whole file. If broader drift coverage is needed, that's a follow-up `agenda-readme-cleanup` change.
- **R-README-4** (Low, spec drift): the stale `GET /api/me` scenario at `openspec/specs/agenda/api/spec.md` lines 313-316 is the same drift family. Deferred to `agenda-spec-drift`. This change does NOT modify the spec file.

---

## Open questions for sdd-apply

None. The proposal locks all decisions: line-precise check (not regex), test file at `tests/Feature/Docs/`, single PR, 3 tasks, RED→GREEN→VERIFY, no chained PRs.

---

## Cross-task dependency graph

```
T-README-1 → T-README-2 → T-README-3
```

Strict linear chain. RED precedes GREEN precedes VERIFY. No parallelism possible.

---

## Aggregate metrics

| Metric | PR 1 | Total |
|---|---|---|
| Tasks | 3 | **3** |
| RED commits (test files only) | 1 | **1** |
| GREEN commits (impl only) | 1 | **1** |
| VERIFY commits | 1 | **1** |
| TDD exceptions documented | 0 | **0** |
| Total commits | 3 | **3** |
| LOC estimate (hand-written) | ~35 | **~35** |
| New files (tests/) | 1 (`ReadmeApiSurfaceTest.php`) | **1** |
| Modified files (docs) | 1 (`README.md`) | **1** |
| New routes | 0 | **0** |
| Test scenario delta | +3 | **+3** |
| Spec/implementation drifts closed | 1 (5 README lines + 3 executable scenarios) | **1** |
