# archive-report — agenda-core

**Status**: completed
**Archived at**: 2026-06-01
**Source change**: `openspec/changes/agenda-core/` (now at `openspec/changes/archive/agenda-core/`)
**Canonical specs synced**: `openspec/specs/{agenda,doctor-schedule,users-roles,clinical-records,prescriptions,admin-audit}/spec.md`
**Verify verdict**: pass-with-warnings (46 passed on MariaDB / 44 passed + 2 skipped on SQLite, 9 TDD red→green pairs intact)
**Merge type**: first-time archive — no canonical content existed, so each delta spec was copied verbatim into the new canonical location with a source-of-truth header

## Executive summary

The agenda-core change (5-PR chained split, merged to `main` at `6ba4d6a`) is now archived. The 21 requirements across 6 capabilities have been promoted to the canonical spec store. Because this is the first archive in the med-connect project, the canonical `openspec/specs/` directory was empty; all 6 delta specs are copied verbatim with a header comment pointing back to the archive folder. The change folder is moved to `openspec/changes/archive/agenda-core/` and remains the historical record (no edits in place). The 6 verify-report warnings and 3 risks are documented below as context for the next change.

## Inventory and sync table

| Capability | Delta reqs | Delta scenarios | SHA-256 (first 16) | Delta bytes | Canonical state | Action | Final canonical |
|------------|-----------:|----------------:|--------------------|------------:|-----------------|--------|-----------------|
| `agenda/` | 5 | 12 | `A5B90C7AFDDF49FF` | 5,164 | not-present | copy verbatim | 5 reqs, 12 scenarios |
| `doctor-schedule/` | 3 | 7 | `7551C302670C34E0` | 3,036 | not-present | copy verbatim | 3 reqs, 7 scenarios |
| `users-roles/` | 5 | 11 | `B601F70CC75E763A` | 3,375 | not-present | copy verbatim | 5 reqs, 11 scenarios |
| `clinical-records/` | 3 | 6 | `949B36D14900D9E0` | 2,556 | not-present | copy verbatim | 3 reqs, 6 scenarios |
| `prescriptions/` | 3 | 4 | `ACF75393B5BF85EC` | 1,919 | not-present | copy verbatim | 3 reqs, 4 scenarios |
| `admin-audit/` | 2 | 4 | `F2B829C73ABB6C9A` | 1,755 | not-present | copy verbatim | 2 reqs, 4 scenarios |
| **Total** | **21** | **44** | — | **17,805** | — | — | **21 reqs, 44 scenarios** |

> **Note on the count**: the orchestrator prompt quoted "21 requirements, 42 scenarios" based on a stale estimate. The actual file-derived count is **21 requirements, 44 scenarios** — agenda has 12 (not 8), doctor-schedule has 7 (not 6), users-roles has 11 (not 12). The verify report's 44-scenario count matches the file. This report uses the file-derived number.

**Canonical file headers** (each `openspec/specs/<cap>/spec.md` opens with):

```html
<!-- Source: openspec/changes/archive/agenda-core/specs/<cap>/spec.md -- synced 2026-06-01 (first-time archive, no canonical content existed) -->
```

Because all 6 canonical specs were `not-present`, no requirement-ID conflict resolution was needed (the `-agenda-core` suffix rule did not apply). The merge is purely additive.

## What the next change inherits

The 6 warnings (W1–W6) from the verify report are documented deferrals. The next change that picks up clinical-records, prescriptions, doctor-schedule validation, admin-audit immutability, or PR-budget governance MUST address them. Also inherit these 3 risks.

### Warnings to address

- **W1 — 8 untested scenarios in `clinical-records` and `prescriptions`** (all deliberate deferrals per proposal §Out of Scope): `Append-Only Medical Notes` (3 scenarios: update impossible, delete impossible, amendment creates new note) + `Medical Attachments` (1 scenario: each attachment is a separate row) + `Prescription Ownership` (1) + `Unique Prescription Code` (1) + `Prescription Items as Rows` (2). When the future `clinical-records` and `prescriptions` changes land, add the 8 missing tests as the first task (red→green). The migrations already enforce the constraints (`$table->unique('unique_code')`, normalized tables, no JSON columns), but no Pest test pins them.
- **W2 — 3 immutability scenarios in `admin-audit` are untested**: `Audit rows have no updated_at column` (verified by reading the migration but not pinned in Pest), `Audit rows cannot be deleted through the public API` (the `AuditLog` model has no public mutator, but no test pins the rejection), `Audit rows cannot be updated through the public API` (same). Trivial to close with 1 unit test that asserts the model throws on `update()` and `delete()`.
- **W3 — `doctor-schedule` Schedule Validation 2/3 scenarios untested**: `Recurring rule with non-positive duration is rejected` and `Recurring rule with end before start is rejected` are NOT enforced at the application layer. The `DoctorSchedule` model has no validation rules. Add 2 model-level validation tests (or a custom rule) before the doctor UI lands.
- **W4 — `doctor-schedule` "Inactive recurring rule produces no slots" scenario is untested**: the service query has `->where('is_active', true)` (correct behaviour) but no test pins it. One-line test in `DoctorAvailabilityServiceTest`.
- **W5 — PRs 3, 4, 5 exceeded the 400-line review budget without an explicit `size:exception`**: PR 2 was explicitly approved with `size:exception` (forecast flagged 550–850, actual 1,572). PRs 3–5 were forecast as 250–500 LOC but landed at 700–3,552 LOC (PR 5 inflated by ~2,400 lines of auto-generated Filament assets). Going forward, get an explicit `size:exception` written into the apply-progress before cutting any PR whose forecast exceeds 400.
- **W6 — Consultorio default timezone is unresolved**: design.md §Open Questions said "Confirm the consultorio default timezone is `America/Argentina/Buenos_Aires` (the most likely PRD value) or another zone." The slot service accepts any tz, so behaviour is correct, but the `.env` default is `UTC`. Next change should add `APP_TIMEZONE=America/Argentina/Buenos_Aires` to `.env.example` (or whichever zone the user picks).

