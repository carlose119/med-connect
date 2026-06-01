<?php

namespace App\States\Transitions;

use App\Exceptions\Domain\UnauthorizedActorException;
use App\Models\Appointment;
use App\Models\User;
use App\States\Appointment\NoShow;
use Spatie\ModelStates\Transition;

/**
 * confirmed → no_show: assigned doctor or admin, and only after start_time.
 *
 * Patients cannot mark themselves as no-show. Unassigned doctors and
 * doctor actors attempting to mark before start_time are rejected.
 *
 * Registered for the (Confirmed, NoShow) edge in
 * App\States\Appointment\AppointmentState::config(). spatie instantiates
 * this as (model, ...$transitionArgs) so the caller must pass the actor
 * positionally:
 *   $appointment->state->transitionTo(NoShow::class, $doctorOrAdmin).
 */
class MarkNoShowAppointmentTransition extends Transition
{
    public function __construct(
        public Appointment $model,
        public ?User $actor = null,
    ) {}

    public function handle(): Appointment
    {
        $actor = $this->actor ?? throw new UnauthorizedActorException(
            'A transition actor is required to mark an appointment as no-show.'
        );

        if ($actor->isAdmin()) {
            // Admins can mark any confirmed appointment as no-show.
        } elseif ($actor->isDoctor()) {
            if ($actor->id !== $this->model->doctor->user_id) {
                throw new UnauthorizedActorException(
                    'Only the assigned doctor can mark this appointment as no-show.'
                );
            }
        } else {
            throw new UnauthorizedActorException(
                'Only the assigned doctor or an admin can mark this appointment as no-show.'
            );
        }

        $this->model->state = new NoShow($this->model);
        $this->model->save();

        return $this->model;
    }
}
