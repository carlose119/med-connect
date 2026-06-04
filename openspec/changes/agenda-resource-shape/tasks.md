# Tasks: agenda-resource-shape

> Chain `stacked-to-main` from `main`@`2f1ccb9` (post-cycle 10 archive). RED MUST FAIL on the current (wrong) spec content; GREEN MUST PASS.
> Baseline (post-cycle 10, per obs #110): SQLite 150+4 / 18 routes / 14 caps / 35 reqs / 141 scenarios. Post: SQLite 153+4 / 18 routes / 14 caps / 35 reqs / 144 scenarios (+3 ADDED).
> Out-of-scope: `name` alias in `DoctorResource`; AGENTS.md; README; other specs.
> TDD exception NOT used — RED/GREEN split (mirrors `agenda-spec-drift` cycle 6).

---

## Review Workload Forecast

| Field | Value |
|---|---|
| Estimated changed lines | ~30 LOC (25 test + 2 spec + 3 metadata) |
| 400-line budget risk | Low |
| Chained PRs recommended | No — single PR, linearly dependent |
| Suggested split | Single PR (`feat/agenda-resource-shape` → `main`) |
| Delivery strategy | `ask-always` (AGENTS.md preflight) |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: stacked-to-main
400-line budget risk: Low

---

## PR 1 — DoctorResource wire-shape spec drift fix (1 PR, 4 commits, ~30 LOC)

### Phase 1: RED

- [x] **T-RES-SHAPE-1 [RED]** — `test(docs): AgendaApiDoctorResourceShapeTest asserts nested user shape in agenda/api REQ-API-7 lines 281, 286`
  - File: `tests/Feature/Docs/AgendaApiDoctorResourceShapeTest.php` (NEW, ~25 LOC). Mirrors `AgendaApiSpecCanonicalRoutesTest.php` style (uses `it()`, line-precise, `const` line locks).
  - `const DRIFT_STALE_LINES = [281, 286];` (1-indexed).
  - **3 scenarios** (all MUST FAIL on the current wrong spec):
    1. `it('agenda/api REQ-API-7 line 281 describes the list row wire shape with user nested')` — `$lines[280]` contains `user{id,name,email}` AND does NOT contain `, name,` after `id`.
    2. `it('agenda/api REQ-API-7 line 286 describes the detail body wire shape with user nested')` — `$lines[285]` contains `user{id,name,email}` AND does NOT contain `, name,` after `id`.
    3. `it('agenda/api REQ-API-7 doctor scenarios do not claim a top-level name field')` — negative assertion over a 10-line window (lines 280-290), `array_slice+implode` pattern from `env-section-overhaul` drift 2 (`ReadmeApiSurfaceTest.php` lines 155-170).
  - No DB. **MUST FAIL** (all 3 scenarios fail on the current `each row has \`id\`, \`name\`, ...` + `with \`id\`, \`name\`, ...` spec content).
  - Commit: `test(agenda-resource-shape): RED - create AgendaApiDoctorResourceShapeTest with 3 failing scenarios (drift 1 line 281, drift 2 line 286, drift 3 negative assertion)`
  - Gate: `vendor/bin/pest --filter=AgendaApiDoctorResourceShapeTest` exits non-zero
  - Dependency: none

### Phase 2: GREEN

- [x] **T-RES-SHAPE-2 [GREEN]** — `docs(spec): amend agenda/api REQ-API-7 lines 281 + 286 to describe the actual nested user wire shape`
  - File: `openspec/specs/agenda/api/spec.md` (MOD, 2 replacements, ~2 LOC)
  - **2 replacements** (full context, locked at proposal time):
    1. **Line 281**: `- **Then** the response is \`200\` with the paginated envelope; each row has \`id\`, \`name\`, \`specialty{name,slug}\`, \`license_number\`` → `- **Then** the response is \`200\` with the paginated envelope; each row has \`id\`, \`user_id\`, \`specialty_id\`, \`license_number\`, \`user{id,name,email}\`, \`specialty{id,name,slug}\``
    2. **Line 286**: `- **Then** the response is \`200\` and the body is \`{"data":<DoctorResource>}\` with \`id\`, \`name\`, \`specialty\`, \`bio\`, \`license_number\`` → `- **Then** the response is \`200\` and the body is \`{"data":<DoctorResource>}\` with \`id\`, \`user_id\`, \`specialty_id\`, \`license_number\`, \`bio\`, \`user{id,name,email}\`, \`specialty{id,name,slug}\``
  - **MUST PASS** all 3 T-RES-SHAPE-1 scenarios.
  - Out of scope: `name` alias in `DoctorResource`; the other 28 REQ-API-7 scenarios; other specs.
  - Commit: `fix(agenda-resource-shape): GREEN - close 2 DoctorResource wire-shape drifts in agenda/api REQ-API-7 (lines 281 + 286) to match the actual nested user shape`
  - Gate: `vendor/bin/pest --filter=AgendaApiDoctorResourceShapeTest` exits 0
  - Dependency: T-RES-SHAPE-1

### Phase 3: VERIFY

- [x] **T-RES-SHAPE-3 [VERIFY]** — `chore(test): verify agenda-resource-shape test suite on SQLite (full regression check)`
  - File: none
  - Commit: `chore(test): verify agenda-resource-shape test suite on SQLite (verify) - 153 passed + 4 skipped, 18 routes unchanged, MariaDB unavailable in this session`
  - **PR 1 acceptance gate (all 8)**:
    1. `vendor/bin/pest --filter=AgendaApiDoctorResourceShapeTest` → 3 passed
    2. `vendor/bin/pest --filter=ListDoctorsTest` → 3 passed (code-shape regression on `app/Http/Resources/Api/DoctorResource.php`)
    3. `vendor/bin/pest --filter=ShowDoctorTest` → 3 passed (code-shape regression on `app/Http/Resources/Api/DoctorResource.php`)
    4. `vendor/bin/pest --filter=AgendaApiSpecCanonicalRoutesTest` → 3 passed (cross-class regression on the same `agenda/api` spec)
    5. `vendor/bin/pest --filter=ReadmeApiSurfaceTest` → 14 passed (cross-class regression)
    6. `vendor/bin/pest --filter=AgentsDocContractTest` → 3 passed (cross-class regression)
    7. `vendor/bin/pest` (SQLite) → **153 passed (150+3) + 4 skipped** (MariaDB-only race tests, unchanged)
    8. Route count: `php artisan route:list --path=api --json | ConvertFrom-Json | Measure-Object` → 18 (unchanged)
  - Out of scope: MariaDB parity (env unavailable; SQLite authoritative for this doc-only change per obs #97 DEV-2)
  - Dependency: T-RES-SHAPE-2

### Phase 4: TASKS-housekeeping

- [x] **T-RES-SHAPE-4 [TASKS-housekeeping]** — `chore(tasks): mark agenda-resource-shape apply phase tasks complete and track change folder`
  - Mark all 4 tasks in this `tasks.md` file as `[x]` (per obs #66 / obs #97 4-commit pattern)
  - Track the change folder (`openspec/changes/agenda-resource-shape/`) for `sdd-archive`
  - Commit: `chore(tasks): mark agenda-resource-shape apply phase tasks complete (1 RED + 1 GREEN + 1 VERIFY + 1 housekeeping) and track change folder`
  - Dependency: T-RES-SHAPE-3

---

## Dependency graph

`T-RES-SHAPE-1 → T-RES-SHAPE-2 → T-RES-SHAPE-3 → T-RES-SHAPE-4` (strict linear chain).

## Risks (all Low)

- **R1** (line drift): `DRIFT_STALE_LINES` const is the single fix point; sdd-apply MUST verify the const matches the actual line numbers at apply time.
- **R2** (negative-assertion false positive): the 10-line window (lines 280-290) covers both drift lines + 8 lines of padding; the old `, name,` substring is unique to lines 281 + 286 and doesn't appear elsewhere in REQ-API-7 (verified in the current spec: the next `name` mention is in line 280's `When` clause `?specialty_id= ?q=`, which is well outside the asserted block).
- **R3** (cross-class regression): the spec edit is contained to 2 lines (no line shift propagates to other tests' anchors, unlike `agenda-readme-revamp` where the +9 line shift cascaded to 5 existing scenarios); VERIFY cross-class checks for `AgendaApiSpecCanonicalRoutesTest` (3) + `ReadmeApiSurfaceTest` (14) + `AgentsDocContractTest` (3).
- **R4** (Pint auto-fix): 1 fix likely; sdd-archive will bundle with verify-report per obs #97 DSC-1.
- **R5** (MariaDB unavailable in this session): SQLite 153+4 is authoritative for this doc-only change per obs #97 DEV-2 SUGGESTION.

## Open questions

None.
