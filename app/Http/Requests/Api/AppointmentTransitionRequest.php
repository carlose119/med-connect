<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Wire payload for the 3 transition endpoints
 * (POST /api/appointments/{id}/transitions/{action}). The only
 * optional field is `notes`, a free-text operator note (capped at
 * 1000 chars) that the transition classes can persist on the
 * appointment if they want to (PR 3 ignores it; a future change may
 * persist it on `appointments.notes` or a sidecar table).
 *
 * The route-level authz is delegated to the transition classes
 * themselves (ConfirmAppointmentTransition, etc.) which check
 * "is this user the assigned doctor or an admin?" via
 * UnauthorizedActorException → 403 UNAUTHORIZED_ACTOR.
 */
class AppointmentTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
