<?php

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Policies\PrescriptionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Gate::policy(Prescription::class, PrescriptionPolicy::class);

    $this->admin = User::factory()->admin()->create();

    // Doctor A owns prescription A
    $this->doctorAUser = User::factory()->doctor()->create();
    $this->doctorA = Doctor::factory()->for($this->doctorAUser)->create();
    $this->patientAUser = User::factory()->patient()->create();
    $this->patientA = Patient::factory()->for($this->patientAUser)->create();
    $this->prescriptionA = Prescription::factory()->create([
        'doctor_id' => $this->doctorA->id,
        'patient_id' => $this->patientA->id,
    ]);

    // Doctor B owns prescription B
    $this->doctorBUser = User::factory()->doctor()->create();
    $this->doctorB = Doctor::factory()->for($this->doctorBUser)->create();
    $this->patientBUser = User::factory()->patient()->create();
    $this->patientB = Patient::factory()->for($this->patientBUser)->create();
    $this->prescriptionB = Prescription::factory()->create([
        'doctor_id' => $this->doctorB->id,
        'patient_id' => $this->patientB->id,
    ]);

    // Patient C (no prescription)
    $this->patientCUser = User::factory()->patient()->create();
    $this->patientC = Patient::factory()->for($this->patientCUser)->create();
});

// ─── viewAny() ─────────────────────────────────────────────────────

it('lets a doctor view the prescriptions list', function (): void {
    expect($this->doctorAUser->can('viewAny', Prescription::class))->toBeTrue();
});

it('lets a patient view the prescriptions list', function (): void {
    expect($this->patientAUser->can('viewAny', Prescription::class))->toBeTrue();
});

it('lets an admin view the prescriptions list', function (): void {
    expect($this->admin->can('viewAny', Prescription::class))->toBeTrue();
});

// ─── view() ────────────────────────────────────────────────────────

it('lets a doctor view their own prescription', function (): void {
    expect($this->doctorAUser->can('view', $this->prescriptionA))->toBeTrue();
});

it('lets a patient view their own prescription', function (): void {
    expect($this->patientAUser->can('view', $this->prescriptionA))->toBeTrue();
});

it('denies a doctor from viewing another doctors prescription', function (): void {
    expect($this->doctorBUser->can('view', $this->prescriptionA))->toBeFalse();
});

it('lets an admin view any prescription', function (): void {
    expect($this->admin->can('view', $this->prescriptionA))->toBeTrue();
    expect($this->admin->can('view', $this->prescriptionB))->toBeTrue();
});

// ─── create() ──────────────────────────────────────────────────────

it('lets any authenticated user create a prescription', function (): void {
    expect($this->doctorAUser->can('create', Prescription::class))->toBeTrue();
    expect($this->admin->can('create', Prescription::class))->toBeTrue();
});

// ─── update() ──────────────────────────────────────────────────────

it('lets a doctor update their own prescription', function (): void {
    expect($this->doctorAUser->can('update', $this->prescriptionA))->toBeTrue();
});

it('denies a doctor from updating another doctors prescription', function (): void {
    expect($this->doctorBUser->can('update', $this->prescriptionA))->toBeFalse();
});

it('lets an admin update any prescription', function (): void {
    expect($this->admin->can('update', $this->prescriptionA))->toBeTrue();
});

// ─── delete() ──────────────────────────────────────────────────────

it('denies delete on any prescription regardless of role', function (): void {
    expect($this->doctorAUser->can('delete', $this->prescriptionA))->toBeFalse();
    expect($this->admin->can('delete', $this->prescriptionA))->toBeFalse();
});