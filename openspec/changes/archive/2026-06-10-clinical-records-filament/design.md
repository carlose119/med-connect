# Design: Clinical Records — Filament Admin/Doctor UI

## Technical Approach

Single Filament Resource (`MedicalHistory`) for the aggregate root, with two RelationManagers for child traversal (Notes → Attachments). Append-only contract enforced at the UI layer (no Edit/Delete actions) and backed by model events. Scoping is role-aware: doctors see only histories for patients they have appointments with; admins see all.

## Architecture Decisions

### Namespace & Structure

| Option | Tradeoff | Decision |
|--------|----------|----------|
| `DoctorClinicalRecords` | Scoped to doctor panel only; admin would need separate resource | ❌ |
| `ClinicalRecords` | Neutral; single resource used by both admin + doctor panels | ✅ |

**Rationale**: The history is the same entity regardless of panel. Keeping one resource avoids duplication. Role-based scoping in `getEloquentQuery()` follows the existing `DoctorScheduleResource` pattern.

### Append-Only in UI

| Option | Tradeoff | Decision |
|--------|----------|----------|
| Hide edit/delete buttons on table | Simple; still accessible via URL if user guesses route | ❌ |
| `canEdit()/canDelete()` return `false` + remove actions from table | Defense in depth; route guard + no surface-level buttons | ✅ |
| Policy gate at model level | Already exists in `saving()`/`deleting()` events on `MedicalNote` | Already done |

**Rationale**: The model already enforces append-only at the Eloquent level. The Filament layer adds UX clarity — no buttons to confuse doctors, and `canEdit() = false` prevents direct URL access.

### Note Create Action

| Option | Tradeoff | Decision |
|--------|----------|----------|
| Full form on Edit page | Easy to implement; available immediately | ✅ |
| Wizard/multi-step | Over-engineered for v1 | ❌ |

