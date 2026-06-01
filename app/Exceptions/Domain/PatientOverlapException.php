<?php

namespace App\Exceptions\Domain;

/**
 * Thrown when the patient already has a non-cancelled appointment
 * overlapping the requested slot. Overlap is defined as the existing
 * appointment's `[start_time, end_time)` intersecting the requested
 * `[start_time, end_time)`. Enforced by `BookAppointmentAction` as
 * the third guard after the slot-existence and anticipación checks.
 *
 * HTTP 422 (Unprocessable Entity) — semantically: the request was
 * well-formed but conflicts with the patient's existing schedule.
 */
class PatientOverlapException extends DomainException
{
    public function httpStatus(): int
    {
        return 422;
    }
}
