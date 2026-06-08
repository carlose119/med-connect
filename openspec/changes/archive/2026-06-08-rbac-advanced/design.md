# Design: rbac-advanced

## Technical Approach

Adds `filament/shield` 4.2.0 as a **second**, panel-scoped authorization layer for the Filament admin UI. The 3 Gate policies keep governing the 18 API routes; they gain **additive** Spatie Role lookups, while the `role` ENUM stays the REST source of truth. Layer 1: `/api/*` → 3 policies (ENUM OR `hasRole`). Layer 2: `/admin/*` → Filament + Shield + super_admin toggle. Option B; 3-slice chained PR split locked by `proposal.md`.

## Architecture Decisions

### Decision 1 — Option B (layered integration)

| Option | Tradeoff | Decision |
|---|---|---|
| A: Pure Shield (delete Gate policies) | Breaks `isAdmin()` / `canAccessPanel()`; 18 API routes lose ENUM contract | Reject |
| **B: Layered — Shield for panel, Gate policies for API (additive Spatie lookups)** | Two observable layers; zero API breakage | **Adopt** |
| C: Pure Gate + hand-rolled admin UI | ~600+ LOC; no super-admin flow; slower | Reject |
| D: Custom RBAC pivot (~3000 LOC) | Out of scope for v1; review-burden blowup | Reject |

### Decision 2 — Composer resolution for `spatie/laravel-permission`

`spatie/laravel-model-states` 2.13 is pinned. Shield 4.2.0 needs `spatie/laravel-permission` 6/7.

**UPDATED 2026-06-05 (slice 1 apply)** — the `^6 then ^7` fallback is **obsolete** on Laravel 13. The actual path that landed is an **inline alias** that exposes 8.0.0 to Shield as 7.0.0.

| Step | Action | Reality on Laravel 13 |
|---|---|---|
| 1 | `composer why-not spatie/laravel-permission` BEFORE merging slice 1 | ✅ `spatie/laravel-permission 8.0.0` released 2026-05-30 (one week before apply) — `why-not` shows it as a candidate |
| 2 | Pin `spatie/laravel-permission:^6` (peer Spatie pkgs typically reconcile) | ❌ FAIL: `6.0.0` requires `illuminate/auth ^8.12\|^9.0\|^10.0` (Laravel 8/9/10 only) |
| 3 | If `^6` fails, fall back to `^7`; document pivot in PR body | ❌ FAIL: `7.0.0` requires `illuminate/auth ^12.0` (Laravel 12 only — not 13) |
| 4 | `composer install` green; `model-states` 2.13 and `permission` 6/7 both present | ✅ `composer require filament/shield:4.2.0 'spatie/laravel-permission:8.0.0 as 7.0.0' --with-all-dependencies` → 109 packages installed; `composer show spatie/laravel-permission --all` reports `versions : * 8.0.0` and `source : .../tree/7.0.0` (the alias exposes 8.0.0 to Shield as 7.0.0) |

**Final `composer.json` line (slice 1, locked)**:
```json
"spatie/laravel-permission": "8.0.0 as 7.0.0",
```

**Why inline aliasing is safe** (documented in slice 1 apply-progress + GREEN commit `ed6c618` body):
- Shield 4.2.0's API surface (HasRoles trait, Role/Permission models, Gate integration) is identical between permission 7.x and 8.x — the 8.0.0 changelog is mostly Laravel 12/13 support + dependency updates, not breaking model API changes.
- The 6 slice 1 GREEN-state tests exercise the only code paths Shield 4.2.0 actually needs from the permission package: `Role::create`, `Permission::create`, `Role::query`, `Permission::query`. All pass.
- If a future Shield feature path needs an 8.x-only API, the alias becomes a no-op when Shield upstream widens the constraint to `^6|^7|^8`. The lock file already records 8.0.0.

**Fallback if aliasing causes a runtime issue in slice 2/3**:
- Patch `vendor/bezhansalleh/filament-shield/composer.json` via `cweagans/composer-patches` to widen the constraint to `^6|^7|^8`. Local patch, no upstream PR needed.
- OR: open an upstream PR on bezhanSalleh/filament-shield 4.x branch to widen the constraint.

