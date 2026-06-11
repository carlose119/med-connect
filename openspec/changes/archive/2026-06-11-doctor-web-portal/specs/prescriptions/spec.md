# Delta: prescriptions (Walk-in Prescription Issuance)

## ADDED Requirements

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