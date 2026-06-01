<?php

namespace App\States\Appointment;

use App\States\Appointment\Cancelled;
use App\States\Appointment\Completed;
use App\States\Appointment\Confirmed;
use App\States\Appointment\NoShow;
use App\States\Appointment\Pending;
use App\States\Transitions\CancelAppointmentTransition;
use App\States\Transitions\CompleteAppointmentTransition;
use App\States\Transitions\ConfirmAppointmentTransition;
use App\States\Transitions\MarkNoShowAppointmentTransition;
use Spatie\ModelStates\Exceptions\TransitionNotFound;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @template-extends State<\App\Models\Appointment>
 */
abstract class AppointmentState extends State
{
    /**
     * Spatie's structural transition map (terminals are intentionally omitted:
     * they have no outgoing edges, so spatie rejects any transition out of
     * them via the default TransitionNotFound behaviour).
     *
     * Actor-level checks (patient cannot self-confirm, doctor-only on
     * confirm/complete, patient 24h cancel window) are NOT enforced here —
     * they live in the per-edge Transition classes under
     * App\States\Transitions. This is intentional: enums cannot be
     * parameterised per request, but a Transition instance can.
     */
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class, Confirmed::class, ConfirmAppointmentTransition::class)
            ->allowTransition(Pending::class, Cancelled::class, CancelAppointmentTransition::class)
            ->allowTransition(Confirmed::class, Completed::class, CompleteAppointmentTransition::class)
            ->allowTransition(Confirmed::class, Cancelled::class, CancelAppointmentTransition::class)
            ->allowTransition(Confirmed::class, NoShow::class, MarkNoShowAppointmentTransition::class);
    }

    /**
     * Override spatie's default "no allowed transition" exception with our
     * domain-specific exception. This makes the test gate and the spec's
     * "Transition out of a terminal state is rejected" scenario match.
     *
     * Custom transition classes (actor checks, 24h window) throw their own
     * domain exceptions from their handle() method, so they are unaffected
     * by this catch.
     */
    public function transitionTo($newState, ...$transitionArgs)
    {
        try {
            return parent::transitionTo($newState, ...$transitionArgs);
        } catch (TransitionNotFound $e) {
            throw new \App\Exceptions\Domain\InvalidStateTransitionException(
                $e->getMessage(),
                previous: $e,
            );
        }
    }
}
