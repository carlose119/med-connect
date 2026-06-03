<!-- Source: openspec/changes/archive/agenda-api-dedup/specs/agenda/api/spec.md -- synced 2026-06-03 (agenda-api-dedup archive) -->
# Delta for agenda/api (agenda-api-dedup)

## Purpose

This delta spec captures a REMOVED scenario in REQ-API-7 of the canonical
`agenda/api` sub-capability. After the `agenda-spec-drift` change archived
at `0ee4e01` closed the 2 stale `/api/me` references, REQ-API-7 ended up
with 2 scenarios describing the same `GET /api/auth/me` endpoint:

- The canonical scenario at lines 213-216 (mentions "an authenticated user"
  and "with the bearer token" — the more precise Sanctum form).
- The post-archive duplicate at lines 313-316 (uses "any authenticated
  user" and omits the bearer token — strictly less precise).

This change REMOVES the duplicate at lines 313-316 (4 LOC). No unique
behavior is covered by the duplicate, so REMOVE is cleaner than MERGE.
After archive, `Select-String 'GET /api/auth/me' openspec/specs/agenda/api/spec.md`
returns exactly 1 match.

This is the first REMOVED scenario in med-connect. The `## REMOVED Scenarios`
section is a delta-only marker; `sdd-apply` removes the scenario text from
`openspec/specs/agenda/api/spec.md`; `sdd-verify` runs
`AgendaApiSpecCanonicalRoutesTest` (the rewritten count check) to prove
the dedup. Source-tracking HTML comment on line 1 follows the
`agenda-spec-drift` / `agenda-readme-drift` archive convention.

## MODIFIED Requirements

None.

## ADDED Requirements

None.

## REMOVED Scenarios

### Scenario: GET /api/auth/me returns 200 with the current user (REMOVED)

The post-archive duplicate of the canonical `GET /api/auth/me` scenario in
REQ-API-7. The scenario name is identical to the kept scenario at lines
213-216, so removal MUST be anchored by line range AND by the unique
Given/When text (the kept scenario uses "an authenticated user" + "with
the bearer token"; this one uses "any authenticated user" + drops the
bearer token mention).

#### Exact text to remove (4 LOC, was at lines 313-316)

```markdown
#### Scenario: GET /api/auth/me returns 200 with the current user
- **Given** any authenticated user
- **When** the client calls `GET /api/auth/me`
- **Then** the response is `200` and the body is `{"data":{"id","name","email","role"}}`
```

- **REMOVED from**: `openspec/specs/agenda/api/spec.md` REQ-API-7
- **Was at lines**: 313-316 (4 LOC)
- **Anchor (unique form)**: Given `any authenticated user` / When `the client calls GET /api/auth/me` (no `with the bearer token`)
- **Kept scenario (do NOT touch)**: lines 213-216 — Given `an authenticated user` / When `the client calls GET /api/auth/me with the bearer token`
- **Reason**: post-archive duplicate created by the `agenda-spec-drift` cycle (the `/api/me` → `/api/auth/me` rename was mechanical, leaving 2 scenarios describing the same behavior). The kept scenario is strictly more precise (the bearer token IS the auth mechanism in Sanctum, so it is the canonical form). No unique content to preserve. Flagged in obs #57.
- **Verify contract**: `Select-String 'GET /api/auth/me' openspec/specs/agenda/api/spec.md` returns exactly 1 match after archive.

## Out of scope (for this delta)

- **The 3 legitimate `/api/medical-histories/{id}` substrings at lines 323, 328, 333 of the canonical spec** — not drift; they name a different endpoint family. (Inherited from `agenda-spec-drift`'s out-of-scope list, still valid.)
- **The kept `GET /api/auth/me` scenario at lines 213-216** — strictly more precise, stays untouched.
- **All other 28 scenarios in REQ-API-7** — no changes; the dedup touches only the 1 duplicate.
- **Code, routes, controllers** — codebase already follows canonical routes; only the spec prose is being deduped.
- **Renaming the `MeTest` Pest class** — already targets `/api/auth/me` correctly.
- **`agenda-readme-drift` README fixes** — already at `d3b4ef9`.
