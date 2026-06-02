<?php

namespace Tests\Support;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Specialty;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\DoctorScheduleFactory;
use Illuminate\Support\Facades\DB;

/**
 * Test fixture: builds a doctor (User + Doctor profile + a published
 * DoctorSchedule for the test's day-of-week + a Sanctum token) in one
 * call. The schedule is generated for the day-of-week matching the
 * supplied Carbon date (defaults to "now"), so the booking tests can
 * compute a valid `start_time` for the same day and the slot lookup
 * in `DoctorAvailabilityService` will return it.
 *
 * Returns the trio so callers can destructure:
 *   [$user, $doctor, $token] = $this->createDoctorWithToken();
 *
 * For PR 2 we only need the schedule; the schedule side of the trait
 * is the seam PR 3 will reuse when /api/doctors/{id}/slots lands.
 */
trait CreatesDoctors
{
    public function createDoctorWithToken(?CarbonImmutable $scheduleDay = null): array
    {
        $user = User::factory()->doctor()->create();

        $specialty = Specialty::firstOrCreate(
            ['slug' => 'general-medicine'],
            ['name' => 'General Medicine', 'is_active' => true],
        );

        $doctor = Doctor::factory()->for($user)->for($specialty)->create();

        // Default to "today" in the local timezone so the day-of-week
        // matches the Carbon date the test will use for `start_time`.
        $scheduleDay ??= CarbonImmutable::now();
        $dayOfWeek = (int) $scheduleDay->dayOfWeekIso;

        // Build the schedule with a known slot at 10:00 local. Use the
        // factory's "for($doctor)" so the doctor_id is wired up.
        DoctorScheduleFactory::new()
            ->for($doctor)
            ->create([
                'day_of_week' => $dayOfWeek,
                'start_time' => '09:00:00',
                'end_time' => '12:00:00',
                'slot_duration_minutes' => 30,
                'is_active' => true,
            ]);

        $token = $user->createToken('test')->plainTextToken;

        // Suppress unused-import warning when the trait is the only
        // consumer of `DB` (it pulls in `Database\Factories\DoctorScheduleFactory`
        // via the `use` statement and resolves the FK through the
        // factory, so no direct DB call is needed here).
        unset(DB::class);

        return [$user, $doctor, $token];
    }
}
