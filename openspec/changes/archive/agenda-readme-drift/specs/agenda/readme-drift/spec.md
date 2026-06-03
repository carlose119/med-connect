<!-- Source: openspec/changes/archive/agenda-readme-drift/specs/agenda/readme-drift/spec.md -- synced 2026-06-02 (agenda-readme-drift archive) -->
# Capability: agenda/readme-drift

## Purpose
This capability enforces doc consistency between `README.md` and the canonical
API routes documented in `openspec/specs/agenda/api/spec.md` REQ-API-7. It
exists to prevent doc drift — the kind of inconsistency that arose when the
`/api/me` placeholder (introduced in `agenda-http` PR 1) was retired in favor
of `/api/auth/me` (in `agenda-http` PR 4) but the `README.md` continued to
reference the old path in 5 places: the endpoint table (line 368), the auth
flow curl example (line 394), the default-TZ curl example (line 417), the
override-TZ curl example (line 421), and the `agenda-http` PR 3 test-slice
mention (line 510). The test that backs this spec lives at
`tests/Feature/Docs/ReadmeApiSurfaceTest.php` and turns the contract into a
hard CI guard.

## ADDED Requirements

### Requirement: REQ-README-DRIFT-1 — README API references match canonical routes
The `README.md` file at the project root MUST NOT contain any reference to
the retired placeholder route `/api/me` (without the `/auth/` prefix). Where
the README documents the auth surface — in the endpoint table, in curl
examples, and in any prose that names the current-user route — it MUST use
the canonical paths from `openspec/specs/agenda/api/spec.md` REQ-API-7:
`POST /api/auth/login`, `POST /api/auth/logout`, and `GET /api/auth/me`. Any
change to the canonical routes in REQ-API-7 MUST trigger a corresponding
update to `README.md` and the test MUST continue to pass.

#### Scenario: README contains no retired `/api/me` references
- **Given** `README.md` exists at the project root
- **When** the file is read and scanned for the substring `/api/me`
- **Then** the substring `/api/me` MUST NOT appear
- **And** the only legitimate prefix in the auth surface is `/api/auth/me`

#### Scenario: Endpoint table lists the canonical auth surface
- **Given** `README.md` contains an endpoint table (the markdown table that
  documents public routes under "REST API" or "Authentication")
- **When** the table is parsed for the auth surface
- **Then** it MUST include a row whose path is `POST /api/auth/login`
- **And** it MUST include a row whose path is `POST /api/auth/logout`
- **And** it MUST include a row whose path is `GET /api/auth/me`

#### Scenario: Curl examples in the README target the canonical route
- **Given** `README.md` contains curl examples demonstrating bearer-token
  requests (the auth flow and the timezone strategy sections)
- **When** those examples are scanned for the URL the bearer token is sent
  against
- **Then** the URL MUST target `/api/auth/me`
- **And** it MUST NOT target the retired `/api/me` (with or without query
  string)

## Out of scope (for this delta)

- **Stale `GET /api/me` scenario at `openspec/specs/agenda/api/spec.md` lines 313-316** — same drift family, deferred to the follow-up `agenda-spec-drift` change.
- **Other README drift** (Filament section, env section, Filament caveats) — out of scope by design; the test only guards the retired `/api/me` substring.
- **Renaming the `MeTest` Pest class** — the class already targets `/api/auth/me` correctly; only the README prose mention is wrong.
- **Table column alignment on line 368** — implementation concern for `sdd-apply` (preserve exact padding when `/api/me` becomes `/api/auth/me`).
