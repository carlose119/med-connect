<!-- Source: openspec/changes/archive/agenda-core/specs/clinical-records/spec.md -- synced 2026-06-01 (first-time archive, no canonical content existed) -->
# Clinical Records

## Purpose
Clinical records cover a patient's medical history, the notes a doctor appends to it, and the files attached to those notes. Notes are append-only: amendments are modeled as new notes pointing back to the note they correct.

## ADDED Requirements

### Requirement: Medical History Lifecycle
The system MUST associate every patient with exactly one `medical_history` row. The first time a patient receives an appointment, a `medical_history` row MUST be created automatically for that patient.

#### Scenario: First appointment triggers history creation
- **Given** a patient with no existing `medical_history` and a published slot in the future
- **When** the patient books the slot
- **Then** a `medical_history` row exists for that patient, created inside the booking transaction

#### Scenario: Subsequent appointments reuse the same history
- **Given** a patient that already has a `medical_history` row
- **When** the patient books another appointment
- **Then** no new `medical_history` row is created and the existing row remains the only one

### Requirement: Append-Only Medical Notes
The system MUST treat `medical_notes` as append-only. Updating or deleting an existing note MUST NOT be possible through any public API. An amendment MUST be a new note whose `corrects_note_id` references the original.

#### Scenario: Updating an existing note is impossible
- **Given** a persisted `medical_note` with id `N`
- **When** any code path attempts to update its body or any field
- **Then** the update is rejected and the persisted note is unchanged

#### Scenario: Deleting an existing note is impossible
- **Given** a persisted `medical_note` with id `N`
- **When** any code path attempts to delete it
- **Then** the deletion is rejected and the persisted note is unchanged

#### Scenario: Amendment creates a new note linked to the original
- **Given** a persisted `medical_note` `N` authored by a doctor
- **When** the doctor amends the clinical record with corrected content
- **Then** a new `medical_note` `N'` is persisted with `N'.corrects_note_id = N.id` and `N` remains untouched

### Requirement: Medical Attachments
The system MUST persist attachments as a normalized table of rows, one per attachment. Attachments SHALL NOT be stored as a JSON column on the note.

#### Scenario: Each attachment is a separate row
- **Given** a note that references two uploaded files
- **When** the attachments are saved
- **Then** two rows exist in `medical_attachments` and each links back to the same `medical_note` id
