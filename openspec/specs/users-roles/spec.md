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
