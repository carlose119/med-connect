<!-- Source: openspec/changes/archive/agenda-core/specs/prescriptions/spec.md -- synced 2026-06-01 (first-time archive, no canonical content existed) -->
# Prescriptions

## Purpose
Prescriptions are written by a doctor after an appointment and are composed of an ordered set of items. Items are first-class rows; the system does not collapse them into a JSON column.

## ADDED Requirements

### Requirement: Prescription Ownership
The system MUST bind every prescription to exactly one appointment and to the patient of that appointment. A prescription SHALL NOT exist without both `appointment_id` and `patient_id`.

#### Scenario: Prescription belongs to appointment and patient
- **Given** a completed appointment between doctor `D` and patient `P`
- **When** the doctor issues a prescription
- **Then** the prescription is persisted and references the appointment id and `P` as the patient

### Requirement: Unique Prescription Code
The system MUST assign every prescription a `unique_code` unique across the table. A collision MUST be rejected at write time.

#### Scenario: Two prescriptions cannot share a unique_code
- **Given** an existing prescription with `unique_code = RX-001`
- **When** another prescription is created with the same `unique_code`
- **Then** the write is rejected by a unique constraint and no second row is persisted

### Requirement: Prescription Items as Rows
The system MUST persist each prescription item as a separate row in `prescription_items`. Items SHALL NOT be a JSON column. Item order MUST be preserved by a `position` column.

#### Scenario: Items are persisted as separate rows
- **Given** a prescription with two items
- **When** the prescription is saved
- **Then** two rows exist in `prescription_items` and each links back to the same `prescription_id`

#### Scenario: Item order is preserved by position
- **Given** a prescription with items `["A", "B", "C"]` in that order
- **When** the items are persisted
- **Then** the rows in `prescription_items` are read back in the same order, determined by the `position` column
