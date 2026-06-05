<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Slice 2 / PR 2 — REQ-ADV-2 "Admin Panel Role and Permission UI"
 * (openspec/changes/rbac-advanced/specs/users-roles/advanced/spec.md).
 *
 * Asserts that after creating `app/Filament/Resources/Roles/RoleResource.php`,
 * `app/Filament/Resources/Permissions/PermissionResource.php`, and registering
 * `BezhanSalleh\FilamentShield\FilamentShieldPlugin` in
 * `app/Providers/Filament/AdminPanelProvider.php`:
 *
 *  1. An authenticated admin user (ENUM `role=admin`) reaches `/admin/roles`
 *     and `/admin/permissions` with a 200 response.
 *  2. The existing Filament resources (Users, Specialties) remain reachable
 *     for the admin (no regression on the auto-discovered resources glob).
 *  3. A doctor user (ENUM `role=doctor`) is denied access to `/admin/roles`
 *     with a 403 response.
 *  4. A patient user (ENUM `role=patient`) is denied access to
 *     `/admin/permissions` with a 403 response.
 *  5. The Shield `super_admin` role can be created and assigned to an admin
 *     user via the Spatie `model_has_roles` pivot, AND the existing
 *     `isAdmin()` ENUM predicate on the same user remains `true` (proves
 *     the two layers are independent — OQ #1 resolution).
 *
 * RED state (pre-GREEN): the `app/Filament/Resources/Roles/RoleResource.php`
 * and `app/Filament/Resources/Permissions/PermissionResource.php` files do
 * not exist yet. The Shield plugin is not registered in
 * `app/Providers/Filament/AdminPanelProvider.php`. Filament's
 * `discoverResources` glob cannot find the role/permission resources, so
 * the admin panel routes for them 404 (or fall through to the
 * `canAccessPanel()` 403 gate) and the assertions below fail.
 *
 * The `super_admin` Spatie role assertion uses direct Eloquent
 * `Role::create()` + `model_has_roles` pivot insert. Slice 3 will add
 * `Spatie\Permission\HasRoles` to `app/Models\User.php` (so
 * `$user->hasRole('super_admin')` becomes available), but slice 2 only
 * needs to prove the role exists and is assignable — the `isAdmin()`
 * cross-check is the spec scenario's normative claim.
 */
it('admin reaches the /admin/roles page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/roles')
        ->assertSuccessful();
});

it('admin reaches the /admin/permissions page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/permissions')
        ->assertSuccessful();
});

it('existing Filament resources (Users, Specialties) remain reachable for admin', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertSuccessful();

    $this->actingAs($admin)
        ->get('/admin/specialties')
        ->assertSuccessful();
});

it('doctor is denied the /admin/roles page with 403', function () {
    $doctor = User::factory()->doctor()->create();

    $this->actingAs($doctor)
        ->get('/admin/roles')
        ->assertForbidden();
});

it('patient is denied the /admin/permissions page with 403', function () {
    $patient = User::factory()->patient()->create();

    $this->actingAs($patient)
        ->get('/admin/permissions')
        ->assertForbidden();
});

it('the Shield super_admin role can be assigned to an admin and isAdmin() stays true', function () {
    // OQ #1 resolution evidence: the ENUM `isAdmin()` predicate and the
    // Shield `super_admin` Spatie role are INDEPENDENT. Granting a user
    // the Shield `super_admin` role does NOT flip their ENUM, and the
    // ENUM `isAdmin()` keeps the panel access gate as the source of
    // truth (per `app/Models/User.php` line 55).
    $admin = User::factory()->admin()->create();

    // Pre-condition: admin has the ENUM `isAdmin()` true.
    expect($admin->isAdmin())->toBeTrue();

    // Create the Shield `super_admin` role and assign it to the admin
    // via the Spatie `model_has_roles` pivot directly (HasRoles trait
    // lands in slice 3).
    $superAdminRole = Role::create([
        'name' => 'super_admin',
        'guard_name' => 'web',
    ]);
    $superAdminRole->save();

    DB::table('model_has_roles')->insert([
        'role_id' => $superAdminRole->id,
        'model_type' => User::class,
        'model_id' => $admin->id,
    ]);

    // The Shield `super_admin` role exists and is queryable.
    $fetched = Role::query()->where('name', 'super_admin')->first();
    expect($fetched)->not->toBeNull();
    expect($fetched?->id)->toBe($superAdminRole->id);

    // The pivot row is present (proves the assignment path works
    // independently of the User model's HasRoles trait).
    $pivot = DB::table('model_has_roles')
        ->where('role_id', $superAdminRole->id)
        ->where('model_type', User::class)
        ->where('model_id', $admin->id)
        ->first();
    expect($pivot)->not->toBeNull();

    // The ENUM `isAdmin()` is unaffected by the Spatie role grant —
    // the two layers stay independent per OQ #1.
    $admin->refresh();
    expect($admin->isAdmin())->toBeTrue();
});
