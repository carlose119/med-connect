<!-- Source: openspec/changes/archive/agenda-readme-revamp/specs/agenda/readme-revamp/spec.md -- synced 2026-06-04 (agenda-readme-revamp archive) -->

# Capability: agenda/readme-revamp

## Purpose

Closes 3 drifts in the `README.md` `## Status` section (lines 323-343) accumulated since the `agenda-core` chained split was archived 9 cycles ago (commits `0a17b3c`, `e2ecc74`): a stale `## Status — agenda-core` subtitle (line 323), a flat 21-line prose block with no per-concern organization, and stale `**Feature-complete pending sdd-verify.**` text plus an obsolete `sdd-verify` next-step paragraph (lines 325-343). Subsequent cycles (4-9) closed drifts in OTHER README sections but left Status untouched. This sub-capability reorganizes the section into 4 h3 subsections (`Build status`, `Test status`, `SDD state`, `Roadmap`) reflecting cycle 9 state per obs #98 (13 caps, 34 reqs, 138 scenarios, 18 routes, 9 archived changes). The doc-contract test pattern (line-precise + h3-enumeration + negative-assertion) extends `ReadmeApiSurfaceTest.php`; drift 3 uses a negative assertion for defense-in-depth.

## Requirements

### REQ-README-REVAMP-1: README Status section structural refactor

The `README.md` `## Status` section MUST be organized as exactly 4 h3 subsections — `Build status`, `Test status`, `SDD state`, `Roadmap` — in that exact order, with a bare `## Status` heading (no `— agenda-core` subtitle) and no stale `Feature-complete pending` / `sdd-verify` text. Each claim is enforced by a doc-contract test scenario in `tests/Feature/Docs/ReadmeApiSurfaceTest.php`. Drift in any of the 3 claims is a test failure.

#### ADDED Scenarios

1. **Status section heading is `## Status` without the `agenda-core` subtitle (line 323)** — line-precise check
   - **Given** the README's `## Status` heading on line 323 is a frozen artifact from the cycle-1 agenda-core chained split
   - **When** line 323 is read
   - **Then** it MUST be exactly `## Status` (NOT `## Status — agenda-core`) — `toBe` exact match, not `toContain`
   - **And** the section has not been about `agenda-core` for 9 cycles.

2. **Status section has exactly 4 h3 subsections in fixed order (Build, Test, SDD state, Roadmap)** — h2-bounded enumeration
   - **Given** the section is reorganized from flat prose into h3 subsections
   - **When** h3 headings are extracted from the section (bounded by the next `## ...` h2)
   - **Then** the section MUST contain exactly 4 h3 headings matching `Build status`, `Test status`, `SDD state`, `Roadmap` in that order
   - **And** the `===` order check (`expect($headings)->toBe([...])`) is the contract; any other h3 fails.

3. **Status section omits the stale `Feature-complete pending sdd-verify` text** — negative assertion (defense-in-depth)
   - **Given** the section previously claimed `**Feature-complete pending sdd-verify.**` for an archived cycle
   - **When** a 40-line window starting at line 323 (`array_slice($lines, 322, 40)`) is scanned
   - **Then** the section MUST NOT contain the string `Feature-complete pending`
   - **And** the section MUST NOT contain the string `sdd-verify`
   - **And** the pattern mirrors `env-section-overhaul` drift 2 (lines 155-170 in `ReadmeApiSurfaceTest.php`); the 40-line window covers the new ~30-line section plus 8 lines of padding.

## Enforcement

Each scenario is enforced by a doc-contract test scenario in `tests/Feature/Docs/ReadmeApiSurfaceTest.php` (no new test class — extend the existing 11-scenario file). All 3 new scenarios use `it()` (NOT `test()`) to match the existing style (cycle 9 DEV-4 lesson). After this change, the class has 14 scenarios total (11 prior + 3 new).

The +9 line shift from the 21→30 line refactor moves 4-5 of the 11 existing scenarios' anchor lines (lines 346, 348, 349, 359, 463). The TEST-FIX commit per `agenda-readme-cleanup` precedent (commit `0461eb3`) handles the line-shift updates in a separate commit (no amend).

Test verification:
- `vendor/bin/pest tests/Feature/Docs/ReadmeApiSurfaceTest.php` — all 14 scenarios pass.
- `vendor/bin/pest` (SQLite) — 150 passed (147+3) + 4 skipped.
- `php artisan route:list --path=api` — 18 unchanged.

## Cross-references

- `openspec/specs/agenda/spec.md` — parent agenda capability
- `openspec/specs/agenda/readme-cleanup/spec.md`, `env-section-overhaul/spec.md`, `prd-update/spec.md` — parallel sub-caps (cycles 7, 8, 9)
- `openspec/changes/agenda-readme-revamp/proposal.md` §"What changes" — canonical 4-h3 content locked
- `openspec/changes/agenda-readme-revamp/design.md` §"Test extension plan" — test patterns locked
- `tests/Feature/Docs/ReadmeApiSurfaceTest.php` — extended test class (14 scenarios after this change)
- `README.md` lines 323-343 — current stale Status section (RED target); 30-line canonical replacement

## Notes

- **Line 323 verified at `0ae0b73`**: `## Status — agenda-core` (per obs #99). After GREEN, line 323 is `## Status`; 0-indexed `$lines[322]` is the assertion target.
- **Roadmap format**: 9-line `- [x] **cycle-name** — scope` checklist (one bullet per archived cycle).
- **Cumulative state**: pre-archive 13/34/138/18 (obs #98); post-archive 14/35/141/18. Routes unchanged.
- **No Modified, no Removed** — doc-only; 0 routes/migrations/models/controllers/Filament resources affected. Rollback via `git revert <merge-commit>`.
