<?php

namespace App\States\Transitions;

use App\Exceptions\Domain\CancellationWindowViolationException;
use App\Exceptions\Domain\UnauthorizedActorException;
use App\Models\Appointment;
use App\Models\User;
use App\States\Appointment\Cancelled;
use Spatie\ModelStates\Transition;

/**
 * pending|confirmed → cancelled.
 *
 * Three actor paths:
 *  - assigned patient inside the 24h window (now() < start_time - 24h)
 *  - assigned doctor (no time restriction)
 *  - admin (no time restriction)
 *
 * Patient actors outside the 24h window get CancellationWindowViolationException.
 * Unassigned doctor or non-assigned patient get UnauthorizedActorException.
 *
 * Registered for the (Pending, Cancelled) and (Confirmed, Cancelled) edges
 * in App\States\Appointment\AppointmentState::config(). spatie instantiates
 * this as (model, ...$transitionArgs) so the caller must pass the actor
 * positionally: $appointment->state->transitionTo(Cancelled::class, $user).
 */
class CancelAppointmentTransition extends Transition
{
    public function __construct(
        public Appointment $model,
        public ?User $actor = null,
    ) {}

    public function handle(): Appointment
    {
        $actor = $this->actor ?? throw new UnauthorizedActorException(
            'A transition actor is required to cancel an appointment.'
        );

        if ($actor->isAdmin()) {
            // Admins can cancel any appointment at any time.
        } elseif ($actor->isDoctor()) {
            if ($actor->id !== $this->model->doctor->user_id) {
                throw new UnauthorizedActorException(
                    'Only the assigned doctor can cancel this appointment.'
                );
            }
        } elseif ($actor->isPatient()) {
            if ($actor->id !== $this->model->patient->user_id) {
                throw new UnauthorizedActorException(
                    'Only the assigned patient can cancel this appointment.'
                );
            }

            if (now() >= $this->model->start_time->copy()->subHours(24)) {
                throw new CancellationWindowViolationException(
                    'Patient cancellation must occur at least 24 hours before start_time.'
                );
            }
        } else {
            throw new UnauthorizedActorException(
                'Only the assigned patient, the assigned doctor, or an admin can cancel this appointment.'
            );
        }

        $this->model->state = new Cancelled($this->model);
        $this->model->save();

        return $this->model;
    }
}
