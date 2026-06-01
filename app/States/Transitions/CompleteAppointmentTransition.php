<?php

namespace App\States\Transitions;

use App\Exceptions\Domain\UnauthorizedActorException;
use App\Models\Appointment;
use App\Models\User;
use App\States\Appointment\Completed;
use Spatie\ModelStates\Transition;

/**
 * confirmed → completed: assigned doctor only.
 *
 * Patients cannot self-complete. Admin and unassigned doctors are rejected.
 * Registered for the (Confirmed, Completed) edge in the abstract state's
 * config(). spatie instantiates this as (model, ...$transitionArgs) so the
 * caller must pass the actor positionally:
 *   $appointment->state->transitionTo(Completed::class, $doctor)
 */
class CompleteAppointmentTransition extends Transition
{
    public function __construct(
        public Appointment $model,
        public ?User $actor = null,
    ) {}

    public function handle(): Appointment
    {
        $actor = $this->actor ?? throw new UnauthorizedActorException(
            'A transition actor is required to complete an appointment.'
        );

        if (! $actor->isDoctor()) {
            throw new UnauthorizedActorException(
                'Only the assigned doctor can complete an appointment.'
            );
        }

        if ($actor->id !== $this->model->doctor->user_id) {
            throw new UnauthorizedActorException(
                'Only the assigned doctor can complete this appointment.'
            );
        }

        $this->model->state = new Completed($this->model);
        $this->model->save();

        return $this->model;
    }
}
