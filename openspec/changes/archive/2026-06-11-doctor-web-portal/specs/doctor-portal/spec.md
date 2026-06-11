# Doctor Portal

## Purpose
A walk-in patient management section inside the Filament doctor panel. Doctors register patients without prior appointments, create medical histories, write consultation notes, complete appointments, and issue prescriptions.

## Requirements

### Requirement: Doctor Authentication
The system MUST authenticate doctors via the Filament panel at `/doctor`. Unauthenticated access MUST redirect to the Filament login page.

#### Scenario: Doctor accesses walk-in pages
- **Given** a doctor logged into the Filament panel at `/doctor`
- **When** the doctor navigates to `/doctor/register-walk-in` or `/doctor/consultation/{patient_id}`
- **Then** the page renders normally

#### Scenario: Non-doctor cannot access walk-in pages
- **Given** a non-doctor user (patient or admin) attempting to access `/doctor/register-walk-in`
- **When** the request is made
- **Then** a 403 error is returned

### Requirement: Walk-in Patient Registration
The system MUST allow a doctor to register a new patient (User + Patient) in a single database transaction. If `identification_number` already exists (case-insensitive), the system MUST show an error instead of creating a duplicate.

#### Scenario: Doctor registers a new walk-in patient
- **Given** the doctor is authenticated and no patient with `identification_number = ID-001` exists
- **When** the doctor submits the registration form with `name, identification_number, email, phone, birth_date, gender`
- **Then** a User (role=patient) and a Patient row are created in one transaction and the doctor is redirected to the consultation page

#### Scenario: Identification number already exists (case-insensitive)
- **Given** a patient with `identification_number = id-001` already exists
- **When** the doctor attempts to register with `Identification_Number = ID-001`
- **Then** the registration is rejected with a validation error

### Requirement: Medical History Creation via Portal
The system MUST create a medical history for a walk-in patient on the consultation page. The history is 1:1 with the patient. If the patient already has a history, the existing history is returned.

#### Scenario: Medical history auto-created on first consultation
- **Given** a walk-in patient `P` with no existing `medical_history`
- **When** the doctor opens the consultation page for `P`
- **Then** a `medical_history` row is created for `P` linked to the doctor, with timestamps

#### Scenario: Existing history is returned
- **Given** a walk-in patient `P` with an existing `medical_history`
- **When** the doctor opens the consultation page for `P`
- **Then** the existing history is returned and no new row is created

### Requirement: Walk-in Appointment Creation
The system MUST create a walk-in appointment when the doctor opens the consultation page. The appointment starts in `pending` status with `start_time = now()`.

#### Scenario: Walk-in appointment auto-created on mount
- **Given** a registered walk-in patient `P` and an authenticated doctor `D`
- **When** the doctor opens the consultation page for `P`
- **Then** an appointment row exists with status `pending`, `start_time = now()`, linked to `D` and `P`

### Requirement: Consultation Notes (Append-Only)
The system MUST append consultation notes during a walk-in consultation. Notes MUST NOT be updated or deleted. Corrections MUST be modeled as new notes with `corrects_note_id` referencing the original.

#### Scenario: Doctor appends a consultation note
- **Given** a walk-in consultation for patient `P`
- **When** the doctor submits a note with `symptoms, physical_exam, diagnosis, treatment_notes`
- **Then** a `medical_note` row is appended to `P`'s history

#### Scenario: Note update is rejected
- **Given** a persisted `medical_note` with id `N`
- **When** any code path attempts to update its content
- **Then** the update is rejected and the note is unchanged

### Requirement: Appointment State Transitions
The system MUST allow the doctor to transition the walk-in appointment through states: `pending` → `confirmed` → `completed`.

#### Scenario: Doctor confirms the walk-in appointment
- **Given** a walk-in appointment with status `pending`
- **When** the doctor clicks "Confirmar"
- **Then** the appointment status transitions to `confirmed`

#### Scenario: Doctor completes the walk-in consultation
- **Given** a walk-in appointment with status `confirmed`
- **When** the doctor clicks "Completar Consulta"
- **Then** the appointment status transitions to `completed`

### Requirement: Prescription Issuance for Walk-in
The system MUST allow the doctor to issue a prescription for a completed walk-in appointment. The system MUST reject prescription creation if the appointment status is not `completed`.

#### Scenario: Doctor issues prescription for completed walk-in
- **Given** a walk-in appointment with status `completed`
- **When** the doctor submits a prescription with items (name, dosage, frequency, duration)
- **Then** a `prescription` row exists bound to the appointment and patient with a unique code

#### Scenario: Prescription rejected for non-completed appointment
- **Given** a walk-in appointment with status `pending` or `confirmed`
- **When** the doctor attempts to issue a prescription
- **Then** the prescription is rejected with an error