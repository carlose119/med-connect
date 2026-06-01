<?php

namespace App\Actions;

use App\Exceptions\Domain\CancellationWindowViolationException;
use App\Models\Appointment;
use App\Models\User;
use App\States\Appointment\Cancelled;

/**
 * Domain action for the patient/doctor/admin cancel flow. The PR 3
 * `CancelAppointmentTransition` already enforces actor identity and
 * the 24h window, but this action runs the 24h check up-front so the
 * spec's "Patient cancels outside the 24h window" scenario raises
 * the right exception class without round-tripping through the state
 * machine. Doctors and admins bypass the window.
 *
 * The action is the place that records the cancellation_reason (the
 * transition does not know about it) and the only public entrypoint
 * to transition an appointment to `cancelled`. The future
 * notification/audit wiring lands in a later change.
 */
class CancelAppointmentAction
{
    public function __invoke(int $appointmentId, User $actor, ?string $reason = null): Appointment
    {
        $appointment = Appointment::findOrFail($appointmentId);

        // 24h anticipación for patients only. Doctors and admins
        // bypass — their cancellations are operational events, not
        // patient UX, and the spec's "patient window" is a patient
        // affordance, not a global rule.
        if ($actor->isPatient() && $this->isOutsidePatientWindow($appointment)) {
            throw new CancellationWindowViolationException(sprintf(
                'Patient cancellation must occur at least 24 hours before start_time. Start: %s.',
                $appointment->start_time->toIso8601String(),
            ));
        }

        // Persist the reason before the state change so the cancelled
        // row carries the operator's note (PR 5 audit log will pick
        // this up via the model events).
        if ($reason !== null) {
            $appointment->cancellation_reason = $reason;
            $appointment->save();
        }

        // Delegate the actor check + state change to the PR 3
        // transition. spatie instantiates the transition as
        // (model, ...transitionArgs), so the actor is the second
        // positional argument.
        return $appointment->state->transitionTo(Cancelled::class, $actor);
    }

    private function isOutsidePatientWindow(Appointment $appointment): bool
    {
        $cutoff = $appointment->start_time->copy()->subHours(24);

        // now() >= start_time - 24h ⇒ outside the window.
        return now()->greaterThanOrEqualTo($cutoff);
    }
}
