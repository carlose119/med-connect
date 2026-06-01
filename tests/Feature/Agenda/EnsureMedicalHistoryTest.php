<?php

use App\Actions\BookAppointmentAction;
use App\Actions\EnsureMedicalHistoryAction;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\MedicalHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->doctorUser = User::factory()->doctor()->create();
    $this->doctor = Doctor::factory()->for($this->doctorUser)->create();

    $this->patientUser = User::factory()->patient()->create();
    $this->patient = \App\Models\Patient::factory()->for($this->patientUser)->create();
});

/**
 * PR 4 unit-ish (DB-backed) tests for EnsureMedicalHistoryAction.
 *
 * The action is also exercised end-to-end by every successful
 * `BookAppointmentAction` call (the booking pipeline invokes it
 * after the INSERT). These tests assert the helper's behaviour
 * directly: idempotency on a second call.
 */
it('creates a medical history for a first-time patient', function () {
    expect(MedicalHistory::where('patient_id', $this->patient->id)->exists())->toBeFalse();

    $history = app(EnsureMedicalHistoryAction::class)($this->patient->id);

    expect($history)->toBeInstanceOf(MedicalHistory::class)
        ->and($history->patient_id)->toBe($this->patient->id)
        ->and(MedicalHistory::where('patient_id', $this->patient->id)->count())->toBe(1);
});

it('does not create a duplicate history when called a second time for the same patient', function () {
    $first = app(EnsureMedicalHistoryAction::class)($this->patient->id);
    $second = app(EnsureMedicalHistoryAction::class)($this->patient->id);

    expect($second->id)->toBe($first->id)
        ->and(MedicalHistory::where('patient_id', $this->patient->id)->count())->toBe(1);
});

it('is called exactly once per successful booking, even across multiple bookings', function () {
    $targetDate = now()->addDays(5)->startOfDay();
    $dayOfWeek = (int) $targetDate->copy()->dayOfWeekIso;

    DoctorSchedule::factory()->for($this->doctor)->create([
        'day_of_week' => $dayOfWeek,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'slot_duration_minutes' => 30,
        'is_active' => true,
    ]);

    $action = app(BookAppointmentAction::class);

    // First booking — creates the history.
    $action($this->doctor->id, $targetDate->copy()->setTime(9, 0), $this->patient->id);
    expect(MedicalHistory::where('patient_id', $this->patient->id)->count())->toBe(1);

    // Second booking — reuses the existing history (no new row).
    $action($this->doctor->id, $targetDate->copy()->setTime(9, 30), $this->patient->id);
    expect(MedicalHistory::where('patient_id', $this->patient->id)->count())->toBe(1);
});
