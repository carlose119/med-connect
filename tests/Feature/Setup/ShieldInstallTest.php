<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Slice 1 / PR 1 — REQ-ADV-1 "Shield Package Installation"
 * (openspec/changes/rbac-advanced/specs/users-roles/advanced/spec.md).
 *
 * Asserts that after `composer require bezhansalleh/filament-shield:^4.2.0` +
 * `php artisan shield:install` + the resulting `create_permission_tables`
 * migration, the Spatie permission tables exist and `Role` /
 * `Permission` Eloquent models resolve without driver errors on the
 * SQLite test driver (per phpunit.xml `<env name="DB_CONNECTION"
 * value="sqlite"/>`). MariaDB 10.11.9 parity is verified manually
 * during the local `php artisan migrate` step (slice 1 task 1.2) —
 * SQLite is sufficient for the in-process Pest assertion.
 *
 * RED state (pre-GREEN): the `Spatie\Permission\Models\Role` and
 * `Spatie\Permission\Models\Permission` classes do not exist in the
 * vendor tree until `composer require bezhansalleh/filament-shield`
 * lands. The `roles` / `permissions` / `model_has_roles` tables do
 * not exist until the `create_permission_tables` migration that
 * `php artisan shield:install` publishes runs. Both preconditions
 * make the assertions below fail.
 *
 * Scope-bleed decision (recorded in commit body + apply-progress):
 *   The slice 1 spec scenario "Shield tables exist and resolve on
 *   both drivers" only requires the Spatie tables to be present and
 *   the Eloquent models to resolve. The "User can be assigned a role
 *   via the pivot table" sub-claim is split off into slice 3, where
 *   `Spatie\Permission\HasRoles` lands on `app/Models/User.php` per
 *   design.md Decision 3 + File Changes table row
 *   `app/Models/User.php — Modify | 3`. Keeping slice 1 to "zero
 *   app code" preserves the rollback boundary (git revert + composer
 *   remove). Slice 3's `SpatieRolePolicyTest` (Phase 3 task 3.1)
 *   will exercise the `assignRole()` / `hasRole()` path.
 */
uses(RefreshDatabase::class);

it('roles table exists after the shield:install permission-tables migration runs', function () {
    // REQ-ADV-1 / Slice 1: Schema::hasTable() is the only slice 1
    // assertion that does NOT depend on the Spatie Eloquent models
    // being autoloadable, so it is the earliest signal that the
    // migration landed.
    expect(Schema::hasTable('roles'))->toBeTrue();
});

it('permissions table exists after the shield:install permission-tables migration runs', function () {
    expect(Schema::hasTable('permissions'))->toBeTrue();
});

it('model_has_roles pivot table exists after the shield:install permission-tables migration runs', function () {
    // The pivot is the spine of the spec scenario
    // "Shield tables exist and resolve on both drivers" — without
    // it the additive Spatie role lookup in slice 3 has nowhere
    // to write.
    expect(Schema::hasTable('model_has_roles'))->toBeTrue();
});

it('a row can be inserted into the roles table and queried back', function () {
    // Triggers the Spatie Role::create() path. The model's
    // `guard_name` defaulting to `config('auth.defaults.guard')`
    // is enough for SQLite.
    $role = Role::create([
        'name' => 'slice-1-test-admin',
        'guard_name' => 'web',
    ]);

    expect($role)->toBeInstanceOf(Role::class);
    expect($role->exists)->toBeTrue();
    expect($role->name)->toBe('slice-1-test-admin');

    // Read-back is a separate driver round-trip; this is the
    // assertion that proves the row was persisted, not merely
    // returned from the in-memory model state.
    $fetched = Role::query()->where('name', 'slice-1-test-admin')->first();
    expect($fetched)->not->toBeNull();
    expect($fetched?->id)->toBe($role->id);
});

it('a row can be inserted into the permissions table and queried back', function () {
    $permission = Permission::create([
        'name' => 'slice-1-test-permission',
        'guard_name' => 'web',
    ]);

    expect($permission)->toBeInstanceOf(Permission::class);
    expect($permission->exists)->toBeTrue();
    expect($permission->name)->toBe('slice-1-test-permission');

    $fetched = Permission::query()->where('name', 'slice-1-test-permission')->first();
    expect($fetched)->not->toBeNull();
    expect($fetched?->id)->toBe($permission->id);
});

it('Role::query() and Permission::query() resolve without driver errors', function () {
    // The spec scenario's "resolve without driver errors" claim is
    // what a real driver round-trip proves — count() forces a SELECT
    // through Eloquent + the connection. Empty result is correct
    // (RefreshDatabase ran a fresh migrate on a clean SQLite memory
    // database) — what matters is that the call returns an int, not
    // a thrown PDOException.
    expect(Role::query()->count())->toBeInt();
    expect(Permission::query()->count())->toBeInt();
});
