<?php

use App\Actions\BookAppointmentAction;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\User;
use App\States\Appointment\Pending;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->doctorUser = User::factory()->doctor()->create();
    $this->doctor = Doctor::factory()->for($this->doctorUser)->create();

    $this->patientUser = User::factory()->patient()->create();
    $this->patient = Patient::factory()->for($this->patientUser)->create();
});

/**
 * PR 4 RED: the action class does not exist yet. Asserting the happy
 * path exercises the full pipeline: lockForUpdate, slot lookup, 2h
 * anticipación guard, patient-overlap guard, INSERT, history ensure.
 * Once the action lands (next commit), this test must pass.
 */
it('booking creates a pending appointment for a published slot in the future', function () {
    $targetDate = now()->addDays(5)->startOfDay();
    $dayOfWeek = (int) $targetDate->copy()->dayOfWeekIso;

    DoctorSchedule::factory()->for($this->doctor)->create([
        'day_of_week' => $dayOfWeek,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'slot_duration_minutes' => 30,
        'is_active' => true,
    ]);

    $start = $targetDate->copy()->setTime(10, 0);

    $action = app(BookAppointmentAction::class);
    $appt = $action($this->doctor->id, $start, $this->patient->id);

    expect($appt)->toBeInstanceOf(Appointment::class)
        ->and($appt->state)->toBeInstanceOf(Pending::class)
        ->and($appt->doctor_id)->toBe($this->doctor->id)
        ->and($appt->patient_id)->toBe($this->patient->id)
        ->and($appt->start_time->equalTo($start->copy()->setTimezone('UTC')))->toBeTrue();
});
