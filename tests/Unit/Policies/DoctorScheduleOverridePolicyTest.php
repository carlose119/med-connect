<?php

use App\Models\Doctor;
use App\Models\DoctorScheduleOverride;
use App\Models\User;
use App\Policies\DoctorScheduleOverridePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Gate::policy(DoctorScheduleOverride::class, DoctorScheduleOverridePolicy::class);

    $this->admin = User::factory()->admin()->create();

    // Doctor A: owns override A
    $this->doctorAUser = User::factory()->doctor()->create();
    $this->doctorA = Doctor::factory()->for($this->doctorAUser)->create();
    $this->overrideA = DoctorScheduleOverride::factory()
        ->for($this->doctorA)
        ->create(['doctor_id' => $this->doctorA->id]);

    // Doctor B: owns override B
    $this->doctorBUser = User::factory()->doctor()->create();
    $this->doctorB = Doctor::factory()->for($this->doctorBUser)->create();
    $this->overrideB = DoctorScheduleOverride::factory()
        ->for($this->doctorB)
        ->create(['doctor_id' => $this->doctorB->id]);
});

// ─── viewAny() ─────────────────────────────────────────────────────

it('lets anyone view any override list', function (): void {
    expect($this->doctorAUser->can('viewAny', DoctorScheduleOverride::class))->toBeTrue();
    expect($this->doctorBUser->can('viewAny', DoctorScheduleOverride::class))->toBeTrue();
    expect($this->admin->can('viewAny', DoctorScheduleOverride::class))->toBeTrue();
});

// ─── view() ────────────────────────────────────────────────────────

it('lets a doctor view their own override', function (): void {
    expect($this->doctorAUser->can('view', $this->overrideA))->toBeTrue();
});

it('denies a doctor from viewing another doctors override', function (): void {
    expect($this->doctorBUser->can('view', $this->overrideA))->toBeFalse();
});

it('lets an admin view any override', function (): void {
    expect($this->admin->can('view', $this->overrideA))->toBeTrue();
    expect($this->admin->can('view', $this->overrideB))->toBeTrue();
});

// ─── create() ──────────────────────────────────────────────────────

it('lets any authenticated user create an override', function (): void {
    expect($this->doctorAUser->can('create', DoctorScheduleOverride::class))->toBeTrue();
    expect($this->doctorBUser->can('create', DoctorScheduleOverride::class))->toBeTrue();
    expect($this->admin->can('create', DoctorScheduleOverride::class))->toBeTrue();
});

// ─── update() ──────────────────────────────────────────────────────

it('lets a doctor update their own override', function (): void {
    expect($this->doctorAUser->can('update', $this->overrideA))->toBeTrue();
});

it('denies a doctor from updating another doctors override', function (): void {
    expect($this->doctorBUser->can('update', $this->overrideA))->toBeFalse();
});

it('lets an admin update any override', function (): void {
    expect($this->admin->can('update', $this->overrideA))->toBeTrue();
});

// ─── delete() ──────────────────────────────────────────────────────

it('lets a doctor delete their own override', function (): void {
    expect($this->doctorAUser->can('delete', $this->overrideA))->toBeTrue();
});

it('denies a doctor from deleting another doctors override', function (): void {
    expect($this->doctorBUser->can('delete', $this->overrideA))->toBeFalse();
});

it('lets an admin delete any override', function (): void {
    expect($this->admin->can('delete', $this->overrideA))->toBeTrue();
});
