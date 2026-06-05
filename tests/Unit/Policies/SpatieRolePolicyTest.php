<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Slice 3 / PR 3 — REQ-ADV-3 "Additive Spatie Role Lookups in Gate Policies"
 * (openspec/changes/rbac-advanced/specs/users-roles/advanced/spec.md).
 *
 * Asserts that after adding `Spatie\Permission\HasRoles` to
 * `app/Models/User.php` and adding `|| $actor->hasRole('<role>')` to each
 * branch of `app/Policies/{User,Patient,Appointment}Policy.php`:
 *
 *  1. A user who does NOT satisfy the ENUM predicate for the action AND
 *     holds the corresponding Spatie role passes the gate (additive path
 *     grants access).
 *  2. A user with no Spatie roles and no matching ENUM role is unaffected
 *     (the additive path does not loosen access).
 *  3. The existing API contract is preserved: the existing
 *     `tests/Unit/Policies/PolicyTest.php` still passes (regression check
 *     verified by re-running `vendor/bin/pest --filter=PolicyTest`).
 *
 * RED state (pre-GREEN): `app/Models/User.php` lacks
 * `Spatie\Permission\HasRoles`, so `$user->hasRole(...)` and
 * `$user->assignRole(...)` are undefined. The 3 Gate policies only check
 * the ENUM predicate (`isAdmin/isDoctor/isPatient`), so a "patient" user
 * with a Spatie role is still denied every gate. The assertions below
 * fail with "Call to undefined method App\Models\User::assignRole()".
 */
uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();

    $this->doctorUser = User::factory()->doctor()->create();
    $this->doctor = Doctor::factory()->for($this->doctorUser)->create();

    $this->otherDoctorUser = User::factory()->doctor()->create();
    $this->otherDoctor = Doctor::factory()->for($this->otherDoctorUser)->create();

    $this->patientUser = User::factory()->patient()->create();
    $this->patient = Patient::factory()->for($this->patientUser)->create();

    $this->otherPatientUser = User::factory()->patient()->create();
    $this->otherPatient = Patient::factory()->for($this->otherPatientUser)->create();

    $this->appointment = Appointment::factory()
        ->for($this->doctor)
        ->for($this->patient)
        ->create();
});

it('UserPolicy: Spatie admin role grants view and viewAny to a non-admin ENUM user', function () {
    // A "patient" user (ENUM role=patient) granted the Spatie 'admin'
    // role. The ENUM path: isAdmin()=false. The additive Spatie path:
    // hasRole('admin')=true → view returns true (admin override),
    // viewAny returns true (admin override). Confirms the additive
    // lookups in UserPolicy@view and UserPolicy@viewAny.
    $spatieAdmin = User::factory()->patient()->create();
    $spatieAdmin->assignRole('admin');

    expect($spatieAdmin->can('view', $this->doctorUser))->toBeTrue()
        ->and($spatieAdmin->can('view', $this->patientUser))->toBeTrue()
        ->and($spatieAdmin->can('viewAny', User::class))->toBeTrue();
});

it('UserPolicy: Spatie admin role grants update to a non-admin ENUM user', function () {
    // Confirms UserPolicy@update honors the additive Spatie admin path.
    $spatieAdmin = User::factory()->patient()->create();
    $spatieAdmin->assignRole('admin');

    expect($spatieAdmin->can('update', $this->doctorUser))->toBeTrue()
        ->and($spatieAdmin->can('update', $this->patientUser))->toBeTrue();
});

it('PatientPolicy: Spatie doctor role grants view when the actor has an assigned appointment with the patient', function () {
    // The canonical design example (design.md Decision 3). A "patient"
    // user (ENUM role=patient) with a doctor profile + an assigned
    // appointment with the target patient + Spatie 'doctor' role. The
    // ENUM path: isDoctor()=false (ENUM is patient). The additive Spatie
    // path: hasRole('doctor')=true → enters the doctor branch → the
    // doctor has an assigned appointment with the patient → returns true.
    $spatieDoctorUser = User::factory()->patient()->create();
    $spatieDoctor = Doctor::factory()->for($spatieDoctorUser)->create();
    Appointment::factory()
        ->for($spatieDoctor)
        ->for($this->patient)
        ->create();
    $spatieDoctorUser->assignRole('doctor');

    expect($spatieDoctorUser->can('view', $this->patient))->toBeTrue();
});

it('PatientPolicy: Spatie doctor role grants viewAny to a non-doctor ENUM user', function () {
    // Confirms PatientPolicy@viewAny honors the additive Spatie doctor
    // path. The viewAny branch is `isAdmin() || isDoctor()`; the additive
    // pattern widens it to `isAdmin()||hasRole('admin') || isDoctor()||
    // hasRole('doctor')`.
    $spatieDoctor = User::factory()->patient()->create();
    $spatieDoctor->assignRole('doctor');

    expect($spatieDoctor->can('viewAny', Patient::class))->toBeTrue();
});

it('AppointmentPolicy: Spatie admin role grants view and cancel to a non-admin ENUM user', function () {
    // A "patient" user (ENUM role=patient) with Spatie 'admin' role. The
    // ENUM path: isAdmin()=false, isDoctor()=false, isPatient()=true but
    // the patient branch is the last fallback. The additive Spatie path:
    // hasRole('admin')=true → view returns true (admin override), cancel
    // returns true (admin override).
    $spatieAdmin = User::factory()->patient()->create();
    $spatieAdmin->assignRole('admin');

    expect($spatieAdmin->can('view', $this->appointment))->toBeTrue()
        ->and($spatieAdmin->can('cancel', $this->appointment))->toBeTrue();
});

it('AppointmentPolicy: Spatie admin role grants viewAny to a non-admin ENUM user', function () {
    // Confirms AppointmentPolicy@viewAny honors the additive Spatie
    // admin path. The viewAny branch is `isAdmin() || isDoctor()`; the
    // additive pattern widens it to `isAdmin()||hasRole('admin') ||
    // isDoctor()||hasRole('doctor')`.
    $spatieAdmin = User::factory()->patient()->create();
    $spatieAdmin->assignRole('admin');

    expect($spatieAdmin->can('viewAny', Appointment::class))->toBeTrue();
});

it('a user without Spatie roles and no matching ENUM role is unaffected across all 3 policies', function () {
    // The "no loosening" guarantee (spec.md Scenario 2: "User without the
    // Spatie role is unaffected"). A "patient" user (ENUM role=patient)
    // with NO Spatie roles and no ownership of any record under test.
    // The ENUM path: isAdmin()=false, isDoctor()=false. isPatient()=true
    // but no record ownership. The Spatie path: hasRole(...)=false for
    // every role. Net: every gate returns false.
    $unaffected = User::factory()->patient()->create();

    // UserPolicy — no Spatie roles, no admin/doctor match, no self-view
    // edge (target is a different user).
    expect($unaffected->can('view', $this->doctorUser))->toBeFalse()
        ->and($unaffected->can('view', $this->otherPatientUser))->toBeFalse()
        ->and($unaffected->can('viewAny', User::class))->toBeFalse();

    // PatientPolicy — patient branch only allows viewing own record.
    expect($unaffected->can('view', $this->otherPatient))->toBeFalse()
        ->and($unaffected->can('viewAny', Patient::class))->toBeFalse();

    // AppointmentPolicy — no admin/doctor match, patient branch requires
    // being the patient on the appointment (we are not).
    expect($unaffected->can('view', $this->appointment))->toBeFalse()
        ->and($unaffected->can('cancel', $this->appointment))->toBeFalse()
        ->and($unaffected->can('viewAny', Appointment::class))->toBeFalse();
});
