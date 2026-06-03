# Proposal: agenda-api-dedup

## Intent

`openspec/specs/agenda/api/spec.md` REQ-API-7 currently lists 2 scenarios for the same `GET /api/auth/me` endpoint (line 213 + line 313). The duplicate at line 313 was created as a side-effect of the recently archived `agenda-spec-drift` change (`0ee4e01`): the fix was a mechanical `/api/me` → `/api/auth/me` rename that left 2 scenarios describing the same behavior. This is a post-archive duplicate flagged in obs #57. The dedup removes the redundant scenario, leaving 1 canonical scenario for the endpoint.

## Scope

### In Scope
- `openspec/specs/agenda/api/spec.md` — REMOVE the duplicate `GET /api/auth/me` scenario at lines 313-316 (4 LOC removed). KEEP the canonical scenario at lines 213-216 (more precise: mentions bearer token, "an authenticated user" Given clause).
- `tests/Feature/Docs/AgendaApiSpecCanonicalRoutesTest.php` — REWRITTEN. Drop the 2 line-precise scenarios (assertions against lines 313 + 315 no longer apply — that scenario is removed). Add 1 count check: "exactly 1 scenario for `GET /api/auth/me` exists in REQ-API-7." Keep the surrounding-lines guard, re-anchored to the post-dedup layout.

### Out of Scope
- The 3 legitimate `/api/medical-histories/{id}` substrings at lines 323/328/333 (different endpoint family, not drift)
- Other spec drift (none expected; this is purely dedup)
- Renaming the scenario or moving it to a different requirement
- The `MeTest` Pest class (already targets `/api/auth/me` correctly)
- Code, routes, controllers (codebase already follows canonical routes)
- `agenda-readme-drift` README fixes (already at `d3b4ef9`)

## Capabilities

### New Capabilities
None.

### Modified Capabilities
- `agenda/api`: REQ-API-7 "Endpoint Contracts" gets 1 REMOVED scenario (the duplicate `GET /api/auth/me` at original line 313). Kept scenario at line 213 stays untouched. All other 28 scenarios stay untouched.

## Approach

1 PR, 3 commits, RED→GREEN→VERIFY, ≤ 25 LOC. Same shape as the sibling `agenda-spec-drift` (`d78435d` → `ca61787` → `140f16a`).

1. **RED** — rewrite `AgendaApiSpecCanonicalRoutesTest` to assert the new contract: `preg_match_all('/GET \/api\/auth\/me/', $spec)` returns exactly 1 match. Watch fail on `main` (currently returns 2).
2. **GREEN** — delete lines 313-316 from the spec. Surrounding scenarios (line 318 specialties, line 323 medical histories) stay anchored.
3. **VERIFY** — re-run new test + full suite on both drivers (SQLite 131+N, MariaDB 134).

Delta spec at `openspec/changes/agenda-api-dedup/specs/agenda/api/spec.md` describes the REMOVED scenario. After archive, the canonical spec has 1 `GET /api/auth/me` scenario and the test guards the count.

## Dedup strategy

**REMOVE the line 313 scenario entirely.** The two are near-duplicates with minor diffs: line 213 says "an authenticated user" + "with the bearer token"; line 313 says "any authenticated user" + drops the bearer token. Line 213 is strictly more precise (the bearer token IS the auth mechanism in Sanctum, so it's the more correct canonical form). No unique content to preserve — REMOVE is cleaner than MERGE.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `openspec/specs/agenda/api/spec.md` | Modified | 4 LOC removed (lines 313-316) |
| `tests/Feature/Docs/AgendaApiSpecCanonicalRoutesTest.php` | Modified | Rewritten: 3 line-precise scenarios → 1 count check + 1 surrounding-lines guard re-anchored |
| `openspec/changes/agenda-api-dedup/` | New | proposal + specs + design + tasks |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Line numbers shift after the dedup | Low | Test switches to a count check (regex), not line-precise; immune to renumbering |
| Surrounding lines drift (specialties at 318, medical histories at 323) | Low | Re-anchor the "preserves the rest" guard to the post-dedup layout |
| Scope creeps into a broader spec audit | Low | Test guards only the 1 known duplicate |

## Rollback Plan

`git revert <merge-sha>`. Spec reverts, test reverts, no code paths affected.

## Dependencies

- `agenda-spec-drift` archived at `0ee4e01` (created the post-archive duplicate this change closes).
- `agenda-readme-drift` archived at `d3b4ef9` (sibling pattern).

## Success Criteria

- [ ] Spec has exactly 1 scenario for `GET /api/auth/me` in REQ-API-7
- [ ] `AgendaApiSpecCanonicalRoutesTest` passes (count check + surrounding-lines guard); full suite green on both drivers
- [ ] `Select-String 'GET /api/auth/me' openspec/specs/agenda/api/spec.md` returns exactly 1 match
- [ ] PR diff ≤ 25 LOC (well under 400-line budget)
- [ ] `sdd-verify` confirms the test is executable, not a tautology
