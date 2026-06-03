# Tasks: agenda-api-dedup

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~20 (15 test rewrite + 4 spec removal + 1 metadata) |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | single PR (3 commits: RED → GREEN → VERIFY) |
| Delivery strategy | ask-on-risk |
| Chain strategy | single-pr |
| 400-line budget risk | Low |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: single-pr
400-line budget risk: Low

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Rewrite test (count check) + remove duplicate scenario + verify on both drivers | PR 1 | base=main@`0ee4e01`; ~20 LOC; mirrors agenda-spec-drift cycle |

## Phase 1: Test rewrite (RED)

- [x] 1.1 Rewrite `tests/Feature/Docs/AgendaApiSpecCanonicalRoutesTest.php` (NOT a new file — class name still fits). Replace 3 line-precise scenarios + `SPEC_DRIFT_STALE_LINES` const with 3 Pest `it(...)` content/count scenarios that FAIL on `main` (current: 2 scenario headings, 4 raw `GET /api/auth/me` matches): (a) `preg_match_all('/^#### Scenario: GET \/api\/auth\/me/m', $spec) === 1` — scenario-heading count; (b) `$spec` contains `the client calls \`GET /api/auth/me\` with the bearer token` (kept scenario marker at line 215); (c) `$spec` does NOT contain the duplicate signature `**Given** any authenticated user\n- **When** the client calls \`GET /api/auth/me\`` (no bearer token). Update docblock to reference the dedup contract. Commit: `test(docs): AgendaApiSpecCanonicalRoutesTest asserts exactly 1 GET /api/auth/me scenario (red)`. Test gate: `vendor/bin/pest --filter=AgendaApiSpecCanonicalRoutesTest` exits non-zero on `main` (scenarios a + c fail; b passes as the kept-scenario regression anchor).

## Phase 2: Spec dedup (GREEN)

- [x] 2.1 Delete the 4-LOC scenario block at lines 313-316 of `openspec/specs/agenda/api/spec.md` (the `any authenticated user` form, no bearer token). Use `Edit` on the exact 4-LOC block; do NOT use a sed/regex replacement. Do NOT touch line 213 (canonical form) or the 3 `/api/medical-histories/{id}` substrings at 323/328/333 (out of scope per delta spec). Commit: `docs(spec): remove duplicate GET /api/auth/me scenario from agenda/api lines 313-316 (green)`. Test gate: `vendor/bin/pest --filter=AgendaApiSpecCanonicalRoutesTest` exits 0 (all 3 scenarios pass; count 2→1, kept preserved, duplicate signature gone).

## Phase 3: Verify

- [x] 3.1 `vendor/bin/pest --filter=AgendaApiSpecCanonicalRoutesTest` — 3 scenarios PASS (re-verify GREEN in isolation).
- [x] 3.2 `vendor/bin/pest` (SQLite) — 136 passed + 4 skipped (3 old replaced by 3 new; net 0).
- [x] 3.3 `DB_CONNECTION=mariadb php artisan migrate:fresh --env=testing 2>&1 | Out-Null` — exits 0.
- [x] 3.4 `DB_CONNECTION=mariadb vendor/bin/pest` — 140 passed (same 4-test SQLite→MariaDB delta as before).
- [x] 3.5 `git diff openspec/specs/agenda/api/spec.md` — 5 deletions, 0 added (4 LOC scenario + 1 trailing blank separator). Only the duplicate block + its trailing blank; no other drift.
- [x] 3.6 `Select-String '^#### Scenario: GET /api/auth/me' openspec/specs/agenda/api/spec.md | Measure-Object | Select-Object -ExpandProperty Count` returns 1.
- [x] 3.7 Commit: `chore(test): verify agenda-api-dedup test suite on both drivers (verify)`. Gate: all 6 prior steps pass; `git log --oneline -3` shows RED → GREEN → VERIFY.
