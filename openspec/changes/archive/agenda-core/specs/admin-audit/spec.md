# Admin Audit

## Purpose
The admin audit log records every admin action so the clinic can reconstruct who did what, when, and to which subject. Log rows are immutable; once written, they cannot be edited or removed through public APIs.

## ADDED Requirements

### Requirement: Audit Log Write Contract
The system MUST write a row to `audit_logs` whenever an admin performs an action that changes domain state. Each row MUST capture the actor type, the action verb, the subject type and id, and a timestamp.

#### Scenario: Admin action writes an audit row
- **Given** an admin user with id `A`
- **When** the admin creates a doctor through the admin panel
- **Then** a row exists in `audit_logs` with `actor_type = "admin"`, `actor_id = A.id`, an action verb, a subject type and id pointing to the created doctor, and a timestamp

### Requirement: Immutable Audit Rows
The system MUST persist audit rows as immutable. The `audit_logs` table MUST NOT have an `updated_at` column, and the system MUST NOT expose a public method to update or delete an existing row.

#### Scenario: Audit rows have no updated_at column
- **Given** the persisted schema for `audit_logs`
- **When** the schema is introspected
- **Then** the table has no `updated_at` column

#### Scenario: Audit rows cannot be deleted through the public API
- **Given** an existing `audit_logs` row
- **When** any code path attempts to delete it through a public API
- **Then** the deletion is rejected and the row remains in the table

#### Scenario: Audit rows cannot be updated through the public API
- **Given** an existing `audit_logs` row
- **When** any code path attempts to update any of its fields through a public API
- **Then** the update is rejected and the row remains unchanged
