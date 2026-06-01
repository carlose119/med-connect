<?php

namespace App\Actions;

use App\Exceptions\Domain\AnticipationWindowViolationException;
use App\Exceptions\Domain\PatientOverlapException;
use App\Exceptions\Domain\SlotNotAvailableException;
use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Models\MedicalHistory;
use App\Services\DoctorAvailabilityService;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrator for the booking write path. Validates the request, opens
 * a transaction, takes a row lock on the doctor's schedule for the
 * slot's day-of-week, and persists the appointment plus the patient's
 * medical history in a single atomic unit.
 *
 * Three guards, applied in this order so the most expensive check
 * (slot lookup) runs first and the cheaper ones catch obvious errors
 * earlier:
 *
 *   1. Slot is in the published schedule (and not already booked).
 *   2. Start is at least 2 hours in the future (anticipación).
 *   3. Patient has no overlapping non-cancelled appointment.
 *
 * If all three pass, the appointment is INSERTed with `state = pending`.
 * The DB unique index `(doctor_id, start_time, cancelled_marker)` is
 * the second line of defense behind `lockForUpdate()`: if a race
 * somehow slipped past the row lock, the INSERT raises a `QueryException`
 * with "Duplicate entry" / "uniq_doctor_start_not_cancelled" and we
 * re-throw as `SlotNotAvailableException` so the spec's "double-book
 * rejected" scenario holds for both the application lock and the
 * persistence contract.
 *
 * The medical history is created in the same transaction so a first-time
 * patient always has a history by the time the appointment is committed.
 */
class BookAppointmentAction
{
    public function __construct(
        private readonly DoctorAvailabilityService $availability,
    ) {}

    public function __invoke(
        int $doctorId,
        CarbonInterface $start,
        int $patientId,
        ?string $notes = null,
    ): Appointment {
        return DB::transaction(function () use ($doctorId, $start, $patientId, $notes) {
            $tz = config('app.timezone');
            $localStart = $start->copy()->setTimezone($tz);
            $dayOfWeek = (int) $localStart->dayOfWeekIso;

            // 0. lockForUpdate() on the doctor's recurring rules for the
            //    slot's day-of-week. Per design Decision 4, the lock is
            //    on `doctor_schedules`, NOT on `appointments`: the
            //    appointment row does not exist yet. The schedule row
            //    serialises every concurrent booking for the same
            //    doctor on the same day-of-week.
            DoctorSchedule::query()
                ->where('doctor_id', $doctorId)
                ->where('day_of_week', $dayOfWeek)
                ->lockForUpdate()
                ->get();

            // 1st guard: slot must be in the published list. The
            //    service's slot list already excludes booked slots and
            //    past slots, so a positive match means the slot is
            //    free and ≥ 2h in the future.
            $startUtc = $start->copy()->setTimezone('UTC');
            $availableSlots = $this->availability->slots($doctorId, $start);
            $matches = collect($availableSlots)->contains(
                fn (array $slot) => $slot['start']->equalTo($startUtc->toImmutable())
            );

            if (! $matches) {
                throw new SlotNotAvailableException(sprintf(
                    'The requested slot at %s is not in the published schedule or is already booked.',
                    $start->toIso8601String(),
                ));
            }

            // 2nd guard: 2h anticipación. The service's filter
            //    (`start > now() + 2h`) already removed past slots from
            //    the list, but a stale list could let a near-future
            //    slot through — re-check here.
            if ($start->lt(now()->addHours(2))) {
                throw new AnticipationWindowViolationException(sprintf(
                    'Bookings require at least 2 hours of anticipación. Requested: %s.',
                    $start->toIso8601String(),
                ));
            }

            // 3rd guard: patient overlap. We need the appointment's
            //    end_time to check the [start, end) intersect, so we
            //    compute the duration from the doctor's schedule first.
            $rule = DoctorSchedule::query()
                ->where('doctor_id', $doctorId)
                ->where('day_of_week', $dayOfWeek)
                ->where('is_active', true)
                ->first();
            $slotMinutes = (int) ($rule?->slot_duration_minutes ?? 30);
            $endUtc = $startUtc->copy()->addMinutes($slotMinutes);

            $overlap = Appointment::query()
                ->where('patient_id', $patientId)
                ->whereIn('state', ['pending', 'confirmed', 'completed', 'no_show'])
                ->where('start_time', '<', $endUtc)
                ->where('end_time', '>', $startUtc)
                ->exists();

            if ($overlap) {
                throw new PatientOverlapException(sprintf(
                    'Patient %d already has a non-cancelled appointment overlapping %s.',
                    $patientId,
                    $start->toIso8601String(),
                ));
            }

            // 4. INSERT. Catch the unique-index violation and re-throw
            //    as a domain exception so the spec's "persistence
            //    rejects the write" scenario maps to SlotNotAvailable.
            try {
                $appointment = Appointment::create([
                    'doctor_id' => $doctorId,
                    'patient_id' => $patientId,
                    'start_time' => $startUtc,
                    'end_time' => $endUtc,
                    'state' => 'pending',
                    'cancellation_reason' => null,
                    'notes' => $notes,
                ]);
            } catch (QueryException $e) {
                $message = $e->getMessage();
                $isDuplicate = str_contains($message, 'Duplicate')
                    || str_contains($message, 'uniq_doctor_start_not_cancelled')
                    || $e->getCode() === '23000';

                if ($isDuplicate) {
                    throw new SlotNotAvailableException(
                        'Slot already booked (unique index violation on appointments).',
                        previous: $e,
                    );
                }

                throw $e;
            }

            // 5. Medical history: idempotent firstOrCreate on
            //    (patient_id). Inside the same transaction so the
            //    first-time patient always has a history committed with
            //    the appointment. The helper action lands in the next
            //    commit; for now we inline the firstOrCreate so this
            //    commit's work unit (the booking pipeline) is
            //    self-contained.
            MedicalHistory::firstOrCreate(
                ['patient_id' => $patientId],
                ['opened_at' => now()],
            );

            return $appointment->refresh();
        });
    }
}
