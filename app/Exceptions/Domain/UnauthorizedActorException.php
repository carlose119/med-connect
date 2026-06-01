<?php

namespace App\Exceptions\Domain;

/**
 * Thrown when the actor attempting a state transition is not allowed to
 * perform it — e.g. a patient attempting to confirm an appointment, an
 * unassigned doctor attempting to confirm someone else's appointment, or
 * a non-admin user attempting an admin-only action.
 *
 * Distinct from InvalidStateTransitionException: that one represents a
 * structurally invalid transition (e.g. out of a terminal state); this
 * one represents a structurally valid transition attempted by the wrong
 * actor. The two map to different status codes (403 vs 422).
 */
class UnauthorizedActorException extends DomainException
{
    public function httpStatus(): int
    {
        return 403;
    }
}
