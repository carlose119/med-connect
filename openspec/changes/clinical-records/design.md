# Design: Clinical Records

## Technical Approach

Two stacked PRs implementing append-only notes and attachments atop the existing MedicalHistory/MedicalNote/MedicalAttachment models. PR 1 enforces immutability on MedicalNote via Eloquent events, exposes CRD + amend endpoints, introduces a MedicalHistoryPolicy, and fixes the N+1 in MedicalHistoryResource. PR 2 adds attachment upload/list/delete with config-driven storage.

PR 3 (Filament doctor UI) is **deferred** — no Filament code in either PR.

## Architecture Decisions

| Decision | Choice | Alternatives | Rationale |
|----------|--------|-------------|-----------|
| Append-only guard | Eloquent `saving`/`deleting` events in `MedicalNote::boot()` | DB triggers, observer class | Portable across SQLite/MariaDB/Postgres; co-located with the model; no migration needed |
| Authz | Dedicated `MedicalHistoryPolicy` | Inline `match` (current pattern), dedicated gate | The inline authz in `MedicalHistoryController@show` works but doesn't scale — multiple endpoints need the same "doctor has appointment with patient" check. Policy is the Laravel convention and is testable in isolation |
| Action placement | `app/Actions/Medical/` subdirectory | `app/Actions/` (existing pattern) | Clinical actions are a distinct domain; `Medical/` subdirectory groups them without polluting the top-level actions namespace |
| N+1 fix | `->loadCount('notes')` in controller | Eager-loading notes, custom attribute | `loadCount` is idiomatic, zero-fuss, and the resource only needs the count — not the collection |
| Storage disk | Config-driven `clinical_attachments` disk in `config/filesystems.php` | Hard-coded `local` | Environment-agnostic; S3 via `FILESYSTEM_CLINICAL_ATTACHMENTS` env var; falls back to `local` |

## Data Flow

```
POST /api/medical-histories/{history}/notes
  ─→ MedicalNoteController@store
      ─→ Authorize via MedicalHistoryPolicy@createNote(doctor, history)
          ─→ checks Appointment exists for (doctor, patient)
      ─→ CreateMedicalNoteAction (inserts note)
      ─→ MedicalNoteResource → 201

POST /api/medical-notes/{note}/amend
  ─→ MedicalNoteController@amend
      ─→ Authorize via MedicalHistoryPolicy@createNote (same check)
      ─→ AmendMedicalNoteAction (inserts new note with corrects_note_id)
      ─→ MedicalNoteResource → 201

GET /api/medical-histories/{history}/notes
  ─→ MedicalNoteController@index
      ─→ MedicalNoteResource::collection → 200

GET /api/medical-notes/{note}
  ─→ Authorize via MedicalHistoryPolicy@view(actor, note.history)
  ─→ MedicalNoteResource → 200
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `app/Models/MedicalNote.php` | Modify | Add `boot()` with `saving`/`deleting` events |
| `app/Actions/Medical/CreateMedicalNoteAction.php` | Create | Insert note with validated fields |
| `app/Actions/Medical/AmendMedicalNoteAction.php` | Create | New note linked via `corrects_note_id` |
| `app/Policies/MedicalHistoryPolicy.php` | Create | `view`, `createNote` — role-aware, checks appointments |
| `app/Http/Controllers/Api/MedicalNoteController.php` | Create | Store, amend, index, show |
| `app/Http/Requests/Api/CreateMedicalNoteRequest.php` | Create | Validate note fields |
| `app/Http/Requests/Api/AmendMedicalNoteRequest.php` | Create | Same fields, optional |
| `app/Http/Resources/Api/MedicalNoteResource.php` | Create | JSON shape for notes |
| `app/Http/Resources/Api/MedicalHistoryResource.php` | Modify | Fix N+1: `notes()->count()` → preloaded `notes_count` |
| `app/Http/Controllers/Api/MedicalHistoryController.php` | Modify | Replace inline authz with policy gate |
| `routes/api.php` | Modify | Add clinical-routes group under Sanctum |
| `app/Http/Controllers/Api/MedicalAttachmentController.php` | Create | Upload, list-by-note, delete |
| `app/Http/Requests/Api/UploadMedicalAttachmentRequest.php` | Create | File validation (mimes, max:10MB) |
| `app/Http/Resources/Api/MedicalAttachmentResource.php` | Create | JSON shape for attachments |
| `config/filesystems.php` | Modify | Add `clinical_attachments` disk |

## Interfaces / Contracts

```php
// MedicalHistoryPolicy
view(User $actor, MedicalHistory $history): bool
createNote(User $actor, MedicalHistory $history): bool  // doctor has appointment with patient

// CreateMedicalNoteAction
__invoke(MedicalHistory $history, Doctor $doctor, array $data): MedicalNote
// $data keys: symptoms, physical_exam, diagnosis, treatment_notes

// AmendMedicalNoteAction
__invoke(MedicalNote $original, array $data): MedicalNote
// $data keys: symptoms, physical_exam, diagnosis, treatment_notes
// Sets corrects_note_id = $original->id. Throws if $original has existing corrections.
```

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | MedicalNote append-only guard | Create a note, assert `update()` throws, `delete()` throws, `create()` succeeds |
| Unit | AmendMedicalNoteAction | Amend a note, assert `corrects_note_id` is set; assert amending an amendment throws |
| Integration | POST create note | 201 for doctor with appointment, 403 without appointment, 422 validation |
| Integration | POST amend note | 201 with new note, original unchanged |
| Integration | GET list notes | 200 with paginated notes |
| Integration | GET show note | 200 for authorized user, 403 for unauthorized |
| Integration | MedicalHistoryResource N+1 | Assert only 1 query for notes_count when loading 20 histories |
| Integration | Attachment upload | 201 with file, max size enforcement, mime validation |
| Integration | Attachment delete | 200 for uploader, 403 for other user |

## Migration / Rollout

No migration required — tables already exist. The append-only guard is a model change that immediately protects all code paths. Each PR independently revertible via `git revert`.

## Open Questions

None. All tables, policies, and patterns are understood from the existing codebase.
