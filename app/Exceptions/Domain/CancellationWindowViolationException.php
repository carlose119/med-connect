<?php

namespace App\Exceptions\Domain;

/**
 * Thrown when a patient attempts to cancel an appointment inside the 24h
 * window before start_time. Doctors and admins bypass the window
 * (their cancellations are out-of-band operational events, not patient
 * UX).
 *
 * The spec allows 422 or 403; we default to 422 (semantic: the request
 * was structurally well-formed but violates a business rule). The base
 * class is 422; switch to 403 by overriding httpStatus() if the
 * application policy changes.
 */
class CancellationWindowViolationException extends DomainException
{
    public function httpStatus(): int
    {
        return 422;
    }
}
