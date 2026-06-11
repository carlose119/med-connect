<!-- Source: openspec/changes/archive/agenda-core/specs/users-roles/spec.md -- synced 2026-06-01 (first-time archive, no canonical content existed) -->
# Users & Roles

## Purpose
The system identifies every actor with a `User`. Each user has exactly one of three roles: admin, doctor, or patient. Roles drive authorization through Gate policies and Filament panel access.

## ADDED Requirements

### Requirement: Role Predicates
The system MUST classify a user as exactly one of admin, doctor, or patient. The `User` model MUST provide `isAdmin()`, `isDoctor()`, `isPatient()` returning true only for the matching role.

#### Scenario: Each predicate is true for exactly one role
- **Given** three users, one per role
- **When** `isAdmin()`, `isDoctor()`, and `isPatient()` are evaluated on each
- **Then** only the admin's `isAdmin()` is true, only the doctor's `isDoctor()` is true, and only the patient's `isPatient()` is true

### Requirement: Admin Gate Policy
The system MUST grant an admin the ability to manage any resource the admin panel exposes. A non-admin SHALL NOT satisfy the admin gate.

#### Scenario: Admin passes the manage-any gate
- **Given** a user with role `admin`
- **When** the admin gate `manage-any` is evaluated
- **Then** the gate returns `true`

#### Scenario: Non-admin fails the manage-any gate
- **Given** a user with role `doctor` or `patient`
- **When** the admin gate `manage-any` is evaluated
- **Then** the gate returns `false`

### Requirement: Doctor Gate Policy
The system MUST grant a doctor the ability to read clinical data of patients assigned through at least one appointment. A doctor SHALL NOT read clinical data of patients with whom they have no appointment.

#### Scenario: Doctor can read an assigned patient
- **Given** doctor `D` with at least one appointment with patient `P`
- **When** the gate for reading patient `P` is evaluated for `D`
- **Then** the gate returns `true`

#### Scenario: Doctor cannot read an unassigned patient
- **Given** doctor `D` with no appointment with patient `Q`
- **When** the gate for reading patient `Q` is evaluated for `D`
- **Then** the gate returns `false`

### Requirement: Patient Gate Policy
The system MUST grant a patient the ability to read only their own clinical data. No other patient SHALL be readable by them.

#### Scenario: Patient can read own data
- **Given** patient `P`
- **When** the gate for reading patient `P` is evaluated for `P`
- **Then** the gate returns `true`

#### Scenario: Patient cannot read another patient
- **Given** patient `P` and a different patient `Q`
- **When** the gate for reading patient `Q` is evaluated for `P`
- **Then** the gate returns `false`

### Requirement: Filament Panel Access
The system MUST restrict `/admin` to role `admin` and `/doctor` to role `doctor`. Any other actor MUST receive a `403` response.

#### Scenario: Admin reaches the admin panel
- **Given** a user with role `admin`
- **When** the user requests `/admin`
- **Then** the panel renders normally

#### Scenario: Non-admin is denied the admin panel
- **Given** a user with role `doctor` or `patient`
- **When** the user requests `/admin`
- **Then** the response is `403`

#### Scenario: Doctor reaches the doctor panel
- **Given** a user with role `doctor`
- **When** the user requests `/doctor`
- **Then** the panel renders normally

#### Scenario: Non-doctor is denied the doctor panel
- **Given** a user with role `admin` or `patient`
- **When** the user requests `/doctor`
- **Then** the response is `403`

### Requirement: Doctor Invitation Token Generation
When an admin creates a doctor via the admin panel, the system MUST generate a unique invitation token and send an activation email to the doctor's address.

#### Scenario: Admin creates doctor and invitation email is sent
- **Given** an admin is on the "Create User" page in the admin panel
- **When** the admin submits the form with role `doctor`, a valid email `doctor@example.com`, and no password field
- **Then** the system creates the user with `is_active = false` and generates a UUID v4 invitation token stored as a SHA-256 hash
- **And** the system dispatches an `InvitationActivated` mailable with an activation link `/invitation/{token}`
- **And** the email lands in `storage/logs/mail.log`

### Requirement: Doctor Activation via Invitation Link
A doctor with a pending invitation MUST be able to set their own password via the activation link. The system MUST NOT send a pre-set temp password.

#### Scenario: Doctor lands on valid invitation page
- **Given** a doctor `D` exists with `is_active = false` and a valid (non-expired) invitation token
- **When** `D` visits `/invitation/{valid-token}`
- **Then** the system renders the activation form with password and password confirmation fields
- **And** the page contains no pre-filled or auto-generated credentials

#### Scenario: Doctor sets password and account activates
- **Given** a doctor `D` is on the activation form at `/invitation/{valid-token}`
- **When** `D` submits the form with a matching password and password confirmation
- **Then** the system sets `D.password` to the submitted hash
- **And** the system sets `D.is_active = true`
- **And** the system clears `D.invitation_token` and `D.invitation_sent_at`
- **And** the system redirects to `/doctor/login` with a success flash message

#### Scenario: Doctor tries expired token
- **Given** a doctor `D` has an invitation token that is older than 7 days
- **When** `D` visits `/invitation/{expired-token}`
- **Then** the system renders a "Enlace expirado" page with instructions to contact the admin for a new invitation

#### Scenario: Doctor tries to reuse token after activation
- **Given** a doctor `D` has previously activated their account via the invitation link
- **When** `D` visits `/invitation/{consumed-token}` (same token from previous activation)
- **Then** the system redirects to `/doctor/login`

### Requirement: Doctor Re-invitation
When an admin re-creates a doctor with the same email as an existing (inactive) doctor, the system MUST invalidate the previous token and issue a new one.

#### Scenario: Admin re-creates doctor with same email
- **Given** a doctor `D` exists with email `doctor@example.com`, `is_active = false`, and an existing invitation token `T1`
- **When** an admin creates a new user with email `doctor@example.com` and role `doctor`
- **Then** the system reuses the existing user record (same `id`)
- **And** the system generates a new invitation token `T2` replacing `T1`
- **And** the system updates `invitation_sent_at` to the current timestamp
- **And** the system sends a new `InvitationActivated` email with link `/invitation/{T2}`

### Requirement: Doctor Panel Login After Activation
A doctor who has activated their account via the invitation link MUST be able to log in with their new password.

#### Scenario: Doctor logs in after activation
- **Given** a doctor `D` has successfully completed the invitation flow and `is_active = true`
- **When** `D` submits valid credentials to the doctor login endpoint
- **Then** the system authenticates `D` and redirects to the doctor panel dashboard
