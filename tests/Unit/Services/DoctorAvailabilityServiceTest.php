<?php

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\DoctorScheduleOverride;
use App\Models\User;
use App\Services\DoctorAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * PR 4 unit tests for the pure slot generator.
 *
 * The service is a pure function of (doctorId, date, tz) → array of slots.
 * It reads from the DB but does not write. The anticipación filter
 * (`start > now() + 2h`) means the target date must be at least 2 hours
 * in the future, so we use `now()->addDays(5)` throughout to make the
 * tests stable across hour boundaries.
 *
 * Timezone: the default tz is `config('app.timezone')` which is `UTC`
 * in the test env. Slots are therefore expected in UTC wall-clock time.
 */
beforeEach(function () {
    $this->doctorUser = User::factory()->doctor()->create();
    $this->doctor = Doctor::factory()->for($this->doctorUser)->create();

    // Target date 5 days from now, on the dot, in the test's local tz.
    $this->targetDate = now()->addDays(5)->startOfDay();
    $this->dayOfWeek = (int) $this->targetDate->copy()->dayOfWeekIso;

    $this->service = app(DoctorAvailabilityService::class);
});

it('returns all slots from a recurring rule when there are no overrides or booked appointments', function () {
    DoctorSchedule::factory()->for($this->doctor)->create([
        'day_of_week' => $this->dayOfWeek,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'slot_duration_minutes' => 30,
        'is_active' => true,
    ]);

    $slots = $this->service->slots($this->doctor->id, $this->targetDate);

    // 6 slots: 09:00, 09:30, 10:00, 10:30, 11:00, 11:30.
    expect($slots)->toHaveCount(6);

    $expectedHours = ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30'];
    foreach ($slots as $i => $slot) {
        expect($slot['start']->format('H:i'))->toBe($expectedHours[$i]);
        expect($slot['end']->format('H:i'))->toBe(
            \Illuminate\Support\Carbon::createFromFormat('H:i', $expectedHours[$i])
                ->addMinutes(30)->format('H:i')
        );
    }
});

it('returns an empty list when a full-day block override covers the entire day', function () {
    DoctorSchedule::factory()->for($this->doctor)->create([
        'day_of_week' => $this->dayOfWeek,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'slot_duration_minutes' => 30,
        'is_active' => true,
    ]);

    DoctorScheduleOverride::factory()
        ->for($this->doctor)
        ->fullDay()
        ->create([
            'date' => $this->targetDate->toDateString(),
        ]);

    $slots = $this->service->slots($this->doctor->id, $this->targetDate);

    expect($slots)->toBeEmpty();
});

it('adds slots from an extra_availability override on top of the recurring rule', function () {
    // Recurring rule only publishes 09:00 and 09:30 on this day.
    DoctorSchedule::factory()->for($this->doctor)->create([
        'day_of_week' => $this->dayOfWeek,
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'slot_duration_minutes' => 30,
        'is_active' => true,
    ]);

    // Override adds two slots at 11:00 and 11:30.
    DoctorScheduleOverride::factory()
        ->for($this->doctor)
        ->extraAvailability()
        ->create([
            'date' => $this->targetDate->toDateString(),
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
        ]);

    $slots = $this->service->slots($this->doctor->id, $this->targetDate);

    $slotStarts = array_map(fn ($s) => $s['start']->format('H:i'), $slots);
    expect($slotStarts)->toContain('09:00')
        ->and($slotStarts)->toContain('09:30')
        ->and($slotStarts)->toContain('11:00')
        ->and($slotStarts)->toContain('11:30');
});

it('omits a slot that is already covered by a non-cancelled booked appointment', function () {
    DoctorSchedule::factory()->for($this->doctor)->create([
        'day_of_week' => $this->dayOfWeek,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'slot_duration_minutes' => 30,
        'is_active' => true,
    ]);

    $patientUser = User::factory()->patient()->create();
    $patient = \App\Models\Patient::factory()->for($patientUser)->create();

    // Book the 10:00 slot. The service should drop that one slot.
    $bookedStart = $this->targetDate->copy()->setTime(10, 0);
    \App\Models\Appointment::factory()
        ->for($this->doctor)
        ->for($patient)
        ->create([
            'start_time' => $bookedStart->format('Y-m-d H:i:s'),
            'end_time' => $bookedStart->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
            'state' => 'pending',
        ]);

    $slots = $this->service->slots($this->doctor->id, $this->targetDate);

    $slotStarts = array_map(fn ($s) => $s['start']->format('H:i'), $slots);
    expect($slotStarts)->not->toContain('10:00')
        ->and($slotStarts)->toHaveCount(5); // 6 - 1 = 5
});
