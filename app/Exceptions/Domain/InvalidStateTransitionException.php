<?php

namespace App\Exceptions\Domain;

/**
 * Thrown when a state transition is structurally invalid — e.g. a request
 * to transition out of a terminal state, or to a target state that is not
 * declared in the state class's allowedTransitions map.
 *
 * Actor policy failures (patient cannot self-confirm, etc.) raise
 * UnauthorizedActorException instead, not this one. The two are distinct
 * because they map to different status codes (422 vs 403) and represent
 * different categories of failure.
 */
class InvalidStateTransitionException extends DomainException
{
    public function httpStatus(): int
    {
        return 422;
    }
}
