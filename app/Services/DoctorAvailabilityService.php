<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Models\DoctorScheduleOverride;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Pure slot generator. Given a doctor, a target date, and an optional
 * display timezone, computes the list of available appointment slots by
 * combining the doctor's recurring weekly rules with any same-day
 * overrides, then subtracting the slots already consumed by non-cancelled
 * appointments.
 *
 * Pure: no DB writes, no `auth()->user()`. The only side effect is
 * reads from the three tables (doctor_schedules, doctor_schedule_overrides,
 * appointments). The anticipación filter (`start > now() + 2h`) is
 * applied last so the action layer (which composes this service) can
 * also enforce it as a second guard.
 *
 * Timezone: the recurring rules and the override times are stored as
 * wall-clock time without a timezone, conventionally the consultorio
 * local time. The `$tz` argument is the timezone the caller wants the
 * result in (default = `config('app.timezone')`, which is `UTC` in the
 * test env). Internally we compute slots in that local timezone then
 * convert each slot's boundaries to UTC for storage-comparable output.
 */
class DoctorAvailabilityService
{
    /**
     * @return array<int, array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    public function slots(int $doctorId, CarbonInterface $date, ?string $tz = null): array
    {
        $tz = $tz ?? config('app.timezone');

        // Resolve the target date's local start-of-day in $tz, then the
        // ISO day-of-week for the recurring-rule lookup. The local date
        // is the wall-clock day in the consultorio's timezone.
        $localDate = $date->copy()->setTimezone($tz)->startOfDay();
        $dayOfWeek = (int) $localDate->dayOfWeekIso;

        $dayStartUtc = $localDate->copy()->setTimezone('UTC');
        $dayEndUtc = $dayStartUtc->copy()->addDay();

        // 1. Active recurring rules for the day-of-week.
        $rules = DoctorSchedule::query()
            ->where('doctor_id', $doctorId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->get();

        // 2. Same-day overrides.
        $overrides = DoctorScheduleOverride::query()
            ->where('doctor_id', $doctorId)
            ->whereDate('date', $localDate->toDateString())
            ->get();

        $slots = collect();

        // 3. Slice each recurring rule into slots in local tz, then
        // convert each slot's [start, end) to UTC.
        foreach ($rules as $rule) {
            $cursor = $localDate->copy()->setTimeFromTimeString((string) $rule->start_time);
            $ruleEnd = $localDate->copy()->setTimeFromTimeString((string) $rule->end_time);
            $duration = (int) $rule->slot_duration_minutes;

            while ($cursor->lt($ruleEnd)) {
                $slots->push([
                    'start' => $cursor->copy()->setTimezone('UTC')->toImmutable(),
                    'end' => $cursor->copy()->addMinutes($duration)->setTimezone('UTC')->toImmutable(),
                ]);
                $cursor = $cursor->copy()->addMinutes($duration);
            }
        }

        // 4. Subtract slots covered by `block` overrides. A block with
        //    null start/end is a full-day block; otherwise it's a range
        //    block in local time. We convert the block to UTC before the
        //    overlap test so the slot's UTC boundaries can be compared
        //    directly.
        $blockOverrides = $overrides->where('type', 'block');

        if ($blockOverrides->isNotEmpty()) {
            $slots = $slots->reject(function (array $slot) use ($blockOverrides, $localDate) {
                foreach ($blockOverrides as $block) {
                    if ($this->isSlotInBlock($slot, $block, $localDate)) {
                        return true;
                    }
                }

                return false;
            })->values();
        }

        // 5. Add slots from `extra_availability` overrides. Each
        //    override's [start, end) is sliced into 30-minute slots by
        //    default (matches the recurring-rule granularity; the spec
        //    does not give overrides their own duration).
        $extraOverrides = $overrides->where('type', 'extra_availability');

        foreach ($extraOverrides as $extra) {
            $cursor = $localDate->copy()->setTimeFromTimeString((string) $extra->start_time);
            $extraEnd = $localDate->copy()->setTimeFromTimeString((string) $extra->end_time);
            $duration = 30;

            while ($cursor->lt($extraEnd)) {
                $slots->push([
                    'start' => $cursor->copy()->setTimezone('UTC')->toImmutable(),
                    'end' => $cursor->copy()->addMinutes($duration)->setTimezone('UTC')->toImmutable(),
                ]);
                $cursor = $cursor->copy()->addMinutes($duration);
            }
        }

        // 6. Subtract slots already consumed by non-cancelled
        //    appointments on the same day. We do the overlap test in
        //    UTC because the appointments table stores UTC.
        $booked = Appointment::query()
            ->where('doctor_id', $doctorId)
            ->whereIn('state', ['pending', 'confirmed', 'completed', 'no_show'])
            ->where('start_time', '>=', $dayStartUtc)
            ->where('start_time', '<', $dayEndUtc)
            ->get();

        if ($booked->isNotEmpty()) {
            $slots = $slots->reject(function (array $slot) use ($booked) {
                foreach ($booked as $appt) {
                    $apptStart = $appt->start_time instanceof CarbonInterface
                        ? $appt->start_time->copy()
                        : Carbon::parse($appt->start_time);
                    $apptEnd = $appt->end_time instanceof CarbonInterface
                        ? $appt->end_time->copy()
                        : Carbon::parse($appt->end_time);

                    // Slot [start, end) overlaps appointment [start, end).
                    if ($slot['start']->lt($apptEnd) && $apptStart->lt($slot['end'])) {
                        return true;
                    }
                }

                return false;
            })->values();
        }

        // 7. Anticipación filter. Slots starting at or before now + 2h
        //    are excluded. The action layer re-enforces this; the service
        //    also enforces it so the published slot list does not include
        //    already-past slots.
        $cutoff = CarbonImmutable::now()->addHours(2);

        $slots = $slots->reject(fn (array $slot) => ! $slot['start']->gt($cutoff))
            ->values();

        // 8. Sort ascending by start.
        return $slots->sortBy(fn (array $slot) => $slot['start']->getTimestamp())
            ->values()
            ->all();
    }

    /**
     * True if a slot's [start_utc, end_utc] overlaps the block's
     * local-time [start, end). A null start AND null end is a full-day
     * block and matches every slot.
     */
    private function isSlotInBlock(array $slot, DoctorScheduleOverride $block, $localDate): bool
    {
        if ($block->start_time === null && $block->end_time === null) {
            return true;
        }

        $blockStart = $localDate->copy()
            ->setTimeFromTimeString((string) $block->start_time)
            ->setTimezone('UTC');
        $blockEnd = $localDate->copy()
            ->setTimeFromTimeString((string) $block->end_time)
            ->setTimezone('UTC');

        return $slot['start']->lt($blockEnd) && $blockStart->lt($slot['end']);
    }
}
