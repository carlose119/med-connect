# Proposal: Clinical Records

## Intent

Implement the clinical records API deferred from agenda-core. The spec exists at `openspec/specs/clinical-records/spec.md` — 3 requirements, 6 scenarios — but only Medical History Lifecycle (req 1) was built. Append-Only Notes (req 2) and Medical Attachments (req 3) need enforcement and endpoints.

## Scope

### In Scope
- Append-only enforcement on MedicalNote (reject update/delete, allow via `corrects_note_id`)
- CreateMedicalNoteAction + AmendMedicalNoteAction + MedicalNoteController
- MedicalAttachmentController (upload, list-by-note, delete) + storage config
- MedicalHistoryPolicy (authorization: doctor with appointment for that patient)
- Tests for all new endpoints (4+ scenarios)
- Fix N+1 in MedicalHistoryResource (`notes()->count()`)

### Out of Scope
- Filament doctor UI for writing notes during appointment (deferred)
- Prescription items (separate change)

## Capabilities

### New Capabilities
None. The canonical spec already exists at `openspec/specs/clinical-records/spec.md`.

### Modified Capabilities
None. All 6 scenarios are already spec'd — no requirement changes needed.

## Approach

Chained 3-PR strategy stacked to main:

- **PR 1** (300-350 LOC): Append-only via Eloquent `saving`/`deleting` events on MedicalNote. CreateMedicalNoteAction, AmendMedicalNoteAction, MedicalNoteController, routes, MedicalHistoryPolicy, tests.
- **PR 2** (~200 LOC): Storage config (local default, S3 via env). MedicalAttachmentController upload/list/delete. Tests.
- **PR 3** (optional, deferred): Filament doctor UI for note-taking during appointment.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Models/MedicalNote.php` | Modified | Append-only guard (boot events) |
| `app/Actions/Medical/CreateMedicalNoteAction.php` | New | Write note to history |
| `app/Actions/Medical/AmendMedicalNoteAction.php` | New | New note with corrects_note_id |
| `app/Http/Controllers/Api/MedicalNoteController.php` | New | POST, amend, list |
| `app/Http/Controllers/Api/MedicalAttachmentController.php` | New | Upload, list, delete |
| `app/Policies/MedicalHistoryPolicy.php` | New | Authorization |
| `app/Http/Resources/MedicalHistoryResource.php` | Modified | Fix N+1 |
| `config/filesystems.php` | Modified | Attachment disk config |
| `routes/api.php` | Modified | New routes |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Append-only not enforced today (anyone can update/delete) | High | Eloquent saving/deleting events — portable, no DB triggers |
| Authorization: which doctors write notes? | Med | Policy checks doctor has appointment for that patient |
| corrects_note_id FK semantics untested | Low | Add cascade test in PR 1 |
| Storage undefined (local vs S3) | Med | Config-driven: local default, S3 via env toggle |

## Rollback Plan

Each PR independently revertible via `git revert <merge-commit>`. Append-only guard is a model change — revert restores mutability. Attachment migration revert via `migrate:rollback`.

## Dependencies

- None. All tables exist from agenda-core migrations.

## Success Criteria

- [ ] MedicalNote::update() and ::delete() throw on any code path
- [ ] POST to create note returns 201 with persisted note
- [ ] PATCH amend creates new note linked via corrects_note_id
- [ ] Attachments upload, list by note, and delete work
- [ ] Authorization rejects doctors without patient appointment
- [ ] All new Pest 4 tests pass
