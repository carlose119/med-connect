# Proposal: agenda-spec-drift

## Intent

`openspec/specs/agenda/api/spec.md` has 1 stale `GET /api/me` scenario at lines 313-316 that contradicts the canonical `GET /api/auth/me` documented earlier in REQ-API-7 (introduced in `agenda-http` PR 4). The spec is the source of truth; this drift leaks a wrong route into agentic readers. Flagged as out-of-scope in `agenda-readme-drift` (obs #55). Closing it the same way: 1 MODIFIED scenario + 1 line-precise spec-guard test.

## Scope

### In Scope
- `openspec/specs/agenda/api/spec.md`: 1 scenario at lines 313-316 modified, `/api/me` → `/api/auth/me` on lines 313 + 315
- `tests/Feature/Docs/AgendaApiSpecCanonicalRoutesTest.php` (new): Pest test asserts the scenario at lines 313-316 uses `/api/auth/me` (line-precise, same shape as `ReadmeApiSurfaceTest`)

### Out of Scope
- The 3 `/api/medical-histories/{id}` substrings at lines 325/330/335 — not drift
- The 5 README refs closed in `agenda-readme-drift` (already at `d3b4ef9`)
- Code, routes, controllers, `MeTest` — codebase already follows canonical routes

## Capabilities

### New Capabilities
None. The drift lives inside an existing requirement.

### Modified Capabilities
- `agenda/api`: REQ-API-7 "Endpoint Contracts" gets 1 MODIFIED scenario. Heading "GET /api/me returns 200…" → "GET /api/auth/me returns 200…"; the **When** clause path changes. Requirement prose and the other 18 scenarios stay untouched.

## Approach

Same shape as `agenda-readme-drift`: 1 PR, 3 commits, RED→GREEN→VERIFY, ≤ 25 LOC. Use `work-unit-commits` for reviewable commits.

1. **RED** — add the test asserting lines 313-316 contain `/api/auth/me`. Watch fail on `main`.
2. **GREEN** — modify 2 lines in the spec (313 + 315), keep the GIVEN/THEN block.
3. **VERIFY** — re-run new test + full suite on both drivers.

Delta spec at `openspec/changes/agenda-spec-drift/specs/agenda/api/spec.md` describes the MODIFIED scenario. After archive, the canonical spec updates and the folder moves to `openspec/changes/archive/`.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `openspec/specs/agenda/api/spec.md` | Modified | 2 string replacements at lines 313 + 315 (~2 LOC) |
| `tests/Feature/Docs/AgendaApiSpecCanonicalRoutesTest.php` | New | 1 Pest test (~15 LOC, line-precise) |
| `openspec/changes/agenda-spec-drift/` | New | proposal + specs + design + tasks |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Line numbers drift if other scenarios are added | Low | Lock in a `const` at the top of the test (like `README_DRIFT_STALE_LINES`) |
| Naïve `not->toContain('/api/me')` trips on `/api/medical-histories/{id}` | Med | Test is line-precise at 313-316, not file-wide; the 3 legitimate matches are outside the asserted block |
| Scope creeps into a broader spec audit | Low | Test guards only the 1 known stale scenario |

## Rollback Plan

`git revert <merge-sha>`. Spec reverts, test is removed, no code paths affected.

## Dependencies

`agenda-readme-drift` archived (`d3b4ef9`); `agenda-http` archived (defines the canonical route).

## Success Criteria

- [ ] Spec line 313 reads "GET /api/auth/me" (heading); line 315 reads "GET /api/auth/me" (When)
- [ ] `AgendaApiSpecCanonicalRoutesTest` passes; full suite green on SQLite (131+3) and MariaDB (134)
- [ ] `Select-String '/api/me' openspec/specs/agenda/api/spec.md` returns only the 3 legitimate `/api/medical-histories/{id}` substrings, zero standalone matches
- [ ] PR diff ≤ 20 LOC added + ≤ 5 LOC modified (well under 400-line budget)
- [ ] `sdd-verify` confirms the test is executable, not a tautology
