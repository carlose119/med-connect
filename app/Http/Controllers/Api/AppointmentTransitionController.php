<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\AppointmentTransitionRequest;
use App\Http\Resources\Api\AppointmentResource;
use App\Models\Appointment;
use App\States\Appointment\Completed as CompletedState;
use App\States\Appointment\Confirmed as ConfirmedState;
use App\States\Appointment\NoShow as NoShowState;
use Illuminate\Routing\Controller;

/**
 * HTTP surface for the 3 state-machine transition endpoints
 * (REQ-API-2 + the appointment state machine from agenda-core PR 3).
 *
 *   POST /api/appointments/{id}/transitions/confirm
 *   POST /api/appointments/{id}/transitions/complete
 *   POST /api/appointments/{id}/transitions/no-show
 *
 * Each method delegates to the corresponding transition class via
 * spatie's `$appointment->state->transitionTo(NewState::class, $actor)`.
 * The transition class does the actor check and throws
 * UnauthorizedActorException on rejection (→ 403 via the PR 1
 * exception handler).
 *
 * NOTE: the appointment's CURRENT state determines which edges
 * are allowed (per AppointmentState::config()). The edge list is:
 *   - pending    → confirmed, cancelled
 *   - confirmed  → completed, cancelled, no_show
 *   - completed  → (terminal)
 *   - cancelled  → (terminal)
 *   - no_show    → (terminal)
 */
class AppointmentTransitionController extends Controller
{
    public function confirm(AppointmentTransitionRequest $request, Appointment $appointment): AppointmentResource
    {
        $appointment->state->transitionTo(ConfirmedState::class, $request->user());

        return new AppointmentResource($appointment->refresh());
    }

    public function complete(AppointmentTransitionRequest $request, Appointment $appointment): AppointmentResource
    {
        $appointment->state->transitionTo(CompletedState::class, $request->user());

        return new AppointmentResource($appointment->refresh());
    }

    public function markNoShow(AppointmentTransitionRequest $request, Appointment $appointment): AppointmentResource
    {
        $appointment->state->transitionTo(NoShowState::class, $request->user());

        return new AppointmentResource($appointment->refresh());
    }
}
