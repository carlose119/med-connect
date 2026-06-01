<?php

namespace App\Exceptions\Domain;

/**
 * Thrown when the requested slot is not in the published schedule, or
 * when the persistence layer rejects a second insert into the same
 * (doctor_id, start_time) for a non-cancelled row. The persistence
 * branch is the second line of defense behind `BookAppointmentAction`'s
 * `lockForUpdate()` row lock; either path converges on this exception.
 *
 * HTTP 409 (Conflict) — the request collides with the current state
 * of the target resource.
 */
class SlotNotAvailableException extends DomainException
{
    public function httpStatus(): int
    {
        return 409;
    }
}
