# users-roles/advanced Specification

## Purpose

Additive authorization layer for the Filament admin panel powered by `filament/shield` 4.2.0. Shield provides role/permission CRUD in the admin UI and a super-admin flow. The three existing Gate policies (`UserPolicy`, `PatientPolicy`, `AppointmentPolicy`) gain additive `Spatie\Permission\Models\Role` lookups so a user with a Spatie-granted role passes the gate, while the existing role-ENUM contract (admin/doctor/patient) stays the source of truth for the API REST surface. Shield governs only the admin panel; the 18 existing API routes are unchanged.

## Requirements

### Requirement: Shield Package Installation

The system MUST provide `filament/shield` 4.2.0 as a resolved Composer dependency, with its `config/filament-shield.php` config published and its permission-tables migration applied. The migration MUST be clean on both SQLite (test driver) and MariaDB 10.11.9 (local driver). After install, the `roles`, `permissions`, and their pivot table MUST exist and be queryable from a `User` model that has gained the `HasRoles` trait.

#### Scenario: Shield tables exist and resolve on both drivers

- **Given** a fresh install of `filament/shield` 4.2.0 on SQLite and on MariaDB 10.11.9
- **When** the permission-tables migration runs and a `Role` and a `Permission` are inserted
- **Then** the rows persist and a `User` can be assigned the role via the pivot table
- **And** `Role::query()` and `Permission::query()` resolve without driver errors

### Requirement: Admin Panel Role and Permission UI

The admin panel MUST expose a `RoleResource` and a `PermissionResource` reachable at `/admin/roles` and `/admin/permissions`, registered via the `AdminPanelProvider` and the Shield plugin. The panel MUST provide a super-admin toggle that grants a user the `super_admin` Shield role. A user who is not an admin (per the existing `isAdmin()` predicate) MUST receive a `403` response when accessing either page.

#### Scenario: Admin reaches role and permission pages

- **Given** an authenticated admin user
- **When** the user requests `/admin/roles` or `/admin/permissions`
- **Then** the page renders the role/permission list
- **And** the existing Filament resources (Users, Specialties) remain reachable

#### Scenario: Non-admin is denied the role/permission pages

- **Given** an authenticated user with role `doctor` or `patient`
- **When** the user requests `/admin/roles` or `/admin/permissions`
- **Then** the response is `403`

#### Scenario: Super-admin toggle grants the Shield super-admin role

- **Given** an authenticated admin editing a target user record
- **When** the admin activates the super-admin toggle and saves
- **Then** the target user holds the `super_admin` Shield role
- **And** the existing `isAdmin()` predicate on the same user remains `true`

### Requirement: Additive Spatie Role Lookups in Gate Policies

The system MUST extend `UserPolicy`, `PatientPolicy`, and `AppointmentPolicy` with additive lookups against `Spatie\Permission\Models\Role`. The existing role-ENUM contract MUST remain the source of truth for the API REST surface; Spatie roles grant access additively (a user with the Spatie role passes; a user without it is unaffected). The existing 18 API routes and the existing policy tests MUST pass on both SQLite and MariaDB 10.11.9.

#### Scenario: Spatie role grants access via the additive path

- **Given** a user who does not satisfy the ENUM predicate for the action
- **And** that user holds the corresponding Spatie role (e.g. `doctor` for `PatientPolicy@view`)
- **When** the policy gate for that action is evaluated
- **Then** the gate returns `true`

#### Scenario: User without the Spatie role is unaffected

- **Given** a user with no Spatie roles and no matching ENUM role for the action
- **When** the policy gate is evaluated
- **Then** the gate returns `false`

#### Scenario: Existing API contract is preserved

- **Given** the existing policy test suite for `UserPolicy`, `PatientPolicy`, `AppointmentPolicy`
- **When** the suite runs on SQLite and on MariaDB 10.11.9
- **Then** all existing tests pass
- **And** the 18 API routes return identical status codes and payloads as before
