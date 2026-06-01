<?php

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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

it('lets admins view and update any user', function () {
    expect($this->admin->can('view', $this->doctorUser))->toBeTrue();
    expect($this->admin->can('view', $this->patientUser))->toBeTrue();
    expect($this->admin->can('update', $this->doctorUser))->toBeTrue();
    expect($this->admin->can('viewAny', User::class))->toBeTrue();
});

it('lets users view their own record but not others', function () {
    expect($this->patientUser->can('view', $this->patientUser))->toBeTrue();
    expect($this->patientUser->can('view', $this->doctorUser))->toBeFalse();
    expect($this->patientUser->can('viewAny', User::class))->toBeFalse();
});

it('lets admins and doctors view patient records, patients their own only', function () {
    expect($this->admin->can('view', $this->patient))->toBeTrue();
    expect($this->doctorUser->can('view', $this->patient))->toBeTrue();
    expect($this->patientUser->can('view', $this->patient))->toBeTrue();
    expect($this->otherPatientUser->can('view', $this->patient))->toBeFalse();
});

it('lets admins and the assigned doctor view an appointment, denies other doctors and the unassigned patient', function () {
    expect($this->admin->can('view', $this->appointment))->toBeTrue();
    expect($this->doctorUser->can('view', $this->appointment))->toBeTrue();
    expect($this->otherDoctorUser->can('view', $this->appointment))->toBeFalse();
    expect($this->patientUser->can('view', $this->appointment))->toBeTrue();
    expect($this->otherPatientUser->can('view', $this->appointment))->toBeFalse();
});

it('lets the assigned doctor and admin cancel an appointment, denies other doctors and unassigned patients', function () {
    expect($this->admin->can('cancel', $this->appointment))->toBeTrue();
    expect($this->doctorUser->can('cancel', $this->appointment))->toBeTrue();
    expect($this->otherDoctorUser->can('cancel', $this->appointment))->toBeFalse();
    expect($this->patientUser->can('cancel', $this->appointment))->toBeTrue();
    expect($this->otherPatientUser->can('cancel', $this->appointment))->toBeFalse();
});
