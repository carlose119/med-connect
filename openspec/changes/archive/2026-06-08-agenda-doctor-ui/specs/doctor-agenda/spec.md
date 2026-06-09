# Doctor Agenda

## Purpose
Doctors manage appointments through the `/doctor` Filament panel: a read-only AppointmentResource scoped to the authenticated doctor, with table actions driving lifecycle transitions via existing Transition classes. Admin panel, API, and patient flows are unaffected.

## Requirements

### Requirement: Appointment List
The system MUST display a read-only table of the doctor's own appointments in the `/doctor` panel. Each row MUST show date, time, patient name, and status. The query MUST scope to `auth()->user()->doctor->appointments()`.

#### Scenario: Doctor views their appointments
- **Given** a doctor with 5 appointments across dates
- **When** they navigate to "My Appointments"
- **Then** the table shows all 5 with date, time, patient name, and status

#### Scenario: Doctor sees only their own appointments
- **Given** two doctors `D1` and `D2`, each with appointments
- **When** `D1` opens "My Appointments"
- **Then** the table contains only `D1`'s appointments

### Requirement: Confirm Appointment
The system MUST allow a doctor to transition `pending` to `confirmed` via a table action delegating to `ConfirmAppointmentTransition`.

#### Scenario: Doctor confirms a pending appointment
- **Given** a pending appointment assigned to doctor `D`
- **When** `D` clicks "Confirm"
- **Then** the appointment state becomes `confirmed`

#### Scenario: Action hidden for non-pending states
- **Given** a `confirmed` appointment
- **When** `D` views the table
- **Then** the "Confirm" action is not available

### Requirement: Complete Appointment
The system MUST allow a doctor to transition `confirmed` to `completed` via a table action delegating to `CompleteAppointmentTransition`.

#### Scenario: Doctor completes a confirmed appointment
- **Given** a confirmed appointment assigned to doctor `D`
- **When** `D` clicks "Complete"
- **Then** the appointment state becomes `completed`

#### Scenario: Action hidden for non-confirmed states
- **Given** a `pending` appointment
- **When** `D` views the table
- **Then** the "Complete" action is not available

### Requirement: Cancel Appointment
The system MUST allow a doctor to cancel an appointment from any non-terminal state via a table action with a required reason, delegating to `CancelAppointmentTransition`.

#### Scenario: Doctor cancels with a reason
- **Given** a pending appointment assigned to doctor `D`
- **When** `D` clicks "Cancel", enters a reason, and confirms
- **Then** the state becomes `cancelled` with the reason recorded

#### Scenario: Cancel hidden for terminal states
- **Given** a `completed` appointment
- **When** `D` views the table
- **Then** the "Cancel" action is not available

### Requirement: Mark No-Show
The system MUST allow a doctor to transition `confirmed` to `no_show` via a table action delegating to `MarkNoShowAppointmentTransition`.

#### Scenario: Doctor marks a no-show
- **Given** a confirmed appointment whose patient did not arrive
- **When** `D` clicks "No Show"
- **Then** the appointment state becomes `no_show`

#### Scenario: Action hidden for non-confirmed states
- **Given** a `pending` appointment
- **When** `D` views the table
- **Then** the "No Show" action is not available

### Requirement: Date-Based Filters
The system MUST provide table filters for "Today", "Upcoming", and "Past" to scope the list by date.

#### Scenario: Doctor filters by Today
- **Given** an appointment today and another next week
- **When** the doctor selects the "Today" filter
- **Then** only today's appointment is displayed

#### Scenario: Doctor filters by Past
- **Given** an appointment yesterday and another tomorrow
- **When** the doctor selects the "Past" filter
- **Then** only yesterday's appointment is displayed

### Requirement: Backend Integrity
The system MUST NOT modify existing admin `AppointmentResource`, API routes, or patient-facing views. The doctor panel MUST be additive only.

#### Scenario: Admin panel unchanged
- **Given** an admin user with access to `/admin`
- **When** the admin browses appointments after the change
- **Then** they see the same AppointmentResource and behavior as before

#### Scenario: API routes unaffected
- **Given** a published endpoint at `/api/appointments`
- **When** a client sends the same request as before
- **Then** the response format and behavior are identical
