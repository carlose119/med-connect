<!-- Source: openspec/changes/env-section-overhaul/specs/agenda/env-section-overhaul/spec.md -->

# Capability: agenda/env-section-overhaul

## Purpose

Closes 3 env-section drifts in `README.md` accumulated since the `agenda-readme-cleanup` cycle (archived `cb1f2d3`): a PHP version claim overstating the `composer.json` `^8.3` constraint (line 8, repeated at line 347), a factually-wrong parenthetical claiming PHP 8.4 features like property hooks and asymmetric visibility (line 347, verified 0 matches in `app/`), and a stale "greenfield" phraseology from the pre-`agenda-core` era (line 350). All 3 drifts are mechanical line-level fixes — no behavior change, no new routes, no new entities.

The doc-contract test pattern (line-precise grep checks in `tests/Feature/Docs/ReadmeApiSurfaceTest.php`) is the enforcement mechanism, identical to the closed `agenda/readme-cleanup` and `agenda/readme-drift` sub-capabilities. This sub-capability extends that pattern with 1 new req and 3 ADDED scenarios; drift 2 includes a negative assertion to permanently ban the factually-wrong phrase.

## Requirements

### REQ-ENV-SECTION-OVERHAUL-1: README env-section drift closure

The `README.md` env-section claims MUST accurately reflect the current project state for the 3 items below. Each is enforced by a doc-contract test scenario in `tests/Feature/Docs/ReadmeApiSurfaceTest.php`. Drift in any of the 3 claims is a test failure.

#### ADDED Scenarios

1. **Stack section PHP version (line 8)**
   - **Given** the README's Stack section claims a PHP version for Laravel 13
   - **When** line 8 is read
   - **Then** it MUST contain `Laravel 13 (PHP 8.3+)` (not the stale `Laravel 13 (PHP 8.4+)`)
   - **And** the `8.3+` version matches `composer.json` `"php": "^8.3"` — the source of truth, not the local Laragon runtime (PHP 8.4.4 NTS, per `openspec/AGENTS.md`).

2. **Environment section PHP version + parenthetical removal (line 347)** — includes a negative assertion
   - **Given** the README's Environment section claims a PHP version and pins to PHP 8.4 features
   - **When** line 347 is read
   - **Then** it MUST contain `PHP 8.3+ (per composer.json)` (not the stale `PHP 8.4+ (the project pins to features available in 8.4 — e.g. property hooks, asymmetric visibility)`)
   - **And** the version `8.3+` matches `composer.json` `"php": "^8.3"`
   - **And** the original parenthetical is factually wrong (0 matches for PHP 8.4 features in `app/`; actual features used are PHP 8.0-8.2)
   - **And** — **negative assertion (defense-in-depth)** — the env section (lines 340-360) MUST NOT contain the phrases `property hooks` or `asymmetric visibility`. Enforced alongside the line-precise check.

3. **Stale "greenfield" phraseology removal (line 350)**
   - **Given** the README's Environment section mentions a "greenfield" state
   - **When** line 350 is read
   - **Then** it MUST NOT contain the phrase `greenfield before that needs no DB`
   - **And** this phrase is from the pre-`agenda-core` era; after `agenda-core` was archived (commits `0a17b3c`, `e2ecc74`) the project always needs a DB. The line is edited to remove the phrase.

## Enforcement

Each scenario in REQ-ENV-SECTION-OVERHAUL-1 is enforced by a doc-contract test scenario in `tests/Feature/Docs/ReadmeApiSurfaceTest.php`. The test scenarios are line-precise grep checks (with one negative assertion in scenario 2), identical in pattern to the 8 existing scenarios from `agenda-readme-drift` and `agenda-readme-cleanup`. After this change, the test class has 11 scenarios total (8 prior + 3 new).

The negative assertion in scenario 2 scans the env section (a 20-line window around line 347) for the banned phrases — a defense-in-depth check to prevent the factually-wrong parenthetical from being reintroduced.

Test verification:
- `vendor/bin/pest tests/Feature/Docs/ReadmeApiSurfaceTest.php` — all 11 scenarios must pass.
- `php artisan route:list --path=api | wc -l` — must report 18 (3 auth + 15 public, unchanged).

## Cross-references

- `openspec/specs/agenda/spec.md` — parent agenda capability
- `openspec/specs/agenda/readme-cleanup/spec.md` — parallel sub-capability; identical doc-contract test pattern
- `openspec/specs/agenda/readme-drift/spec.md` — first sub-capability to use the doc-contract test pattern
- `composer.json` — source of truth for the PHP version claim (scenarios 1, 2); `"php": "^8.3"` verified
- `app/` — source of truth for the negative assertion in scenario 2; 0 matches for PHP 8.4 features verified
- `openspec/AGENTS.md` §Local environment — PHP 8.4.4 NTS is the local runtime, not the README source of truth
- `vendor/bin/pest` — test counts unchanged (141+4 SQLite / 145 MariaDB); route count unchanged (18)
