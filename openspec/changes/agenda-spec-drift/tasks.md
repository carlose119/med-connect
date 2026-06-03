# Tasks: agenda-spec-drift

> Chain `stacked-to-main` from `main`@`d3b4ef9`. RED MUST FAIL (`/api/me` stale at 313+315); GREEN MUST PASS.
> Baseline (obs #55): SQLite 133+4 / MariaDB 137. Post: 136+4 / 140 (+3 scenarios).
> Out-of-scope: lines 325/330/335; de-dup (obs #57); `MeTest` rename.
> TDD exception NOT used — RED/GREEN split (mirrors `agenda-readme-drift`).

---

## Review Workload Forecast

| Field | Value |
|---|---|
| Estimated changed lines | ~25 LOC (20 test + 2 spec + 3 metadata) |
| 400-line budget risk | Low |
| Chained PRs recommended | No — single PR, linearly dependent |
| Suggested split | Single PR (`feat/spec-drift` → `main`) |
| Delivery strategy | `ask-always` |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: stacked-to-main
400-line budget risk: Low

---

## PR 1 — Spec drift fix (1 PR, 3 commits, ~25 LOC)

### Phase 1: RED

- [x] **T-SPEC-DRIFT-1 [RED]** — `test(docs): AgendaApiSpecCanonicalRoutesTest asserts canonical routes at lines 313, 315`
  - File: `tests/Feature/Docs/AgendaApiSpecCanonicalRoutesTest.php` (NEW, ~20 LOC). Mirrors `ReadmeApiSurfaceTest.php`.
  - `const SPEC_DRIFT_STALE_LINES = [313, 315];` (1-indexed).
  - **3 scenarios** (line-precise):
    1. `it('uses canonical /api/auth/me at line 313')` — `$lines[312]` contains `/api/auth/me`.
    2. `it('uses canonical /api/auth/me at line 315')` — `$lines[314]` contains `/api/auth/me`.
    3. `it('preserves the rest at lines 300-340')` — lines 312, 314, 316-320 unchanged.
  - No DB. **MUST FAIL**.
  - Commit: `test(docs): AgendaApiSpecCanonicalRoutesTest asserts canonical routes at lines 313, 315 (red)`
  - Gate: `vendor/bin/pest --filter=AgendaApiSpecCanonicalRoutesTest` exits non-zero
  - Dependency: none

### Phase 2: GREEN

- [x] **T-SPEC-DRIFT-2 [GREEN]** — `docs(spec): replace /api/me with /api/auth/me in agenda/api at lines 313, 315`
  - File: `openspec/specs/agenda/api/spec.md` (MOD, 2 replacements, ~2 LOC)
  - **2 replacements** (full context):
    1. **Line 313**: `#### Scenario: GET /api/me returns 200 with the current user` → `#### Scenario: GET /api/auth/me returns 200 with the current user`
    2. **Line 315**: `- **When** the client calls \`GET /api/me\`` → `- **When** the client calls \`GET /api/auth/me\``
  - **MUST PASS** all 3 T-SPEC-DRIFT-1 scenarios.
  - Out of scope: lines 325/330/335; de-dup; `MeTest`.
  - Commit: `docs(spec): replace /api/me with /api/auth/me in agenda/api at lines 313, 315 (green)`
  - Gate: `vendor/bin/pest --filter=AgendaApiSpecCanonicalRoutesTest` exits 0
  - Dependency: T-SPEC-DRIFT-1

### Phase 3: VERIFY

- [x] **T-SPEC-DRIFT-3 [VERIFY]** — `chore(test): verify agenda-spec-drift test suite on both drivers`
  - File: none
  - Commit: `chore(test): verify agenda-spec-drift test suite on both drivers (verify)`
  - **PR 1 acceptance gate (all 6)**:
    1. `vendor/bin/pest` (SQLite) → **136 passed + 4 skipped** (sdd-apply MUST report actual)
    2. `DB_CONNECTION=mariadb php artisan migrate:fresh --env=testing 2>&1 | Out-Null` → exits 0
    3. `DB_CONNECTION=mariadb vendor/bin/pest` → **140 passed**
    4. `git diff openspec/specs/agenda/api/spec.md` → exactly 2 lines, each `/api/me` → `/api/auth/me`
    5. `grep -c '/api/me' openspec/specs/agenda/api/spec.md` (post-fix) → **3** (only 325/330/335)
    6. `git log --oneline -3` shows RED → GREEN → VERIFY
  - Dependency: T-SPEC-DRIFT-2

---

## Risks (all Low)

- **R1** (line drift): `SPEC_DRIFT_STALE_LINES` const is the single fix point.
- **R2** (regex): line-precise checks avoid `/api/medical-histories/{id}` false positives.
- **R3** (scope creep): post-archive duplicate scenario excluded (obs #57).
- **R4** (source comment): delta spec line 1 has `<!-- Source: .../archive/... -->` artifact; sdd-archive reconciles.

## Dependency graph

`T-SPEC-DRIFT-1 → T-SPEC-DRIFT-2 → T-SPEC-DRIFT-3` (strict linear chain).

## Open questions

None.
