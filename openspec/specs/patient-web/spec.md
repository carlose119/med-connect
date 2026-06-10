# Patient Web

## Purpose

The patient web is a self-service browser UI for patients to register, authenticate, browse doctors, view available slots, book appointments, cancel their own bookings, and view their profile. It reuses the existing Service and Model layer through Livewire components; it MUST NOT modify any existing API or admin behavior.

## Requirements

### Requirement: Patient Registration
The system MUST allow a visitor to register as a patient with name, identification number (DNI/Cédula), phone, email, and password. Registration MUST create a User with role `patient` and a Patient profile with the provided data.

#### Scenario: Happy path registration
- GIVEN a visitor with valid name, identification number, phone, email, and password
- WHEN the visitor submits the registration form at `/patient/register`
- THEN a User with role `patient` and a Patient profile are created with the provided data
- AND the visitor is redirected to `/patient/dashboard` as an authenticated session

#### Scenario: Duplicate email is rejected
- GIVEN an existing user with email `a@b.com`
- WHEN a visitor attempts to register with email `a@b.com`
- THEN registration fails and the form shows a duplicate-email error

#### Scenario: Duplicate identification number is rejected
- GIVEN an existing patient with identification number `DNI-11111111`
- WHEN a visitor attempts to register with identification number `DNI-11111111`
- THEN registration fails and the form shows a duplicate-identification-number error

### Requirement: Patient Authentication
The system MUST authenticate patients via the web guard session. The login form MUST be at `/patient/login`.

#### Scenario: Login with valid credentials
- GIVEN a registered patient with valid credentials
- WHEN the patient submits email and password at `/patient/login`
- THEN the patient is redirected to `/patient/dashboard`

#### Scenario: Login with invalid credentials
- GIVEN a registered patient
- WHEN a visitor submits an incorrect password at `/patient/login`
- THEN the system shows a generic auth error and does NOT create a session

### Requirement: Patient Dashboard
The system MUST display the authenticated patient's upcoming appointments (date, time, doctor name, specialty, status) at `/patient/dashboard`. The dashboard also shows past appointments (completed, cancelled, no_show) for reference.

#### Scenario: Dashboard shows upcoming appointments
- GIVEN a patient with 2 pending or confirmed appointments in the future
- WHEN the patient visits `/patient/dashboard`
- THEN the page lists both appointments with date, time, doctor name, specialty, and status

#### Scenario: Dashboard with no appointments
- GIVEN a patient with zero appointments
- WHEN the patient visits `/patient/dashboard`
- THEN the page shows an empty-state message

#### Scenario: Dashboard shows past appointments
- GIVEN a patient with past appointments in completed, cancelled, or no_show states
- WHEN the patient visits `/patient/dashboard`
- THEN the page shows a "Past Appointments" section listing up to 10 recent past appointments with their status

#### Scenario: Dashboard does not show cancelled upcoming appointments in the upcoming section
- GIVEN a patient with an upcoming appointment in cancelled state
- WHEN the patient visits `/patient/dashboard`
- THEN the cancelled appointment does not appear in the Upcoming Appointments section

### Requirement: Doctor Listing
The system MUST allow a patient to browse doctors with specialty filtering. Each doctor card MUST show the doctor's name and specialty.

#### Scenario: Browse all doctors
- GIVEN 5 doctors with various specialties
- WHEN a patient visits `/patient/doctors`
- THEN the page lists all 5 doctors with name and specialty

#### Scenario: Filter by specialty
- GIVEN doctors in "Cardiology" and "Dermatology"
- WHEN a patient selects the "Cardiology" filter
- THEN only doctors with `specialty = Cardiology` are shown

### Requirement: Appointment Booking
The system MUST allow a patient to view a doctor's available slots for the next 7 days and book an appointment. Booking MUST enforce the existing rules: slot free, 2-hour anticipation, no patient overlap.

#### Scenario: Happy path booking
- GIVEN a patient viewing doctor D's available slots
- WHEN the patient selects a valid slot at `now + 3h` and confirms
- THEN an appointment with status `pending` is created for that patient and doctor

#### Scenario: Slot already taken
- GIVEN a slot that was booked by another patient between page load and submission
- WHEN the patient attempts to book it
- THEN the system shows a "slot no longer available" error without crashing

### Requirement: Appointment Cancellation
The system MUST allow a patient to cancel their own appointment within the 24h window. Outside this window the cancellation MUST be rejected.

#### Scenario: Cancel inside the window
- GIVEN a pending appointment starting at `now + 48h`
- WHEN the patient cancels it from the dashboard
- THEN the appointment status becomes `cancelled`

#### Scenario: Cancel outside the window
- GIVEN a pending appointment starting at `now + 12h`
- WHEN the patient attempts to cancel it
- THEN the cancellation is rejected and the system shows a "too close to appointment" error

### Requirement: Patient Profile
The system MUST allow an authenticated patient to view and update their profile data. Editable fields include: name, email, identification number, phone, birth date, and gender.

#### Scenario: Profile shows current data
- GIVEN an authenticated patient with profile data
- WHEN the patient visits `/patient/profile`
- THEN the page shows all current profile values

#### Scenario: Profile updates correctly
- GIVEN an authenticated patient
- WHEN the patient submits the profile form with valid data
- THEN the profile is updated and a success message is shown

#### Scenario: Profile updates birth date and gender
- GIVEN an authenticated patient
- WHEN the patient submits the profile form with birth date and gender
- THEN both fields are saved to the patient record

### Requirement: Existing Backend Integrity
The system MUST NOT modify, remove, or interfere with any existing API routes, admin panel routes, or backend behavior. All existing 18 Sanctum API routes and the Filament admin panel MUST remain fully functional.

#### Scenario: API routes remain accessible
- GIVEN a valid API token for an existing route
- WHEN the existing API endpoint is requested
- THEN the response status and payload are identical to before the change

#### Scenario: Admin panel remains accessible
- GIVEN an authenticated admin user
- WHEN the admin visits `/admin` or `/doctor`
- THEN the panel renders normally and all existing resources are reachable
