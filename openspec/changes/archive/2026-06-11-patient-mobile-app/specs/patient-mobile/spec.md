---
change: patient-mobile-app
status: spec
created: 2026-06-11
---

# Delta for Patient Mobile

## ADDED Requirements

### Requirement: API Path Migration

All mobile HTTP calls MUST use `/api/v1/` prefixed paths. The `apiClient` refresh interceptor and every `authService` method MUST call v1 endpoints; no unversioned `/api/` paths are permitted.

#### Scenario: Auth calls use v1 paths
- **Given** any authenticated API call is made
- **When** the request fires (login, register, refresh, or data fetch)
- **Then** the path is `/api/v1/…` and a `401` retry with refresh succeeds if the token expired

---

### Requirement: Doctor Browsing

A patient MUST be able to list doctors filtered by specialty, view a doctor's profile, and fetch available time slots for a chosen date. Empty and error states MUST be rendered without crashing.

#### Scenario: List doctors with specialty filter
- **Given** the patient is on DoctorListScreen
- **When** the screen loads or a specialty chip is tapped
- **Then** `GET /api/v1/doctors` (or with `?specialty_id=`) returns a list; each card shows name, specialty, and bio excerpt

#### Scenario: View doctor detail and fetch availability
- **Given** the patient tapped a doctor
- **When** `GET /api/v1/doctors/{id}` renders and the patient taps "Ver disponibilidad"
- **Then** DoctorAvailabilityScreen fetches `GET /api/v1/doctors/{id}/availability?date=YYYY-MM-DD&tz=…` and renders slots as tappable time chips grouped by date
- **And** an empty state shows when no slots exist

#### Scenario: Availability error state
- **Given** the availability request fails (network or 5xx)
- **When** the response is received
- **Then** an error banner with retry button is shown; the app does not crash

---

### Requirement: Appointment Booking and Cancellation

A patient MUST be able to book a published slot and cancel their own upcoming `pending` or `confirmed` appointment if it starts more than 24 hours from now.

#### Scenario: Book a slot
- **Given** the patient selected a time slot on BookAppointmentScreen
- **When** `POST /api/v1/appointments` fires with `{doctor_id, start_time}`
- **Then** a `pending` appointment is created; a success screen confirms and the list refreshes

#### Scenario: Booking conflict returns 409
- **Given** the slot was taken between the patient's view and submission
- **When** the booking request returns `409`
- **Then** "Este horario ya no está disponible" is shown and availability refreshes

#### Scenario: Patient cancels within 24-hour window
- **Given** the patient has an upcoming appointment starting in more than 24 hours
- **When** the patient taps "Cancelar" and confirms
- **Then** `DELETE /api/v1/appointments/{id}` transitions state to `cancelled`; the appointment disappears from the upcoming list

#### Scenario: Cancel button hidden inside 24-hour window
- **Given** an appointment starts in less than 24 hours
- **When** the appointment card renders
- **Then** "Cancelar" is hidden or disabled; a tooltip explains the 24-hour restriction

---

### Requirement: Medical History Read-Only

A patient MUST be able to view their medical history timeline and note details. The patient MUST NOT be able to create, modify, or delete any clinical data.

#### Scenario: View history timeline
- **Given** the patient is on MedicalHistoryScreen
- **When** `GET /api/v1/medical-history` returns entries
- **Then** a timeline is rendered; tapping an entry navigates to MedicalNoteDetailScreen

#### Scenario: View note detail
- **Given** the patient tapped a note entry
- **When** MedicalNoteDetailScreen loads from `GET /api/v1/medical-history/notes/{id}`
- **Then** symptoms, diagnosis, treatment, doctor name, and date are shown in a read-only layout; no edit controls exist

#### Scenario: Empty history state
- **Given** the patient has no medical history
- **When** the history request returns an empty list
- **Then** "Aún no tienes historial clínico" is displayed

---

### Requirement: Prescription List and PDF Access

A patient MUST be able to list prescriptions and open the prescription PDF in the device browser via `Linking.openURL()` with the auth token sent automatically by the axios interceptor.

#### Scenario: List prescriptions
- **Given** the patient is on PrescriptionsScreen
- **When** `GET /api/v1/prescriptions` loads
- **Then** each row shows unique code, doctor, date, and status badge; empty state shows "Aún no tienes recetas"

#### Scenario: Open prescription PDF
- **Given** the patient tapped a prescription and is on PrescriptionDetailScreen
- **When** "Ver PDF" is tapped
- **Then** `Linking.openURL()` opens `GET /api/v1/prescriptions/{id}/pdf` in the device browser; the auth token is sent via the interceptor

---

### Requirement: Profile and Session Management

A patient MUST be able to view their own profile and end their session. All profile API calls MUST use `/api/v1/` paths. Unauthenticated access to any tab screen MUST redirect to the LoginScreen.

#### Scenario: View own profile
- **Given** the patient is on ProfileScreen
- **When** the screen loads
- **Then** name, email, and registration date are displayed

#### Scenario: Patient logs out
- **Given** the patient taps "Cerrar sesión"
- **When** logout is confirmed
- **Then** the token is cleared from storage; the app navigates to LoginScreen via AuthNavigator