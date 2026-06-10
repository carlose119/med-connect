# Tasks: Clinical Records — Filament Admin/Doctor UI

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~1,100–1,400 (new files only, no existing file modifications) |
| 400-line budget risk | Medium |
| Chained PRs recommended | No |
| Suggested split | Single PR — all 4 work units are cohesive and interdependent |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Medium

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | MedicalHistory Filament Resource (base) | PR 1 | Foundation; other units depend on it |
| 2 | MedicalNotes RelationManager | PR 1 | Depends on Unit 1 (resource class) |
| 3 | Attachments embedded in note view | PR 1 | Depends on Unit 2 (note ViewAction) |
| 4 | Feature tests | PR 1 | Depends on Units 1–3 |

> The medium risk comes from new file volume (~14 files). A single PR is workable because the codebase is new territory — no existing files are modified, only created. Risk is in scope breadth, not in code conflict.

## Phase 1: MedicalHistory Filament Resource

- [ ] 1.1 Create `app/Filament/Resources/ClinicalRecords/MedicalHistoryResource.php`
  - File: `app/Filament/Resources/ClinicalRecords/MedicalHistoryResource.php`
  - Binds `MedicalHistory::class`, navigation group `Clinical`, icon, sort order
  - `canCreate()` → `false` (history is auto-created on first appointment)
  - `canEdit()` → `false`, `canDelete()` → `false`
  - `getEloquentQuery()` scopes doctors to histories where they have an appointment: `whereHas('patient.appointments', fn $q => $q->where('doctor_id', auth()->user()->doctor->id))`
  - `getPages()` returns `index` (ListMedicalHistories) and `edit` (EditMedicalHistory)
  - `getRelations()` returns `[MedicalNotesRelationManager::class]`
  - Dependency: MedicalHistoryPolicy (already exists)
  - Effort: medium

- [ ] 1.2 Create `app/Filament/Resources/ClinicalRecords/Schemas/MedicalHistoryForm.php`
  - File: `app/Filament/Resources/ClinicalRecords/Schemas/MedicalHistoryForm.php`
  - Read-only fields: patient name, primary doctor name, opened_at
  - Uses `TextColumn::make()` wrapped in a disabled `Fieldset` or `Section`
  - No Hidden fields, no inputs
  - Dependency: Unit 1.1 (referenced by `MedicalHistoryResource::form()`)
  - Effort: small

- [ ] 1.3 Create `app/Filament/Resources/ClinicalRecords/Tables/MedicalHistoriesTable.php`
  - File: `app/Filament/Resources/ClinicalRecords/Tables/MedicalHistoriesTable.php`
  - Columns: patient name (searchable, sortable), opened_at (date, sortable), notes count (badge), primary doctor name
  - No `recordActions` (read-only resource)
  - No `toolbarActions`
  - Dependency: Unit 1.1 (referenced by `MedicalHistoryResource::table()`)
  - Effort: small

- [ ] 1.4 Create `app/Filament/Resources/ClinicalRecords/Pages/ListMedicalHistories.php`
  - File: `app/Filament/Resources/ClinicalRecords/Pages/ListMedicalHistories.php`
  - Extends `ListRecords`
  - No extra header actions for now
  - Dependency: Unit 1.1 (referenced by `getPages()`)
  - Effort: small

- [ ] 1.5 Create `app/Filament/Resources/ClinicalRecords/Pages/EditMedicalHistory.php`
  - File: `app/Filament/Resources/ClinicalRecords/Pages/EditMedicalHistory.php`
  - Extends `EditRecord`
  - Renders read-only header form + RelationManagers tabs below
  - Dependency: Unit 1.1 (referenced by `getPages()`), Unit 1.2 (form schema), Unit 2 (RelationManager)
  - Effort: small

## Phase 2: MedicalNotes RelationManager

