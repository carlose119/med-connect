<!-- Source: openspec/changes/archive/agenda-resource-shape/specs/agenda/api/spec.md -- synced 2026-06-04 (agenda-resource-shape archive) -->

# Spec: agenda-resource-shape (delta)

## Purpose

The `DoctorResource` API resource at `app/Http/Resources/Api/DoctorResource.php` exposes a wire shape that nests `name` inside `user` (no top-level `name`), but the canonical spec at `openspec/specs/agenda/api/spec.md` REQ-API-7 lines 281 + 286 claim a flat top-level `name` field. This is a TYPE 1 drift (spec wrong, code right) carried as a follow-up since `agenda-http` (cycle 2). The `agenda-http` verify report (WARNING #2) pre-resolved it as "Amend the spec (preferred)". This delta spec defines the contract for amending REQ-API-7 to match the actual wire shape.

## MODIFIED Requirements

### REQ-API-7 — Endpoint Contracts (lines 281 + 286 amended)

The `GET /api/doctors` and `GET /api/doctors/{id}` endpoints MUST return a wire shape that nests `name` inside `user` (not at the top level), reflecting the actual `DoctorResource::toArray()` output: `id`, `user_id`, `specialty_id`, `license_number`, `bio`, `user{id,name,email}`, `specialty{id,name,slug}`.

(Previously: line 281 described each row as `id`, `name`, `specialty{name,slug}`, `license_number`; line 286 described the body as `id`, `name`, `specialty`, `bio`, `license_number` — a flat top-level `name` field that does not exist in the wire.)

#### ADDED Scenarios

1. **Drift 1 — list row shape (line 281)**: The spec line 281 MUST describe each row as having `id`, `user_id`, `specialty_id`, `license_number`, `user{id,name,email}`, `specialty{id,name,slug}` (NOT a flat top-level `name`). The phrase `each row has` MUST be followed by `id` and `user_id` (NOT `id` and `name`).

2. **Drift 2 — detail body shape (line 286)**: The spec line 286 MUST describe the body as having `id`, `user_id`, `specialty_id`, `license_number`, `bio`, `user{id,name,email}`, `specialty{id,name,slug}` (NOT a flat top-level `name`). The phrase `the body is` MUST be followed by `id` and `user_id` (NOT `id` and `name`).

3. **Drift 3 — negative assertion (no top-level `name`)**: A 20-line window around spec lines 281 + 286 (lines 276-295, 1-indexed) MUST NOT contain the literal string `, name,` between `id` and `specialty` — the old flat name claim. Negative assertion pattern from `env-section-overhaul` (drift 2) is the template.

## ADDED Requirements

None.

## REMOVED Requirements

None.

## Notes

- The 3 ADDED scenarios are implementable as doc-contract tests in a NEW `tests/Feature/Docs/AgendaApiDoctorResourceShapeTest.php` (no existing test class covers this drift).
- Pattern sibling: `agenda-spec-drift` (cycle 6) — 1 MODIFIED scenario in the same `agenda/api` REQ-API-7 file, 1 NEW test class with 3 scenarios, ~30 LOC total, 1 PR, 3-5 commits.
- 0 code changes: `DoctorResource::toArray()` is the source of truth and is tested by `tests/Feature/Api/ListDoctorsTest.php` (3 scenarios) + `tests/Feature/Api/ShowDoctorTest.php` (3 scenarios).
- 0 routes, 0 migrations, 0 models, 0 controllers, 0 Filament resources affected.
- Cumulative state after archive: 14 capabilities (unchanged — MODIFIES existing `agenda/api` sub-cap), 35 reqs (unchanged), 144 scenarios (141 + 3 ADDED), 18 routes (unchanged).
- Spec line numbers are locked at 281 + 286 (verified at `2f1ccb9`). If they shift between proposal and apply, the test class must use a `const DRIFT_STALE_LINES = [281, 286];` declaration to lock them, per `agenda-spec-drift` precedent.
