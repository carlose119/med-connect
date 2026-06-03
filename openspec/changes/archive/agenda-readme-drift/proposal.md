# Proposal: agenda-readme-drift

## Intent

`README.md` references the retired placeholder route `/api/me` in 5 places (endpoint table, 3 curl examples, 1 PR 3 test-slice mention). Canonical routes live at `/api/auth/*` per `openspec/specs/agenda/api/spec.md` REQ-API-7. This breaks the README↔spec contract and misleads new contributors hitting the README first. Pure doc drift — flagged as out-of-scope in `agenda-http` verify re-run (obs #40) and `agenda-test-coverage` verify. Closing it with an executable doc contract.

## Scope

### In Scope
- `README.md`: 5× mechanical replace `/api/me` → `/api/auth/me` (lines 368, 394, 417, 421, 510)
- `tests/Feature/Docs/ReadmeApiSurfaceTest.php` (new): Pest test reads `README.md`, asserts no `/api/me` substring + `/api/auth/me` IS present

### Out of Scope
- Other README drift (Filament section, env section, Filament caveats) — only these 5 refs
- Stale `GET /api/me` scenario at `openspec/specs/agenda/api/spec.md` line 313-316 — same drift, defer to a follow-up `agenda-spec-drift` change
- Renaming `MeTest` Pest class — assertions already target `/api/auth/me`; only the README mention is wrong
- New routes, route tests, migrations, controllers — pure docs + 1 doc-contract test

## Capabilities

### New Capabilities
- `agenda/readme-drift`: executable doc contract asserting README API refs match canonical routes in `agenda/api`. 1 REQ, 2-3 scenarios.

### Modified Capabilities
None. `agenda/api` REQ-API-7 already lists `GET /api/auth/me` correctly (lines 198-216). Only the README drifted.

## Approach

RED → GREEN → VERIFY in 1 PR, ~3 commits, ≤ 30 LOC. Use the `work-unit-commits` skill for reviewable commits.

1. **RED** — add `ReadmeApiSurfaceTest` asserting no `/api/me` in README. Watch fail on main.
2. **GREEN** — replace the 5 strings; verify column alignment on line 368.
3. **VERIFY** — re-run new test + full suite; confirm 0 regression.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `README.md` | Modified | 5 string replacements (~5 LOC) |
| `tests/Feature/Docs/ReadmeApiSurfaceTest.php` | New | 1 Pest test (~15 LOC) |
| `openspec/changes/agenda-readme-drift/` | New | proposal + specs + design + tasks |
| `openspec/specs/agenda/readme-drift/spec.md` | New | 1 new capability spec |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Replace breaks table column alignment (line 368) | Low | Preserve exact padding; verify with `git diff` |
| Test too strict (catches `/api/me` in unrelated prose) | Low | Only legit ref is `/api/auth/me`; assertion is correct |
| Other README drift slips through | Med | Out of scope by design; test only guards `/api/me` |
| Spec file has its own stale `/api/me` scenario (line 313-316) | Low | Logged as follow-up; does not block this PR |

## Rollback Plan

`git revert <merge-sha>` (or `git reset --hard HEAD~1` on the feature branch). README reverts, test is removed, no code paths affected. No data migration, no API consumers to notify.

## Dependencies

None. Prerequisite: `agenda-http` archived (already true, main at `b8eb5e2`).

## Success Criteria

- [ ] README contains zero `/api/me` substrings (enforced by new Pest test)
- [ ] README references canonical `GET /api/auth/me` at all 5 sites
- [ ] `ReadmeApiSurfaceTest` passes; full suite still green
- [ ] PR diff ≤ 25 LOC added + ≤ 5 LOC modified (well under 400-line budget)
- [ ] `sdd-verify` confirms the test is executable, not a tautology