- [ ] 2.1 Create `app/Filament/Resources/ClinicalRecords/RelationManagers/MedicalNotesRelationManager.php`
  - File: `app/Filament/Resources/ClinicalRecords/RelationManagers/MedicalNotesRelationManager.php`
  - Extends `RelationManager`
  - `form()`: CreateAction with fields: `symptoms` (TextArea), `physical_exam` (TextArea), `diagnosis` (TextArea), `treatment_notes` (TextArea), `appointment_id` (Select filtered to patient's completed appointments), `doctor_id` (Hidden, default `auth()->user()->doctor->id`)
  - `table()`: columns for doctor name, appointment date, symptoms (truncated 50), diagnosis (truncated 50), amendment badge (icon if `corrects_note_id` is not null), created_at
  - `canEdit()` → `false`, `canDelete()` → `false` at resource level
  - No `EditAction` or `DeleteAction` on table
  - Dependency: Unit 1.1 (getRelations), Unit 1.5 (EditMedicalHistory page)
  - Effort: large

- [ ] 2.2 Add ViewAction to MedicalNotes table (inline in 2.1)
  - ViewAction shows Infolist with: symptoms, physical_exam, diagnosis, treatment_notes, doctor name, appointment date, created_at (all read-only)
  - Inline within 2.1 — not a separate task
  - Dependency: Unit 2.1
  - Effort: included in 2.1

## Phase 3: Attachments Inline in Note View

- [ ] 3.1 Embed attachment table inside MedicalNote ViewAction modal
  - File: modify `app/Filament/Resources/ClinicalRecords/RelationManagers/MedicalNotesRelationManager.php` (from 2.1)
  - When ViewAction renders the Infolist, append an attachments sub-section below the note fields
  - Attachment table columns: file_name, mime_type, size_bytes (formatted KB/MB), uploaded_by (user name), created_at, download link
  - Download uses `FileDownload` action or a dedicated download route using `clinical_attachments` disk
  - No upload UI (API-only per spec)
  - Dependency: Unit 2.1, Unit 2.2
  - Effort: medium

- [ ] 3.2 Ensure `clinical_attachments` disk is configured in `config/filesystems.php`
  - File: `config/filesystems.php`
  - Check if `clinical_attachments` disk already exists; if not, add it
  - If a new disk is needed, create migration/seed for storage path — verify this is not already done from prior changes
  - Dependency: prior clinical-records change (check `config/filesystems.php` first)
  - Effort: small (investigation + 1 line if missing)

## Phase 4: Feature Tests

- [ ] 4.1 Create `tests/Feature/Filament/MedicalHistoryResourceTest.php`
  - File: `tests/Feature/Filament/MedicalHistoryResourceTest.php`
  - `it('renders the resource with expected table columns')` — Livewire test on ListMedicalHistories
  - `it('renders the edit page with read-only form')` — Livewire test on EditMedicalHistory
  - `it('does not expose a create page')` — assert `/clinical/medical-histories/create` returns 404
  - Dependency: Units 1.1–1.5
  - Effort: medium

- [ ] 4.2 Create `tests/Feature/Filament/MedicalNotesRelationManagerTest.php`
  - File: `tests/Feature/Filament/MedicalNotesRelationManagerTest.php`
  - `it('renders notes table with expected columns')` — Livewire test
  - `it('doctor can create a note via create form')` — Livewire form fill + submit, assert DB row created with auto-assigned doctor_id
  - `it('note create form auto-assigns doctor_id from auth')` — assert created note's doctor_id matches `auth()->user()->doctor->id`
  - `it('no edit action present on notes table')` — assert EditAction not registered
  - `it('no delete action present on notes table')` — assert DeleteAction not registered
  - `it('append-only: model rejects update attempt')` — unit test on MedicalNote model
  - `it('append-only: model rejects delete attempt')` — unit test on MedicalNote model
  - Dependency: Units 2.1, 2.2
  - Effort: medium

- [ ] 4.3 Create `tests/Feature/Filament/MedicalHistoryScopingTest.php`
  - File: `tests/Feature/Filament/MedicalHistoryScopingTest.php`
  - `it('doctor sees only histories of patients they have appointments with')` — two doctors, two patients; doctor A only sees patient A's history
  - `it('admin sees all histories')` — Livewire actingAs(admin), assert all histories visible
  - `it('doctor cannot access another doctors patient history via direct URL')` — policy check
  - Dependency: Unit 1.1 (getEloquentQuery scoping)
  - Effort: medium

- [ ] 4.4 Create `tests/Feature/Filament/MedicalAttachmentVisibilityTest.php`
  - File: `tests/Feature/Filament/MedicalAttachmentVisibilityTest.php`
  - `it('note view modal displays attachment rows')` — Livewire, assert attachment table visible in note view
  - `it('attachment download link returns file from clinical_attachments disk')` — assert download action/URL returns 200 with correct content-type
  - Dependency: Unit 3.1
  - Effort: small

## Implementation Order

1. **Phase 1 first** — MedicalHistoryResource, Form, Table, Pages are the skeleton everything else hangs off.
2. **Phase 2 second** — MedicalNotesRelationManager needs the resource's `getRelations()` to be wired up.
3. **Phase 3 third** — Attachments are embedded inside the note ViewAction modal from Phase 2.
4. **Phase 4 last** — Tests run against finished code; write them after implementation.

## Notes

- No model changes, no migrations — this change is pure Filament view layer.
- The `clinical_attachments` disk must exist from a prior change; verify `config/filesystems.php` before Phase 3.
- Append-only enforcement at the model level (MedicalNote boot events) is already done — do not re-implement.
- Follow the existing Filament v5 pattern from `DoctorScheduleResource` for namespaces, schema composition, and scoping.