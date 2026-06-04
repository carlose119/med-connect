<!-- Source: openspec/changes/archive/agenda-prd-update/specs/agenda/prd-update/spec.md -- synced 2026-06-04 (agenda-prd-update archive) -->

# Capability: agenda/prd-update

## Purpose

Closes 3 drifts in `openspec/AGENTS.md` (the project's PRD and workflow contract) accumulated since the `env-section-overhaul` cycle (archived `6455b86`). AGENTS.md is the developer contract — a different file from README.md, explicitly out of scope for `env-section-overhaul` (obs #90 §Out-of-scope follow-ups). Applies the same doc-contract test pattern with a NEW test class `tests/Feature/Docs/AgentsDocContractTest.php` (Option A — per-file separation from `ReadmeApiSurfaceTest.php`).

The 3 drifts:
- **Line 69 (COSMETIC)**: PHP version claim overstates `composer.json` `^8.3` to `^8.4+`.
- **Line 75 (COSMETIC)**: Pest version claim understates the project (claims Pest 3, actually 4.7.1).
- **Line 58 (CORRECTNESS)**: unique partial index syntax describes only the PostgreSQL/SQLite form, but the actual migration is driver-aware (MariaDB/MySQL: generated `cancelled_marker` + UNIQUE KEY; PostgreSQL/SQLite: partial index with `WHERE`). Misleading wording would cause a future agent to apply only the PostgreSQL form when porting.

All 3 are line-level fixes; no behavior change, no new routes, no new entities. Drift 3 is the only non-trivial edit: the new wording must describe the driver-aware pattern for the test to validate it (4 sub-string assertions).

## Requirements

### REQ-PRD-UPDATE-1: AGENTS.md drift closure

The `openspec/AGENTS.md` claims MUST accurately reflect the current project state for 3 specific items. Each is enforced by a doc-contract test scenario in `tests/Feature/Docs/AgentsDocContractTest.php` (NEW, separate from `ReadmeApiSurfaceTest.php`). Drift in any claim is a test failure.

#### ADDED Scenarios

1. **Stack section PHP version (line 69)**
   - **Given** AGENTS.md's Stack section claims a PHP version for Laravel 13
   - **When** line 69 is read
   - **Then** it MUST contain `Laravel 13 (PHP 8.3+)` (not the stale `Laravel 13 (PHP 8.4+)`)
   - **And** the `8.3+` matches `composer.json` `"php": "^8.3"` (source of truth, not the local Laragon runtime).
   - **COSMETIC drift** — same fix as `env-section-overhaul` drift 1 but in AGENTS.md.

2. **Stack section Pest version (line 75)**
   - **Given** AGENTS.md's Stack section names the testing framework
   - **When** line 75 is read
   - **Then** it MUST contain `Pest 4 (modern Laravel default)` (not the stale `Pest 3 (modern Laravel default)`)
   - **And** the `4` matches `composer.json` `"pestphp/pest": "4.7.1"`.
   - **COSMETIC drift**.

3. **Unique partial index syntax (line 58) — CORRECTNESS drift**
   - **Given** AGENTS.md's PRD section documents the unique constraint on `appointments`
   - **When** line 58 is read
   - **Then** it MUST describe the driver-aware pattern, mentioning BOTH:
     - MariaDB/MySQL: generated `cancelled_marker` column + UNIQUE KEY on `(doctor_id, start_time, cancelled_marker)` (verified in `database/migrations/2026_06_01_000007_create_appointments_table.php` lines 30-45)
     - PostgreSQL/SQLite: partial unique index `(doctor_id, start_time) WHERE status != 'cancelled'` (verified lines 47-53)
   - **And** the new wording MUST contain at minimum 4 sub-strings: `MariaDB`, `PostgreSQL`, `cancelled_marker`, `WHERE status !=`. Missing any of the 4 fails the test.
   - **And** a future agent porting the pattern to MariaDB without reading the migration would apply only the PostgreSQL form, breaking the unique constraint for non-cancelled rows.

## Enforcement

Each scenario is enforced by a doc-contract test scenario in `tests/Feature/Docs/AgentsDocContractTest.php` (NEW, created by sdd-apply), following `ReadmeApiSurfaceTest.php`'s pattern but for AGENTS.md. Drift 3 is the most stringent — 4 sub-string assertions in a single test:

```php
test('AGENTS.md line 58 has correct driver-aware unique partial index description', function () {
    $lines = file(base_path('openspec/AGENTS.md'), FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeFalse('Could not read AGENTS.md');
    $line = $lines[57];
    expect($line)->toContain('MariaDB');
    expect($line)->toContain('PostgreSQL');
    expect($line)->toContain('cancelled_marker');
    expect($line)->toContain('WHERE status !=');
});
```

Content validation, not absence-of-old-wording. Removing any of the 4 sub-strings from the new wording fails the test.

Test verification:
- `vendor/bin/pest tests/Feature/Docs/AgentsDocContractTest.php` — all 3 scenarios pass.
- `vendor/bin/pest tests/Feature/Docs/ReadmeApiSurfaceTest.php` — all 11 existing scenarios still pass (regression check).

## Cross-references

- `openspec/specs/agenda/spec.md` — parent agenda capability
- `openspec/specs/agenda/env-section-overhaul/spec.md` — parallel prior-cycle sub-cap (identical doc-contract pattern, but tests README.md)
- `openspec/specs/agenda/readme-cleanup/spec.md` — closed the same PHP version drift in README.md
- `composer.json` — source of truth for scenarios 1 and 2
- `database/migrations/2026_06_01_000007_create_appointments_table.php` lines 27-53 — source of truth for scenario 3
- `tests/Feature/Docs/AgentsDocContractTest.php` (NEW) — AGENTS.md test class
- `tests/Feature/Docs/ReadmeApiSurfaceTest.php` — README.md test class (NOT modified; 11 existing scenarios still pass)
