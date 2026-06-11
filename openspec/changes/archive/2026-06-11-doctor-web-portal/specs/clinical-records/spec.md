# Delta: clinical-records (Walk-in Patient via Doctor Panel)

## ADDED Requirements

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