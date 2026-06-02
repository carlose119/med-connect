<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Wire payload for DELETE /api/appointments/{id}. The only field is
 * the operator note that the cancel action persists on
 * `appointments.cancellation_reason`.
 *
 * The 24h window check is enforced by `CancelAppointmentAction` (it
 * runs before the state transition), NOT by the FormRequest. The
 * policy gate ("can this user cancel?") is enforced by the
 * controller's `$this->authorize('cancel', $appointment)`.
 */
class CancelAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The policy gate is applied explicitly in the controller
        // because we need the `Appointment` instance. This authorize
        // hook only checks that the actor is authenticated — Sanctum
        // middleware already guarantees that, but the explicit guard
        // keeps the FormRequest's contract clear.
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
