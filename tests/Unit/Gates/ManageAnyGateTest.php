<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('manage-any gate', function (): void {
    it('grants manage-any gate to admin user', function (): void {
        $admin = User::factory()->admin()->create();
        expect(Gate::forUser($admin)->allows('manage-any'))->toBeTrue();
    });

    it('denies manage-any gate to doctor user', function (): void {
        $doctor = User::factory()->doctor()->create();
        expect(Gate::forUser($doctor)->allows('manage-any'))->toBeFalse();
    });

    it('denies manage-any gate to patient user', function (): void {
        $patient = User::factory()->patient()->create();
        expect(Gate::forUser($patient)->allows('manage-any'))->toBeFalse();
    });

    it('admin with Spatie admin role passes manage-any gate', function (): void {
        $role = Role::findOrCreate('admin', 'web');
        $user = User::factory()->doctor()->create();
        $user->assignRole($role);
        expect(Gate::forUser($user)->allows('manage-any'))->toBeTrue();
    });

    it('non-admin with Spatie admin role passes manage-any gate', function (): void {
        $role = Role::findOrCreate('admin', 'web');
        $user = User::factory()->doctor()->create();
        $user->assignRole($role);
        expect(Gate::forUser($user)->allows('manage-any'))->toBeTrue();
    });
});