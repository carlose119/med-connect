<?php

use App\Actions\BookAppointmentAction;
use App\Exceptions\Domain\AnticipationWindowViolationException;
use App\Exceptions\Domain\PatientOverlapException;
use App\Exceptions\Domain\SlotNotAvailableException;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\User;
use App\Services\DoctorAvailabilityService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->doctorUser = User::factory()->doctor()->create();
    $this->doctor = Doctor::factory()->for($this->doctorUser)->create();

    $this->patientUser = User::factory()->patient()->create();
    $this->patient = Patient::factory()->for($this->patientUser)->create();
});

/**
 * PR 4 negative-path tests for BookAppointmentAction. The happy path is
 * covered in BookAppointmentTest. Each failure path maps to one of the
 * three domain exceptions and exercises one of the three guards in
 * the action:
 *
 *   - 1st guard (slot existence / not-booked) → SlotNotAvailableException
 *   - 2nd guard (2h anticipación)             → AnticipationWindowViolationException
 *   - 3rd guard (no patient overlap)         → PatientOverlapException
 */
it('rejects a double-book on the same (doctor, start_time) with SlotNotAvailableException', function () {
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

    $action($this->doctor->id, $start, $this->patient->id);

    expect(fn () => $action($this->doctor->id, $start, $this->patient->id))
        ->toThrow(SlotNotAvailableException::class);
});

it('rejects a booking inside the 2h anticipación window with AnticipationWindowViolationException', function () {
    // The 2nd guard is defense-in-depth: by design, the slot
    // service already filters past slots, so a request inside the
    // 2h window is normally caught by the 1st guard (with 409). To
    // exercise the 2nd guard's specific exception class, we bind a
    // fake service that returns a slot inside the 2h window — the
    // request then passes the 1st guard and falls through to the
    // anticipación check.
    $today = now()->startOfDay();
    $dayOfWeek = (int) $today->copy()->dayOfWeekIso;

    DoctorSchedule::factory()->for($this->doctor)->create([
        'day_of_week' => $dayOfWeek,
        'start_time' => '00:00:00',
        'end_time' => '23:59:00',
        'slot_duration_minutes' => 30,
        'is_active' => true,
    ]);

    $start = now()->addMinutes(90)->startOfMinute();
    $startUtc = $start->copy()->setTimezone('UTC');

    // Subclass the real service and override `slots()` to return the
    // requested slot even though it is inside the 2h window. This
    // exercises the action's 2nd guard (defense-in-depth) in isolation.
    $fake = new class($startUtc) extends DoctorAvailabilityService
    {
        public function __construct(private readonly CarbonInterface $slot) {}

        public function slots(int $doctorId, CarbonInterface $date, ?string $tz = null): array
        {
            return [[
                'start' => $this->slot->copy()->toImmutable(),
                'end' => $this->slot->copy()->addMinutes(30)->toImmutable(),
            ]];
        }
    };

    app()->instance(DoctorAvailabilityService::class, $fake);

    $action = app(BookAppointmentAction::class);

    expect(fn () => $action($this->doctor->id, $start, $this->patient->id))
        ->toThrow(AnticipationWindowViolationException::class);
});

it('rejects a booking when the patient has an overlapping non-cancelled appointment with PatientOverlapException', function () {
    $targetDate = now()->addDays(5)->startOfDay();
    $dayOfWeek = (int) $targetDate->copy()->dayOfWeekIso;

    // Doctor 1 publishes a slot for the target day.
    DoctorSchedule::factory()->for($this->doctor)->create([
        'day_of_week' => $dayOfWeek,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'slot_duration_minutes' => 30,
        'is_active' => true,
    ]);

    // Doctor 2 — different doctor, same target day, separate schedule.
    $otherDoctorUser = User::factory()->doctor()->create();
    $otherDoctor = Doctor::factory()->for($otherDoctorUser)->create();
    DoctorSchedule::factory()->for($otherDoctor)->create([
        'day_of_week' => $dayOfWeek,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'slot_duration_minutes' => 30,
        'is_active' => true,
    ]);

    // The patient already has a non-cancelled appointment on doctor 1
    // at the same time the patient is now trying to book doctor 2.
    $overlapStart = $targetDate->copy()->setTime(10, 0);
    Appointment::factory()
        ->for($this->doctor)
        ->for($this->patient)
        ->create([
            'start_time' => $overlapStart->format('Y-m-d H:i:s'),
            'end_time' => $overlapStart->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
            'state' => 'confirmed',
        ]);

    $action = app(BookAppointmentAction::class);

    expect(fn () => $action($otherDoctor->id, $overlapStart, $this->patient->id))
        ->toThrow(PatientOverlapException::class);
});