### Risks to monitor

- **R1 — 8 untested clinical-records + prescriptions scenarios are a real regression risk for the next change.** The migrations are the only contract until the UI/forms land. A future contributor could add a JSON column to `medical_attachments` (violating the "normalized tables" PRD) and no test would catch it. The next change touching these tables MUST add the 8 missing tests in its first red→green cycle.
- **R2 — The `appointments` unique index was buggy in the first attempt (`9e272a0`)** and was fixed in `612101c` (changed the MariaDB virtual-column expression from `id`-based to constant-1-based). The red test `689f926` caught the bug — proof TDD worked. **The test (`ConcurrentDoubleBookTest::rejects a raw second insert with a QueryException`) is the safety net, not the migration code.** If a future MariaDB version changes VIRTUAL-column behaviour with NULL markers in unique indexes, this test will catch the regression. Do NOT remove it.
- **R3 — The doctor panel has no `discoverResources()` call** (intentional per the user prompt: "NO resources for v1"). The next change adding a doctor-facing resource (e.g. `MyAppointmentsResource`) MUST add `->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')` to `DoctorPanelProvider`. The current setup silently does NOT mount new resources on `/doctor`. A test like `test('a doctor can see their appointment list on /doctor')` would be the right regression guard.

## Files moved (sync + archive)

**Delta specs → canonical specs** (additive copy, no merge logic needed):

- `openspec/changes/agenda-core/specs/agenda/spec.md` → `openspec/specs/agenda/spec.md` (5,164 B + 148 B header = 5,312 B)
- `openspec/changes/agenda-core/specs/doctor-schedule/spec.md` → `openspec/specs/doctor-schedule/spec.md` (3,036 B + 157 B header = 3,193 B)
- `openspec/changes/agenda-core/specs/users-roles/spec.md` → `openspec/specs/users-roles/spec.md` (3,375 B + 153 B header = 3,528 B)
- `openspec/changes/agenda-core/specs/clinical-records/spec.md` → `openspec/specs/clinical-records/spec.md` (2,556 B + 158 B header = 2,714 B)
- `openspec/changes/agenda-core/specs/prescriptions/spec.md` → `openspec/specs/prescriptions/spec.md` (1,919 B + 155 B header = 2,074 B)
- `openspec/changes/agenda-core/specs/admin-audit/spec.md` → `openspec/specs/admin-audit/spec.md` (1,755 B + 153 B header = 1,908 B)

**Change folder → archive** (full git-rename, 9 files):

- `openspec/changes/agenda-core/` → `openspec/changes/archive/agenda-core/`
- Contains: `proposal.md`, `design.md`, `tasks.md`, `verify-report.md`, and `specs/{agenda,doctor-schedule,users-roles,clinical-records,prescriptions,admin-audit}/spec.md`

The `openspec/changes/archive/` parent directory did not exist and was created.

## AGENTS.md updates

None. `openspec/AGENTS.md` does not contain a "Changes in flight" or "Recent changes" / "Changelog" section. The layout tree already documents the `archive/` subdirectory under `changes/`, so the project's workflow contract remains accurate.

## Archive integrity verification

- `ls openspec/changes/` shows `archive/` only (no `agenda-core` at the active path).
- `ls openspec/changes/archive/agenda-core/` shows the full 9-file change set.
- `ls openspec/specs/` shows 6 capability folders, each with `spec.md`.
- `git status` after the move shows 9 `R` (rename) entries and 1 untracked entry (`openspec/changes/archive/agenda-core/verify-report.md`, which was untracked before the move) and 1 untracked directory `openspec/specs/` (new, all files untracked). The 9 renamed files include the 6 delta specs + `proposal.md` + `design.md` + `tasks.md`.

## Commit

The archive is staged as a single commit:

```
chore(archive): archive agenda-core change to openspec/changes/archive

agenda-core is feature-complete and verified (pass-with-warnings,
6/6 smoke tests green, 9 strict TDD red→green pairs intact).
Delta specs synced to openspec/specs/{agenda,doctor-schedule,
users-roles,clinical-records,prescriptions,admin-audit}/spec.md
(additive copy — first-time archive, no canonical content existed).
The change folder moved to openspec/changes/archive/agenda-core/
as the historical record. Verify report is at
openspec/changes/archive/agenda-core/verify-report.md.
The 6 warnings (W1-W6) and 3 risks (R1-R3) in the verify report
are documented deferrals for the next change (agenda-http,
agenda-patient-web, or a future clinical-records/prescriptions
change) to address when they implement the wire-up of those tables.
```

No Co-Authored-By trailer. No AI attribution.

## skill_resolution

paths-injected (sdd-archive via orchestrator; `_shared` reference loaded for protocol context; engram-protocol from system prompt)

## Next recommended

The orchestrator should:

1. **`/sdd-new` to start the next change** — candidates: `agenda-http` (wire Sanctum-protected REST endpoints for the booking core), `agenda-patient-web` (Blade + Tailwind patient portal consuming the API), or a focused `agenda-core-cleanup` change that closes W1–W4 before they compound.
2. **Surface W1–W6 to the user** as documented context — the user may want to schedule a cleanup change before opening a new feature slice, or may accept the gaps as deliberate deferrals.
3. **Capture the audit decisions in Engram** — the `actor-aware Transition` shape (spatie `Transition` subclass with policy re-check) and the Filament v5 `CreateRecord::handleRecordCreation` audit-log injection pattern are reusable patterns for the next changes.
