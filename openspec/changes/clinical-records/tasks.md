# Tasks: Clinical Records

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 500-550 (PR 1: ~350, PR 2: ~200) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 (append-only + notes CRUD) → PR 2 (attachments) |
| Delivery strategy | ask-always |
| Chain strategy | stacked-to-main |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Append-only guard + Notes CRUD | PR 1 | Model guard, actions, controller, policy, routes, tests. Targets main. |
| 2 | Medical Attachments | PR 2 | Storage config, upload/list/delete endpoints, tests. Targets main. |

## Phase 1: Foundation (PR 1)

- [x] 1.1 **RED**: Write unit test asserting `MedicalNote::update()` throws, `delete()` throws, `create()` succeeds
- [x] 1.2 **GREEN**: Add `boot()` with `saving`/`deleting` event listeners to MedicalNote; throw `LogicException`
- [x] 1.3 Create `MedicalHistoryPolicy` with `view()` and `createNote()` — checks doctor has appointment for patient
- [x] 1.4 Create `CreateMedicalNoteRequest` + `AmendMedicalNoteRequest` with field validation rules

## Phase 2: Core Implementation (PR 1)

- [x] 2.1 Create `app/Actions/Medical/CreateMedicalNoteAction` — inserts note with symptoms, physical_exam, diagnosis, treatment_notes
- [x] 2.2 Create `app/Actions/Medical/AmendMedicalNoteAction` — new note with `corrects_note_id`, throws if original already corrected
- [x] 2.3 Create `app/Http/Resources/Api/MedicalNoteResource` — id, history_id, doctor, fields, corrects_note_id, created_at
- [x] 2.4 Create `app/Http/Controllers/Api/MedicalNoteController` — store, index, show, amend

## Phase 3: Integration & Wiring (PR 1)

- [x] 3.1 Modify `MedicalHistoryController@show` — replace inline authz with `$this->authorize('view', $history)`
- [x] 3.2 Fix N+1 in `MedicalHistoryResource` — change `notes()->count()` to preloaded `notes_count`; add `->loadCount('notes')` in controller
- [x] 3.3 Register policy via `Gate::policy()` in `AppServiceProvider`
- [x] 3.4 Add note routes to `routes/api.php` under Sanctum group (nested under histories)

## Phase 4: Testing (PR 1)

- [x] 4.1 Integration: POST create note — 201 with appointment, 403 without, 422 validation
- [x] 4.2 Integration: POST amend note — 201 with `corrects_note_id`, original unchanged
- [x] 4.3 Integration: GET list notes (200 paginated), GET show note (200 authorized, 403 unauthorized)
- [x] 4.4 Assert N+1 fixed: only 1 query for `notes_count` when loading 20 histories

## Phase 5: Attachments (PR 2)

- [x] 5.1 Add `clinical_attachments` disk to `config/filesystems.php` (env-driven, falls back to local)
- [x] 5.2 Create `UploadMedicalAttachmentRequest` — mimes validation, max 10MB
- [x] 5.3 Create `MedicalAttachmentResource` — id, note_id, filename, mime, size, url, created_at
- [x] 5.4 Create `MedicalAttachmentController` — upload (POST), list-by-note (GET), delete (DELETE)
- [x] 5.5 Add attachment routes to `routes/api.php` (nested under notes)
- [x] 5.6 Integration: upload (201, max size, mime), delete (200 uploader, 403 other user)
