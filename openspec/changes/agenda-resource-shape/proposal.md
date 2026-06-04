# Proposal: agenda-resource-shape

## Why

The `DoctorResource` API resource at `app/Http/Resources/Api/DoctorResource.php` (52 LOC, extends `Illuminate\Http\Resources\Json\JsonResource` — the **API** resource, NOT a Filament resource) exposes a wire shape that nests `name` inside `user` (no top-level `name`), but the canonical spec at `openspec/specs/agenda/api/spec.md` (REQ-API-7, lines 281 + 286) describes a flat `name` field at the top level of the response.

This is a TYPE 1 drift (spec wrong, code right) carried as a follow-up since `agenda-http` (cycle 2). The `agenda-http` verify report (WARNING #2) pre-resolved it as "Amend the spec (preferred)". The `agenda-resource-shape` cycle (cycle 11) implements that decision. See engram obs #111 (sdd-explore) for the full Q1-Q7 analysis.

The fix is minimal: amend 2 spec lines, add 1 NEW doc-contract test class with 3 scenarios enforcing the spec, leave the code untouched. The existing `tests/Feature/Api/ListDoctorsTest.php` (3 scenarios) + `tests/Feature/Api/ShowDoctorTest.php` (3 scenarios) already assert the code shape (nested `user.name`), so the regression risk is zero.

## What changes

- **`openspec/specs/agenda/api/spec.md`** (MODIFIED): 2 string replacements at lines 281 + 286 in REQ-API-7 (~2 LOC)
  - Line 281: `each row has \`id\`, \`name\`, \`specialty{name,slug}\`, \`license_number\`` → `each row has \`id\`, \`user_id\`, \`specialty_id\`, \`license_number\`, \`user{id,name,email}\`, \`specialty{id,name,slug}\``
  - Line 286: `with \`id\`, \`name\`, \`specialty\`, \`bio\`, \`license_number\`` → `with \`id\`, \`user_id\`, \`specialty_id\`, \`license_number\`, \`bio\`, \`user{id,name,email}\`, \`specialty{id,name,slug}\``
- **1 NEW test class**: `tests/Feature/Docs/AgendaApiDoctorResourceShapeTest.php` with 3 ADDED scenarios enforcing the spec (~25 LOC)
- **0 code changes** — `DoctorResource` is the source of truth
- **0 routes, 0 migrations, 0 models, 0 controllers, 0 Filament resources affected**

## Scope

- **In scope**: 2 spec line edits + 1 NEW doc-contract test class with 3 scenarios + cycle 11 close-out
- **Out of scope**: PRD/AGENTS.md, README, other specs, routes, controllers, migrations, models, Filament resources; the `name` backward-compat alias in `DoctorResource.php` (deferred to a follow-up if a real client needs it)

## Approach

3-phase plan (mirrors `agenda-spec-drift` cycle 6 — the closest pattern sibling):

1. **RED** — create NEW `tests/Feature/Docs/AgendaApiDoctorResourceShapeTest.php` with 3 failing scenarios. All 3 MUST be RED on the current (wrong) spec content.
2. **GREEN** — edit `openspec/specs/agenda/api/spec.md` lines 281 + 286 to describe the actual wire shape. All 3 RED scenarios pass.
3. **VERIFY** — run full suite on SQLite (expect 153 passed + 4 skipped), regression-check the 6 existing `ListDoctorsTest` + `ShowDoctorTest` scenarios + 3 `AgendaApiSpecCanonicalRoutesTest` + 14 `ReadmeApiSurfaceTest` + 3 `AgentsDocContractTest`, verify route count unchanged at 18.

No TEST-FIX commit needed — the spec edit is contained to 2 lines (no line shift cascades to other tests' anchors, unlike `agenda-readme-revamp`).

## Design decision

**Option B — amend the spec to match the code** (locked by user + explore + `agenda-http` verify report WARNING #2 pre-resolution).

### Why B (not A, C, D)

- Pre-resolved by `agenda-http` verify report WARNING #2 ("Amend the spec (preferred)")
- The code is tested and consumed (by future `agenda-patient-web`); changing the code would break that contract
- The spec is prose; updating it is cheap (~2 LOC)
- The project's drift-closure pattern (cycles 4-10) all closed drifts by updating text/spec to match code
- **A** (add a top-level `name` alias in code) — backward-compat bonus, but not the minimal fix; deferred to a follow-up
- **C** (extract to new `agenda/doctor-resource-shape` sub-cap with per-row table) — overkill for 1 resource drift
- **D** (document drift, no fix) — first "accepted" drift, contradicts the project pattern

### Tradeoffs of B

- Consumers who read `data.name` (per the old spec) must update to `data.user.name` (per the new spec)
- The spec loses the "flat name" abstraction (consumers must go through `user.name`)
- These are minimal: `agenda-patient-web` hasn't been built yet; the existing 6 `ListDoctorsTest` + `ShowDoctorTest` scenarios already assert the nested shape

## Test scenarios (3 ADDED in NEW test class `AgendaApiDoctorResourceShapeTest.php`)

### Scenario 1 — Drift 1 (list row shape at line 281)
- **Name**: `it('agenda/api REQ-API-7 line 281 describes the list row wire shape with user nested', ...)`
- **Asserts**: spec line 281 contains `user{id,name,email}` (correct nested user shape), and does NOT contain `, name,` after `id` (the old flat name claim)

### Scenario 2 — Drift 2 (detail body shape at line 286)
- **Name**: `it('agenda/api REQ-API-7 line 286 describes the detail body wire shape with user nested', ...)`
- **Asserts**: spec line 286 contains `user{id,name,email}` (correct nested user shape), and does NOT contain `, name,` after `id` (the old flat name claim)

### Scenario 3 — Negative assertion (no top-level `name` in REQ-API-7 doctor scenarios)
- **Name**: `it('agenda/api REQ-API-7 doctor scenarios do not claim a top-level name field', ...)`
- **Asserts**: a 10-line window (lines 280-290) of the spec does NOT contain the literal `, name,` between `id` and `specialty` (old flat name claim). Pattern mirrors `env-section-overhaul` drift 2 (`ReadmeApiSurfaceTest.php` lines 155-170, `array_slice+implode` over a bounded window).

## Cumulative state forecast

- **Pre-archive**: 14 capabilities, 35 reqs, 141 scenarios, 18 routes (per obs #110, post-cycle 10)
- **Post-archive**: 14 capabilities (unchanged), 35 reqs (unchanged), 144 scenarios (141 + 3 ADDED), 18 routes (unchanged)
- **Note**: this is a MODIFY of the existing `agenda/api` sub-cap (1 MODIFIED scenario at line 281 + 1 MODIFIED scenario at line 286), not a NEW sub-cap. The cumulative state changes only in the scenario count (3 ADDED in the existing `agenda/api` sub-cap).

## Affected areas

| Area | Impact | Description |
|------|--------|-------------|
| `openspec/specs/agenda/api/spec.md` | Modified | 2 string replacements at lines 281 + 286 (~2 LOC) |
| `tests/Feature/Docs/AgendaApiDoctorResourceShapeTest.php` | New | 1 Pest test (~25 LOC, 3 scenarios, `it()` style, `const` line locks) |
| `app/Http/Resources/Api/DoctorResource.php` | None | Unchanged (code is the source of truth) |
| `tests/Feature/Api/ListDoctorsTest.php` | None | Unchanged (already asserts code shape: `data.user`, `data.user_id`) |
| `tests/Feature/Api/ShowDoctorTest.php` | None | Unchanged (already asserts code shape: `data.user.name`, `data.specialty.name`) |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Spec line numbers drift if other scenarios are added | Low | Lock lines 281 + 286 as `const DRIFT_STALE_LINES` at the top of the test class (per `agenda-spec-drift` `SPEC_DRIFT_STALE_LINES` pattern) |
| Negative-assertion false positive from the `, name,` substring elsewhere in REQ-API-7 | Low | Bounded 10-line window (lines 280-290) covers both drift lines + 8 lines of padding; the old `, name,` substring is unique to lines 281 + 286 and doesn't appear elsewhere in REQ-API-7 (verified in the current spec: the next `name` mention is in line 280's `When` clause for `?specialty_id= ?q=`, which is far from the asserted block) |
| 0 code changes — fix is doc-only + test-only | Low | Existing 6 `ListDoctorsTest` + `ShowDoctorTest` scenarios already assert the code shape; VERIFY regression check |
| Pint auto-fix during sdd-verify | Low | 1 fix likely; sdd-archive will bundle it with verify-report per obs #97 DSC-1 |
| MariaDB env unavailable in this session | None | SQLite 153+4 is authoritative for this doc-only change (per obs #97 DEV-2 pattern) |
| Cross-class regression on the same `agenda/api` spec | Low | Spec edit is contained to 2 lines (no line shift cascades to other tests' anchors, unlike `agenda-readme-revamp` where the +9 line shift cascaded to 5 existing scenarios); VERIFY cross-class checks for `AgendaApiSpecCanonicalRoutesTest` (3) + `ReadmeApiSurfaceTest` (14) + `AgentsDocContractTest` (3) |

## Rollback plan

`git revert <merge-sha>`. The change is doc-only + test-only; no code paths affected. Reverting the merge commit fully restores the prior spec state (lines 281 + 286 reverted) and removes the 1 new test class (`AgendaApiDoctorResourceShapeTest.php`).

## Dependencies

- `agenda-http` archived (defines the canonical wire shape — `DoctorResource` is the source of truth; pre-resolved WARNING #2)
- `agenda-spec-drift` archived (cycle 6, the closest pattern sibling — also modifies REQ-API-7, 1-MODIFIED-scenario delta, `SPEC_DRIFT_STALE_LINES` const pattern)
- `agenda-readme-revamp` archived (cycle 10, the latest cycle, defines the 4-commit pattern: RED → GREEN → VERIFY → TASKS-housekeeping)
- `env-section-overhaul` archived (drift 2 negative-assertion pattern at `ReadmeApiSurfaceTest.php` lines 155-170)

## Out-of-scope (carried forward)

- The `name` backward-compat alias in `DoctorResource.php` (deferred to a follow-up if a real client needs it)
- agenda-readme-revamp (already closed in cycle 10)
- rbac-advanced (Filament Shield integration)
- agenda-patient-web (future patient-facing web)
- agenda-doctor-ui (Filament doctor "view my appointments" UI)
- CONTRIBUTING.md update (document 4-commit pattern)
- Composer 2.8+ claim (README line 357, borderline, no `engines` constraint)
- Stale `for the agenda-core PR` reference (README line 359)
- 3 cosmetic SUGGESTIONs from prior verify runs (DoctorController@slots TZ fallback, AuditLogResource defensive parse, MedicalHistoryController inline role check)
- DEV-4 (Pest test() vs it() style divergence) — future cleanup cycle
- 50+ pre-existing pint issues in unmodified files — future cleanup cycle
- DEV-2 (MariaDB unavailable) — re-run for parity if MariaDB is started

## Success criteria

- [ ] Spec line 281 reads `each row has \`id\`, \`user_id\`, \`specialty_id\`, \`license_number\`, \`user{id,name,email}\`, \`specialty{id,name,slug}\``
- [ ] Spec line 286 reads `the body is \`{"data":<DoctorResource>}\` with \`id\`, \`user_id\`, \`specialty_id\`, \`license_number\`, \`bio\`, \`user{id,name,email}\`, \`specialty{id,name,slug}\``
- [ ] All 3 new `AgendaApiDoctorResourceShapeTest` scenarios pass (and were RED on the pre-edit spec)
- [ ] Full suite green on SQLite: **153 passed (150+3) + 4 skipped** (MariaDB-only race tests, unchanged)
- [ ] All 6 existing `ListDoctorsTest` (3) + `ShowDoctorTest` (3) scenarios still pass (regression on the code shape)
- [ ] All 3 existing `AgendaApiSpecCanonicalRoutesTest` scenarios still pass (regression on the same `agenda/api` spec)
- [ ] All 14 existing `ReadmeApiSurfaceTest` scenarios still pass (regression on the cross-class contract)
- [ ] All 3 existing `AgentsDocContractTest` scenarios still pass (regression on the cross-class contract)
- [ ] Route count unchanged at 18
- [ ] PR diff ≤ 30 LOC added + ≤ 5 LOC modified (well under 400-line budget)
- [ ] `sdd-verify` confirms the 3 new tests are executable, not tautologies (all 3 RED on pre-edit spec, all 3 GREEN on post-edit spec)
