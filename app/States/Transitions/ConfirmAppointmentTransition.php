<?php

namespace App\States\Transitions;

use App\Exceptions\Domain\UnauthorizedActorException;
use App\Models\Appointment;
use App\Models\User;
use App\States\Appointment\Confirmed;
use Spatie\ModelStates\Transition;

/**
 * pending → confirmed: assigned doctor only.
 *
 * Patients cannot self-confirm. Admin and unassigned doctors are rejected.
 * The structural allow-edge is declared in
 * App\States\Appointment\AppointmentState::config() with this class as the
 * custom transition; spatie calls this constructor with
 * (model, ...transitionArgs), so callers pass the actor as a positional
 * second argument: `$appointment->state->transitionTo(Confirmed::class, $user)`.
 */
class ConfirmAppointmentTransition extends Transition
{
    public function __construct(
        public Appointment $model,
        public ?User $actor = null,
    ) {}

    public function handle(): Appointment
    {
        $actor = $this->actor ?? throw new UnauthorizedActorException(
            'A transition actor is required to confirm an appointment.'
        );

        if (! $actor->isDoctor()) {
            throw new UnauthorizedActorException(
                'Only the assigned doctor can confirm an appointment.'
            );
        }

        if ($actor->id !== $this->model->doctor->user_id) {
            throw new UnauthorizedActorException(
                'Only the assigned doctor can confirm this appointment.'
            );
        }

        $this->model->state = new Confirmed($this->model);
        $this->model->save();

        return $this->model;
    }
}
