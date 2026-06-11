<!-- Source: openspec/changes/archive/2026-06-11-doctor-web-portal/specs/clinical-records/spec.md -->
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

## ADDED Requirements (synced from doctor-web-portal)

### Requirement: Walk-in Patient Registration via Doctor Panel
The system MUST allow a doctor to register a new patient from the Filament doctor panel. Registration creates a User (role=patient) and a Patient row in a single database transaction. The `identification_number` field is case-insensitive for duplicate detection.

#### Scenario: Doctor registers walk-in patient from panel
- **Given** a doctor authenticated in the Filament panel
- **When** the doctor navigates to "Paciente sin Cita" and submits the registration form
- **Then** a User (role=patient) and Patient row are created in one DB transaction

#### Scenario: Case-insensitive duplicate detection
- **Given** a patient with `identification_number = id-001`
- **When** a doctor attempts to register a patient with `Identification_Number = ID-001`
- **Then** the registration is rejected with a validation error

### Requirement: Medical History Creation via Doctor Panel
The system MUST create a medical history for a walk-in patient when the doctor opens the consultation page. The history is 1:1 with the patient (unique constraint). If the patient already has a history, the existing history is returned and no new row is created.

#### Scenario: History auto-created on first consultation
- **Given** a walk-in patient with no existing `medical_history`
- **When** the doctor opens the consultation page
- **Then** a `medical_history` row is created linked to the patient and the doctor

#### Scenario: Existing history is reused
- **Given** a walk-in patient with an existing `medical_history`
- **When** the doctor opens the consultation page
- **Then** no new history row is created and the existing one is returned

### Requirement: Walk-in Appointment with History Creation
The system MUST create both a walk-in appointment and a medical history in a single transaction when the doctor opens the consultation page for a patient with no history.

#### Scenario: Walk-in appointment and history created atomically
- **Given** a walk-in patient with no history and no appointment for today
- **When** the doctor opens the consultation page
- **Then** both a `medical_history` row and an `appointment` row are created in one transaction
