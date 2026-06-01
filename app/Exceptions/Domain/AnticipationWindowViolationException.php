<?php

namespace App\Exceptions\Domain;

/**
 * Thrown when a booking is attempted inside the 2h anticipación window
 * (`start_time < now() + 2h`). Enforced by `BookAppointmentAction` as
 * the second guard after the slot-existence check. The pure service
 * `DoctorAvailabilityService::slots()` also filters these out so the
 * published slot list never includes them; the action re-checks to
 * defend against a request submitted from a stale list.
 *
 * HTTP 422 (Unprocessable Entity) — the request is structurally valid
 * but violates a business rule.
 */
class AnticipationWindowViolationException extends DomainException
{
    public function httpStatus(): int
    {
        return 422;
    }
}