**Rationale**: Single form with fields: `symptoms`, `physical_exam`, `diagnosis`, `treatment_notes`, `appointment_id` (select filtered to patient's completed appointments), `doctor_id` (auto-assigned from auth). Matches the API controller schema.

### Attachments View

| Option | Tradeoff | Decision |
|--------|----------|----------|
| Separate RelationManager tab on Edit page | Shows all attachments for the history, not per-note | ❌ |
| Embedded in note ViewAction modal | Per-note scoping; follows spec's "under Note view" | ✅ |
| Dedicated page for note attachments | Extra page/route; overkill for read-only list | ❌ |

**Rationale**: Note ViewAction modal shows all note fields (read-only Infolist) plus an attachment table with `file_name`, `mime_type`, `size_bytes`, `uploaded_by`, and a download link. No upload in Filament — spec explicitly says API-only for uploads.

### Scoping Strategy

| Role | Scope |
|------|-------|
| Admin | `MedicalHistory::query()` — no filter |
| Doctor | `whereHas('patient.appointments', fn $q => $q->where('doctor_id', auth()->user()->doctor->id))` |

**Rationale**: The existing `MedicalHistoryPolicy::view()` already implements this logic. We reuse the same condition in `getEloquentQuery()` for list-scoping efficiency, and use the policy for record-level guard.

## Component Tree

```
MedicalHistoryResource (app/Filament/Resources/ClinicalRecords/)
├── Pages/
│   ├── ListMedicalHistories      # Table with patient, opened_at, note count, primary doctor
│   └── EditMedicalHistory        # Patient info (read-only) + RelationManagers
│       ├── MedicalNotesRM        # Table: doctor, appointment date, symptoms, diagnosis, amendments
│       │   └── ViewAction        # Modal: Infolist + attachments table (read-only + download)
│       │   └── CreateAction      # Form: symptoms, physical_exam, diagnosis, treatment_notes, appointment_id
│       └── (no Attachments RM — embedded in note ViewAction)
├── Schemas/
│   └── MedicalHistoryForm        # Read-only fields: patient_id, primary_doctor_id, opened_at
├── Tables/
│   └── MedicalHistoriesTable     # Columns: patient name, opened_at, note count, primary doctor
└── MedicalHistoryResource.php    # form(), table(), getPages(), getEloquentQuery(), canCreate()=false
```

No Create page — history is auto-created on first appointment booking.

## Data Flow

```
Doctor Panel / Admin Panel
        │
        ▼
MedicalHistoryResource
  getEloquentQuery() — scoped by role
        │
        ▼
ListMedicalHistories — table, searchable, filterable
        │
        ▼ (click record)
EditMedicalHistory — read-only header + RelationManager tabs
        │
        ├── MedicalNotesRelationManager
        │       ├── Table: list notes (doctor, date, symptoms, diagnosis, amendment flag)
        │       ├── ViewAction → modal: full note + attachments table
        │       │       └── DownloadAction → storage link (clinical_attachments disk)
        │       └── CreateAction → form → $note->save() → table refreshes
        │
        └── (Attachments shown inside note View modal, not as separate RM)
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `app/Filament/Resources/ClinicalRecords/MedicalHistoryResource.php` | Create | Resource class: model binding, nav, form/table wiring, pages, scoping |
| `app/Filament/Resources/ClinicalRecords/Schemas/MedicalHistoryForm.php` | Create | Read-only form schema for history fields |
| `app/Filament/Resources/ClinicalRecords/Tables/MedicalHistoriesTable.php` | Create | List table columns, filters, default sort |
| `app/Filament/Resources/ClinicalRecords/RelationManagers/MedicalNotesRelationManager.php` | Create | Notes table with View + Create, no Edit/Delete |
| `app/Filament/Resources/ClinicalRecords/Pages/ListMedicalHistories.php` | Create | List page with header actions (none for now) |
| `app/Filament/Resources/ClinicalRecords/Pages/EditMedicalHistory.php` | Create | Edit page (read-only form + RelationManagers) |

## Interfaces / Contracts

### Scoping Query (existing pattern, reused)

```php
// MedicalHistoryResource
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->when(auth()->user()->isDoctor(), fn (Builder $q) => $q->whereHas(
            'patient.appointments',
            fn (Builder $q) => $q->where('doctor_id', auth()->user()->doctor->id),
        ));
}
```

### Note Create — auto-assignments

```php
// MedicalNotesRelationManager
// appointment_id: Select filtered to patient's appointments (completed ones)
// doctor_id: Hidden with default(fn => auth()->user()?->doctor?->id)
// corrects_note_id: NOT exposed in create form (only via API/amend flow)
```

### Authorization (reuse existing policy)

| Method | Guard |
|--------|-------|
| `canViewAny()` | Admin → true; Doctor → true |
| `canCreate()` | `false` (history is never manually created) |
| `canEdit()` | `false` (history fields are read-only) |
| `canDelete()` | `false` |

Note: For the MedicalNote RM, edit/delete are removed at the action level (no `EditAction`, no `DeleteAction`). The policy is secondary defense — the model events are the ultimate gate.

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | MedicalHistoryPolicy::view + createNote | Pest with Doctor/Admin/Patient actors |
| Unit | MedicalNote append-only boot events | Pest asserting exception on update/delete |
| Integration | Resource renders in admin panel | Livewire: `Livewire::test(ListMedicalHistories)` — assert table visible |
| Integration | Doctor scoping | Livewire: assert doctor sees only histories with their appointments |
| Integration | Admin sees all | Livewire: assert admin sees all records |
| Integration | Note creation via UI | Livewire: fill form, submit, assert note appears in table |
| Integration | Note creation auto-assigns doctor_id | Assert created note's doctor_id matches auth |
| Integration | No Edit/Delete on notes | Assert `EditAction`/`DeleteAction` not present on notes table |
| Integration | Attachment visibility in view modal | Assert attachment rows visible when viewing note |
| Integration | Download action on attachment | Assert download link returns file from `clinical_attachments` disk |
| Integration | No Create page for history | Assert 404 or route not registered |

## Migration / Rollout

No migration required. Models and migrations already exist from prior changes. This adds only Filament view-layer files. Feature-flag: not needed — the resource is only visible to logged-in admin/doctor users.

## Open Questions

- [ ] Should the primary_doctor_id on MedicalHistory be editable in the admin panel? Current design says no (read-only), but admins may need to reassign.
- [ ] Attachment download route: use Filament's built-in `FileDownload` or a custom route? Custom route gives more control over auth + logging.
- [ ] Should the notes list show a "View History" link that shows the amendment chain (note → corrections)? Not in v1 spec but logical extension.
