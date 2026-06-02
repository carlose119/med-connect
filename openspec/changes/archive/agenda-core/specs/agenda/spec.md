# Agenda

## Purpose
The agenda is the core booking domain. It models the appointment aggregate, the lifecycle states a consultation moves through, the slot-generation contract, and the rules that govern when a patient can book or cancel.

## ADDED Requirements

### Requirement: Appointment Aggregate
The system MUST represent an appointment as an aggregate bound to exactly one patient and one doctor, with `start_time` and `end_time` in UTC, a state, and an optional reason-for-visit note.

#### Scenario: Persistence rejects a colliding appointment
- **Given** a doctor with one non-cancelled appointment at `2030-01-15 10:00 UTC`
- **When** a second appointment is attempted for the same doctor and `start_time`
- **Then** the persistence layer rejects the write and surfaces `SlotNotAvailableException`

### Requirement: Appointment State Machine
The system MUST model lifecycle as states `pending`, `confirmed`, `completed`, `cancelled`, `no_show`. Terminals are `completed`, `cancelled`, `no_show`; transitions out of a terminal state MUST be rejected. Only the assigned doctor may transition into `confirmed`, `completed`, or `no_show`.

#### Scenario: Doctor confirms a pending appointment
- **Given** an appointment in state `pending`
- **When** the assigned doctor transitions it to `confirmed`
- **Then** the state becomes `confirmed` and the transition is recorded

#### Scenario: Transition out of a terminal state is rejected
- **Given** an appointment in state `cancelled`
- **When** any actor attempts to transition it to `pending` or `confirmed`
- **Then** the transition is rejected and the state remains `cancelled`

#### Scenario: Patient cannot self-confirm
- **Given** an appointment in state `pending` owned by patient `P`
- **When** `P` attempts to transition it to `confirmed`
- **Then** the transition is rejected and the state remains `pending`

### Requirement: Slot Generation
The system MUST compute available slots for a doctor on a given date from the recurring schedule and any same-day overrides, minus slots consumed by non-cancelled appointments. Slots are computed on the fly; they MUST NOT be persisted.

#### Scenario: Slots combine a recurring rule with a block override
- **Given** a doctor with a recurring rule publishing `09:00` and `09:30` on the target day and a `block` override for `09:15` to `09:45`
- **When** the availability service is queried for that doctor and date
- **Then** the result contains only `09:00`

#### Scenario: Slots include an extra_availability override
- **Given** a doctor with a recurring rule publishing only `10:00` on the target day and an `extra_availability` override adding `11:00`
- **When** the availability service is queried for that doctor and date
- **Then** the result contains `10:00` and `11:00`

### Requirement: Booking
The system MUST accept `(doctor_id, start_time, patient_id)` and produce a `pending` appointment after validating: the slot is published, the slot is free, the start is at least 2 hours in the future, and the patient has no overlapping non-cancelled appointment. Validation and write MUST run in a single transaction.

#### Scenario: Happy path booking
- **Given** a published slot at `now + 3h` for doctor `D` that is not booked and patient `P` with no overlapping non-cancelled appointments
- **When** `P` books the slot
- **Then** a new appointment exists with state `pending` and belongs to `D` and `P`

#### Scenario: Double-booking is rejected with 409
- **Given** a non-cancelled appointment for doctor `D` at the requested `start_time`
- **When** any patient attempts to book the same `(doctor_id, start_time)`
- **Then** the booking is rejected, `SlotNotAvailableException` is raised, and the API responds `409`

#### Scenario: Booking inside the anticipation window is rejected with 422
- **Given** a published slot at `now + 30m`
- **When** any patient attempts to book that slot
- **Then** the booking is rejected, `AnticipationWindowViolationException` is raised, and the API responds `422`

#### Scenario: Patient with an overlapping appointment is rejected with 422
- **Given** patient `P` with a non-cancelled appointment at `now + 3h` for doctor `D1` and a published slot at `now + 3h` for doctor `D2`
- **When** `P` attempts to book the `D2` slot at the overlapping time
- **Then** the booking is rejected, `PatientOverlapException` is raised, and the API responds `422`

### Requirement: Patient Cancellation Window
The system MUST allow a patient to cancel their own appointment up to 24 hours before `start_time`. A cancellation at or after `start_time - 24h` MUST be rejected.

#### Scenario: Patient cancels inside the 24h window
- **Given** a `pending` appointment for patient `P` starting at `now + 48h`
- **When** `P` cancels the appointment
- **Then** the state becomes `cancelled` and the cancellation is recorded

#### Scenario: Patient cancels outside the 24h window
- **Given** a `pending` appointment for patient `P` starting at `now + 12h`
- **When** `P` attempts to cancel the appointment
- **Then** the cancellation is rejected, `CancellationWindowViolationException` is raised, and the API responds `422` or `403`
