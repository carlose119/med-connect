# Tasks: rbac-advanced — Shield-mediated role/permission layer (additive)

## Review Workload Forecast

| Field | Value |
|---|---|
| Estimated changed lines | 400-550 across 3 slices |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 -> PR 2 -> PR 3 |
| Delivery strategy | ask-always |
| Chain strategy | stacked-to-main |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|---|---|---|---|
| 1 | Composer + Shield install + config + migration | PR 1 `feat/rbac-advanced-slice-1-shield-install` | off main; zero app code; composer risk |
| 2 | RoleResource + PermissionResource + plugin | PR 2 `feat/rbac-advanced-slice-2-admin-ui` | stacked off PR 1; mirrors Specialties |
| 3 | `HasRoles` on User + 3 policy additive lookups | PR 3 `feat/rbac-advanced-slice-3-spatie-lookups` | stacked off PR 2; 18 API routes unchanged |

## Phase 0 — Pre-Slice Open Questions (resolve BEFORE apply)

- [ ] 0.1 Resolve OQ #1: `canAccessPanel()` keeps gating on ENUM `isAdmin()`, NOT Shield `super_admin` — before slice 2.
- [ ] 0.2 Resolve OQ #2: Spatie role precedence — ENUM OR Spatie passes, both tested in slice 3 — before slice 3.
- [ ] 0.3 Resolve OQ #3: `PermissionResource` create-page strategy (Shield `shield:generate` vs custom) — before slice 2.

## Phase 1 — Slice 1 / PR 1: Composer + Shield install (REQ `Shield Package Installation`)

- [x] 1.1 RED: write `tests/Feature/Setup/ShieldInstallTest.php` — `migrate:fresh` SQLite, assert `roles`+`permissions`+2 pivots; `Role::query()`+`Permission::query()` resolve.
- [x] 1.2 GREEN: `composer why-not spatie/laravel-permission`; on conflict pin `^6` (fallback `^7`); `composer require filament/shield:4.2.0`; `php artisan shield:install`; publish `config/filament-shield.php`; commit migration.
- [x] 1.3 VERIFY: `vendor/bin/pest --filter=ShieldInstallTest` flips RED->GREEN; `migrate:fresh` clean on SQLite AND MariaDB 10.11.9.
- [x] 1.4 Housekeeping: mark 1.1-1.3 done in tasks.md (obs #66); commit per `work-unit-commits` rules.

## Phase 2 — Slice 2 / PR 2: RoleResource + PermissionResource + plugin (REQ `Admin Panel Role and Permission UI`)

- [x] 2.1 RED: write `tests/Feature/Admin/RoleResourceAccessTest.php` — admin->`/admin/roles` 200; doctor/patient->403; non-admin->`/admin/permissions` 403; super-admin toggle grants `super_admin` Spatie role AND `isAdmin()` stays true.
- [x] 2.2 GREEN: create `app/Filament/Resources/Roles/{RoleResource.php,Pages/{ListRoles,CreateRole,EditRole}.php,Schemas/RoleForm.php,Tables/RolesTable.php}` mirroring `Specialties/*`; create `app/Filament/Resources/Permissions/PermissionResource.php` (create-page per OQ #3).
- [x] 2.3 GREEN: register `->plugin(\BezhanSalleh\FilamentShield\FilamentShieldPlugin::make())` in `app/Providers/Filament/AdminPanelProvider.php` (additive chain; keep `discoverResources`).
- [x] 2.4 VERIFY: `vendor/bin/pest --filter=RoleResourceAccessTest` flips RED->GREEN; `UserResource`+`SpecialtyResource` still reachable; mark 2.1-2.3 done.

## Phase 3 — Slice 3 / PR 3: HasRoles + 3 policy additive lookups (REQ `Additive Spatie Role Lookups`)

- [ ] 3.1 RED: write `tests/Unit/Policies/SpatieRolePolicyTest.php` — patient+`assignRole('doctor')`+assigned appt -> `can('view',$patient)` true; no Spatie role -> unaffected; existing `PolicyTest` still green.
- [ ] 3.2 GREEN: add `Spatie\Permission\HasRoles` trait to `app/Models/User.php` (additive; keep traits, ENUM predicates, `canAccessPanel()`).
- [ ] 3.3 GREEN: extend `app/Policies/{User,Patient,Appointment}Policy.php` with additive `|| $actor->hasRole('<role>')` per method; do NOT replace ENUM.
- [ ] 3.4 VERIFY: `vendor/bin/pest --filter=SpatieRolePolicyTest` flips RED->GREEN; run full `vendor/bin/pest` (existing policy tests pass SQLite); 18 API routes identical; mark 3.1-3.3 done.
