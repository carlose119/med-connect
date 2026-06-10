<?php

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\User;
use App\Policies\DoctorSchedulePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Gate::policy(DoctorSchedule::class, DoctorSchedulePolicy::class);

    $this->admin = User::factory()->admin()->create();

    // Doctor A: owns schedule A
    $this->doctorAUser = User::factory()->doctor()->create();
    $this->doctorA = Doctor::factory()->for($this->doctorAUser)->create();
    $this->scheduleA = DoctorSchedule::factory()
        ->for($this->doctorA)
        ->create(['doctor_id' => $this->doctorA->id]);

    // Doctor B: owns schedule B
    $this->doctorBUser = User::factory()->doctor()->create();
    $this->doctorB = Doctor::factory()->for($this->doctorBUser)->create();
    $this->scheduleB = DoctorSchedule::factory()
        ->for($this->doctorB)
        ->create(['doctor_id' => $this->doctorB->id]);
});

// ─── viewAny() ─────────────────────────────────────────────────────

it('lets anyone view any schedule list', function (): void {
    expect($this->doctorAUser->can('viewAny', DoctorSchedule::class))->toBeTrue();
    expect($this->doctorBUser->can('viewAny', DoctorSchedule::class))->toBeTrue();
    expect($this->admin->can('viewAny', DoctorSchedule::class))->toBeTrue();
});

// ─── view() ────────────────────────────────────────────────────────

it('lets a doctor view their own schedule', function (): void {
    expect($this->doctorAUser->can('view', $this->scheduleA))->toBeTrue();
});

it('denies a doctor from viewing another doctors schedule', function (): void {
    expect($this->doctorBUser->can('view', $this->scheduleA))->toBeFalse();
});

it('lets an admin view any schedule', function (): void {
    expect($this->admin->can('view', $this->scheduleA))->toBeTrue();
    expect($this->admin->can('view', $this->scheduleB))->toBeTrue();
});

// ─── create() ──────────────────────────────────────────────────────

it('lets any authenticated user create a schedule', function (): void {
    expect($this->doctorAUser->can('create', DoctorSchedule::class))->toBeTrue();
    expect($this->doctorBUser->can('create', DoctorSchedule::class))->toBeTrue();
    expect($this->admin->can('create', DoctorSchedule::class))->toBeTrue();
});

// ─── update() ──────────────────────────────────────────────────────

it('lets a doctor update their own schedule', function (): void {
    expect($this->doctorAUser->can('update', $this->scheduleA))->toBeTrue();
});

it('denies a doctor from updating another doctors schedule', function (): void {
    expect($this->doctorBUser->can('update', $this->scheduleA))->toBeFalse();
});

it('lets an admin update any schedule', function (): void {
    expect($this->admin->can('update', $this->scheduleA))->toBeTrue();
});

// ─── delete() ──────────────────────────────────────────────────────

it('lets a doctor delete their own schedule', function (): void {
    expect($this->doctorAUser->can('delete', $this->scheduleA))->toBeTrue();
});

it('denies a doctor from deleting another doctors schedule', function (): void {
    expect($this->doctorBUser->can('delete', $this->scheduleA))->toBeFalse();
});

it('lets an admin delete any schedule', function (): void {
    expect($this->admin->can('delete', $this->scheduleA))->toBeTrue();
});
