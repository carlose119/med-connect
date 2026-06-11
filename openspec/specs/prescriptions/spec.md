<!-- Source: openspec/changes/archive/2026-06-11-doctor-web-portal/specs/prescriptions/spec.md -->
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

## ADDED Requirements (synced from doctor-web-portal)

### Requirement: Walk-in Prescription Issuance
The system MUST allow a doctor to issue a prescription for a walk-in appointment that was transitioned to `completed`. The prescription is bound to the walk-in appointment and the patient. Walk-in prescriptions have the same format as scheduled-appointment prescriptions (unique code, items with name/dosage/frequency/duration).

#### Scenario: Doctor issues prescription for completed walk-in appointment
- **Given** a walk-in appointment with status `completed`
- **When** the doctor submits a prescription with items (name, dosage, frequency, duration)
- **Then** a `prescription` row is created bound to the appointment and patient, with a unique code and ordered items

#### Scenario: Prescription rejected when appointment not completed
- **Given** a walk-in appointment with status `pending` or `confirmed`
- **When** the doctor attempts to issue a prescription
- **Then** the issuance is rejected and no prescription row is created

#### Scenario: Walk-in prescription items are persisted as rows
- **Given** a completed walk-in appointment
- **When** the doctor issues a prescription with 3 items
- **Then** 3 rows exist in `prescription_items` linked to the prescription, each with a `position` value