**Risk**: low for slice 1 (table existence + Eloquent create/query don't touch version-specific APIs). Medium for slice 2 (RoleResource + PermissionResource wire format) and slice 3 (HasRoles + Gate lookups) — those are the real regression net. Slice 2 RED→GREEN test suite stayed at 6 passed (11 assertions) and the full suite stayed green (165 passed, 4 skipped, 612 assertions), so the alias survives end-to-end at the Resource layer too.

### Decision 3 — Additive Spatie Role lookup in 3 Gate policies

```php
public function view(User $actor, Patient $patient): bool
{
    if ($actor->isAdmin() || $actor->hasRole('admin')) { return true; }
    if ($actor->isDoctor() || $actor->hasRole('doctor')) {
        return $actor->doctor?->appointments()
            ->where('patient_id', $patient->id)->exists() ?? false;
    }
    if ($actor->isPatient() || $actor->hasRole('patient')) {
        return $actor->id === $patient->user_id;
    }
    return false;
}
```

Apply to every method; do NOT replace ENUM logic. `User` gains `HasRoles` in slice 3. Same shape for `UserPolicy` (5 methods) and `AppointmentPolicy` (6 methods). ENUM side intact → existing `PolicyTest` passes

### Decision 4 — Filament Shield plugin registration

```php
->plugin(\BezhanSalleh\FilamentShield\FilamentShieldPlugin::make())
->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
```

Additive chain call; existing `discoverResources` glob stays. Shield auto-discovers the new resources. New resources follow the nested convention (`{Plural}/{ResourceName}.php` + `Pages/*` + `Schemas/*` + `Tables/*`), mirroring `Users\UserResource`. `User` adds `HasRoles;`

## File Changes

| File | Action | Slice |
|---|---|---|
| `composer.json` / `composer.lock` | Modify | 1 |
| `database/migrations/..._create_permission_tables.php` | Create | 1 |
| `config/filament-shield.php` | Create | 1 |
| `app/Filament/Resources/Roles/{RoleResource,Pages/*,Schemas/*,Tables/*}.php` | Create | 2 |
| `app/Filament/Resources/Permissions/{PermissionResource,Pages/*,Schemas/*,Tables/*}.php` | Create | 2 |
| `app/Providers/Filament/AdminPanelProvider.php` | Modify | 2 |
| `app/Models/User.php` | Modify | 3 |
| `app/Policies/UserPolicy.php` | Modify | 3 |
| `app/Policies/PatientPolicy.php` | Modify | 3 |
| `app/Policies/AppointmentPolicy.php` | Modify | 3 |
| `tests/Feature/Setup/ShieldInstallTest.php` | Create | 1 |
| `tests/Feature/Admin/RoleResourceAccessTest.php` | Create | 2 |
| `tests/Unit/Policies/SpatieRolePolicyTest.php` | Create | 3 |

## Testing Strategy

| Layer | What | Approach |
|---|---|---|
| Feature (1) | Shield migration clean; tables exist; `Role::query()` resolves | `migrate:fresh` + assert `roles`, `permissions`, 3 pivots |
| Feature (2) | Admin reaches pages; non-admin 403; existing resources still reachable | `actingAs(admin)->get('/admin/roles')->assertSuccessful()`; `actingAs(doctor|patient)->get('/admin/roles|permissions')->assertForbidden()` (4 cases) |
| Feature (2) | Super-admin toggle grants `super_admin` Shield role | Hit toggle; assert `hasRole('super_admin') && isAdmin()` |
| Unit (3) | Spatie role grants access where ENUM denies; existing `PolicyTest` passes | Patient user + `assignRole('doctor')` + assigned appointment; `can('view', $patient)` true; re-run `PolicyTest.php` |

## Migration / Rollout

3-slice chained PRs (stacked-to-main), per `proposal.md` §Rollback Plan:

| Slice | Boundary | Rollback |
|---|---|---|
| 1 — Composer + migration + config | `composer install` green; `migrate:fresh` clean; **zero app code** | `composer remove filament/shield spatie/laravel-permission`; drop migration; delete `config/filament-shield.php` |
| 2 — Resources + plugin | `/admin/roles` + `/admin/permissions` render for admin; non-admin 403; `UserResource`/`SpecialtyResource` still reachable | Delete 2 resource folders; remove `->plugin()` call |
| 3 — `HasRoles` + 3 policy lookups | 18 API routes respond identically; 3 ADDED scenarios green | Revert 3 policies; remove `HasRoles` |

## Open Questions

- [ ] **Shield `super_admin` vs `role=admin` ENUM** — granting `super_admin` does NOT change the ENUM. Non-admin 403 must use `isAdmin()`, not Shield membership. Confirm `canAccessPanel()` keeps gating on the ENUM.
- [ ] **Spatie role precedence in `PatientPolicy@view`** — `role=admin` ENUM with no Spatie `admin` role must still pass (ENUM wins); Spatie `doctor` with no ENUM match must pass via the Spatie OR. Slice 3 tests both.
- [ ] **Driver parity + create page** — `create_permission_tables` must run clean on SQLite and MariaDB 10.11.9 before slice 1 merges. Also: does `PermissionResource` need a `create` page, or does Shield manage via `shield:generate`?
