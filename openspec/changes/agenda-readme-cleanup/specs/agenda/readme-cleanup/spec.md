<!-- Source: openspec/changes/agenda-readme-cleanup/specs/agenda/readme-cleanup/spec.md -->

# Capability: agenda/readme-cleanup

## Purpose

Closes 5 cosmetic README drifts that accumulated in `README.md` since the `agenda-readme-drift` cycle (archived `d3b4ef9`). The drifts cover migration counts, stale test counts, contradictory Node version claims, email domain mismatches, and a missing endpoint table row. All drifts are mechanical line-level fixes — no behavior change, no new routes, no new entities, no new architecture.

The doc-contract test pattern (line-precise grep checks in `tests/Feature/Docs/ReadmeApiSurfaceTest.php`) is the enforcement mechanism, identical to `agenda/readme-drift` (1 req, 3 ADDED scenarios). This new sub-capability extends that pattern with 1 new req and 5 ADDED scenarios, one per closed drift.

## Requirements

### REQ-README-CLEANUP-1: README cosmetic drift closure

The `README.md` MUST accurately reflect the current project state for the 5 claims below. Each claim is enforced by a doc-contract test scenario in `tests/Feature/Docs/ReadmeApiSurfaceTest.php`. Drift in any of the 5 claims is a test failure.

#### ADDED Scenarios

1. **Migration count breakdown (line 71)**
   - **Given** the README states the agenda-core migration count breakdown
   - **When** line 71 is read
   - **Then** it MUST contain `13 from PR 1+2` (not the stale `15 from PR 1+2`)
   - **And** the 2 Laravel default migrations (`0001_01_01_000001_create_cache_table.php` and `0001_01_01_000002_create_jobs_table.php`) are not from PR 1+2; the total `16` is correct.

2. **Test count (line 304)**
   - **Given** the README documents the PR 5 test slice
   - **When** line 304 is read
   - **Then** it MUST contain `136+4 on SQLite` and `140 on MariaDB` (not the stale `42 / 44` numbers from PR 5; 6 cycles have shipped since).

3. **Node version consistency (lines 313-314, 349)**
   - **Given** the README has contradictory Node version claims
   - **When** lines 314 and 349 are read
   - **Then** both MUST contain the aligned wording `Node 20.16+ (prints warning, build succeeds)`
   - **And** this reflects `node --version` (v20.16.0) and the project's actual Vite 7 build behavior; the prior `Node 20.19+` claim in the Environment section was an upstream Vite 7 requirement that does not apply to this project's actual node version.

4. **Email domain (lines 283, 464)**
   - **Given** the README references seeded user emails
   - **When** lines 283 and 464 are read
   - **Then** both MUST contain `med-connect.test` (not `med-connect.local`)
   - **And** this matches `database/seeders/DatabaseSeeder.php` lines 29, 47, 81 which create `admin@med-connect.test`, `doctor@med-connect.test`, and `patient@med-connect.test`.

5. **Route count and endpoint table completeness (line 360, lines 364-382)**
   - **Given** the README documents the API route count and endpoint table
   - **When** line 360 is read
   - **Then** it MUST contain `18 routes` (not the stale `19 routes`), broken down as `3 auth + 15 public`
   - **And** the endpoint table MUST include a row for `GET /api/doctors/{doctor}` (mapped to `Api\DoctorController@show`, between `/api/doctors` and `/api/doctors/{doctor}/slots`) — the table currently has 17 rows and is missing this one.

## Enforcement

Each scenario in REQ-README-CLEANUP-1 is enforced by a doc-contract test scenario in `tests/Feature/Docs/ReadmeApiSurfaceTest.php`. The test scenarios are line-precise grep checks, identical in pattern to the 3 existing scenarios from `agenda/readme-drift`. After this change, the test class has 8 scenarios total (3 from `agenda/readme-drift` + 5 from this sub-capability).

Test verification:
- `vendor/bin/pest tests/Feature/Docs/ReadmeApiSurfaceTest.php` — all 8 scenarios must pass.
- `php artisan route:list --path=api | wc -l` — must report 18 (3 auth + 15 public).

## Cross-references

- `openspec/specs/agenda/spec.md` — parent agenda capability
- `openspec/specs/agenda/readme-drift/spec.md` — parallel sub-capability for the closed `/api/me` family of drifts; the doc-contract test pattern is identical
- `database/seeders/DatabaseSeeder.php` — source of truth for the email domain claim (scenario 4)
- `php artisan route:list --path=api` — source of truth for the route count claim (scenario 5)
- `composer.json`, `package.json` — source of truth for the Node version claim (scenario 3)
- `database/migrations/` — source of truth for the migration count claim (scenario 1)
- `vendor/bin/pest` — source of truth for the test count claim (scenario 2)
