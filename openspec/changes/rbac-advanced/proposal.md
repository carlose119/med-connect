# Proposal: rbac-advanced

## Intent

`users-roles` (5 REQs, 3 Gate policies, 18 API routes) has no admin UI for roles/permissions. Add `filament/shield` 4.2.0 as a **second** authorization layer for the Filament admin panel only. The 3 existing Gate policies keep governing the API REST surface unchanged. Forecast: 15 caps, 36 reqs, 141 + ADDED scenarios, 18 routes.

## Scope

### In Scope
- Install `filament/shield` 4.2.0; publish config; run Shield's permission-tables migration
- `RoleResource` + `PermissionResource`; register in `AdminPanelProvider`; super-admin flow
- Add `HasRoles` to `User` (additive — keep ENUM `role` + `isAdmin/isDoctor/isPatient`)
- Extend 3 Gate policies with **additive** Spatie Role lookups; rules + tests unchanged

### Out of Scope
Replace `role` ENUM; new API routes / policy methods; doctor/patient panel permission UI.

## Capabilities

### New Capabilities
- `users-roles/advanced` — Shield-mediated role/permission CRUD + additive Spatie Role support. Sub-cap pattern (`agenda/api`, `agenda/readme-revamp`).

### Modified Capabilities
None.

## Approach

**Three-slice chained PR** (stacked-to-main): (1) `composer require filament/shield` 4.2.0 + `shield:install` + config + migration [150-200 LOC, **High** — composer conflict risk]; (2) `RoleResource` + `PermissionResource` + `AdminPanelProvider` super-admin wiring [150-200 LOC, Med]; (3) `HasRoles` on `User` + additive Spatie lookups in 3 Gate policies [100-150 LOC, **High** — correctness].

**Why not other options** (sdd-explore): A breaks authz; C loses role-UI; D = full pivot (~3000+ LOC).

## Affected Areas

- `composer.json` / `composer.lock` — add `filament/shield` 4.2.0; resolves `spatie/laravel-permission` 6/7
- `app/Models/User.php` — add `HasRoles` trait (additive)
- `app/Policies/{User,Patient,Appointment}Policy.php` — additive Spatie Role lookups
- `app/Filament/Resources/{Roles,Permissions}/*` + `app/Providers/Filament/AdminPanelProvider.php` — new Filament CRUD; register Shield plugin
- `database/migrations/*_create_permission_tables.php` + `config/filament-shield.php` + `openspec/specs/users-roles/advanced/spec.md` — **new** Shield migration, config, sub-cap spec

## Risks

- **Med — Composer conflict** (`spatie/laravel-model-states` 2.13 vs Shield's `spatie/laravel-permission` 6/7). Slice 1 runs `composer why-not` first; if conflicts, pin `spatie/laravel-permission:^6` (peer Spatie pkgs typically reconcile).
- **Med — Slice 3 breaks 18 API routes**. Existing policy tests must pass on SQLite (CI) + MariaDB (local); no API contract change; new ADDED scenarios cover Spatie paths.

## Rollback Plan

Each slice independently revertible via `git revert <merge-commit>` (stacked on main):

- **Slice 1**: `composer remove filament/shield` + `spatie/laravel-permission`; drop migration + config. **Zero app code touched.**
- **Slice 2**: delete 2 Filament resource folders; unregister Shield plugin.
- **Slice 3**: revert 3 Gate policies; remove `HasRoles`. Existing policy tests = safety net.

## Success Criteria

- [ ] `composer install` succeeds; Shield migration clean on SQLite **and** MariaDB 10.11.9
- [ ] `/admin/roles` + `/admin/permissions` render for admin; super-admin toggle works
- [ ] All existing policy tests pass on both DB drivers; 18 API routes respond identically
- [ ] Cumulative: 15 caps, 36 reqs, 141 + ADDED scenarios, 18 routes; 3 chained PRs <400 LOC each
