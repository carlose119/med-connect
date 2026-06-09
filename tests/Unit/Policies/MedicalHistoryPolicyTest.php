<?php

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\User;
use App\Policies\MedicalHistoryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * PR 1 — clinical-records — MedicalHistoryPolicy.
 *
 * RED at this commit: the MedicalHistoryPolicy does not exist yet.
 * All tests fail until Task 1.3 creates the policy class.
 *
 * view(): admin → true, self patient → true, doctor with appointment → true,
 *         other patient → false, doctor without appointment → false.
 *
 * createNote(): doctor with appointment → true,
 *               doctor without appointment → false,
 *               patient → false, admin → false.
 */
beforeEach(function (): void {
    Gate::policy(MedicalHistory::class, MedicalHistoryPolicy::class);

    $this->admin = User::factory()->admin()->create();

    // Patient A: owns history H1
    $this->patientAUser = User::factory()->patient()->create();
    $this->patientA = Patient::factory()->for($this->patientAUser)->create();
    $this->historyA = MedicalHistory::factory()->for($this->patientA)->create();

    // Patient B: owns history H2
    $this->patientBUser = User::factory()->patient()->create();
    $this->patientB = Patient::factory()->for($this->patientBUser)->create();
    $this->historyB = MedicalHistory::factory()->for($this->patientB)->create();

    // Doctor X: has completed appointment with Patient A
    $this->doctorXUser = User::factory()->doctor()->create();
    $this->doctorX = Doctor::factory()->for($this->doctorXUser)->create();
    Appointment::factory()
        ->for($this->doctorX)
        ->for($this->patientA)
        ->create(['state' => 'completed']);

    // Doctor Y: has no appointments
    $this->doctorYUser = User::factory()->doctor()->create();
    $this->doctorY = Doctor::factory()->for($this->doctorYUser)->create();
});

// ─── view() ────────────────────────────────────────────────────────

it('lets admins view any medical history', function (): void {
    expect($this->admin->can('view', $this->historyA))->toBeTrue();
});

it('lets the patient who owns the history view it', function (): void {
    expect($this->patientAUser->can('view', $this->historyA))->toBeTrue();
});

it('denies another patient from viewing a history they do not own', function (): void {
    expect($this->patientBUser->can('view', $this->historyA))->toBeFalse();
});

it('lets a doctor with an appointment view the patient history', function (): void {
    expect($this->doctorXUser->can('view', $this->historyA))->toBeTrue();
});

it('denies a doctor without an appointment from viewing the history', function (): void {
    expect($this->doctorYUser->can('view', $this->historyA))->toBeFalse();
});

// ─── createNote() ──────────────────────────────────────────────────

it('lets a doctor with an appointment create a note on the history', function (): void {
    expect($this->doctorXUser->can('createNote', $this->historyA))->toBeTrue();
});

it('denies a doctor without an appointment from creating a note', function (): void {
    expect($this->doctorYUser->can('createNote', $this->historyA))->toBeFalse();
});

it('denies a patient from creating a note', function (): void {
    expect($this->patientAUser->can('createNote', $this->historyA))->toBeFalse();
});

it('denies an admin from creating a note', function (): void {
    expect($this->admin->can('createNote', $this->historyA))->toBeFalse();
});
