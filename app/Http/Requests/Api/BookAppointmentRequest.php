<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Wire payload for POST /api/appointments.
 *
 * Field rules (REQ-API-2 + REQ-API-7 + the booking contract from
 * agenda-core/booking/spec.md):
 *
 *   - doctor_id   : required, exists in `doctors` table.
 *   - start_time  : required, ISO 8601 datetime with offset. The
 *                   controller converts it to UTC via the resolved
 *                   timezone before passing to the action layer.
 *   - notes       : optional, free-text, capped at 1000 chars.
 *   - patient_id  : nullable, exists in `patients` table. REQUIRED
 *                   when the actor is admin or doctor (the action
 *                   layer is patient-centric, so non-patient actors
 *                   must supply the patient explicitly).
 *
 * The 2h anticipación rule is enforced by `BookAppointmentAction` as
 * the 2nd guard, not here — the FormRequest only validates the shape.
 * `AnticipationWindowViolationException` (422 ANTICIPATION_WINDOW_VIOLATION)
 * is the canonical signal.
 */
class BookAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // REQ-API-7 §5: only patients book via the API. Admin and
        // doctor actors are rejected with 403 FORBIDDEN at the HTTP
        // layer. The patient_id field requirement in rules() is
        // kept as defense-in-depth (a request that somehow bypassed
        // authorize() would still 422 on the missing patient_id).
        $user = $this->user();

        return $user?->isPatient() ?? false;
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Validation\Rules\In>>
     */
    public function rules(): array
    {
        $user = $this->user();
        $isPatient = $user?->isPatient() ?? false;

        $rules = [
            'doctor_id' => ['required', 'integer', Rule::exists('doctors', 'id')],
            'start_time' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        if (! $isPatient) {
            // Admin / doctor must pick the patient explicitly.
            $rules['patient_id'] = ['required', 'integer', Rule::exists('patients', 'id')];
        } else {
            $rules['patient_id'] = ['nullable', 'integer', Rule::exists('patients', 'id')];
        }

        return $rules;
    }
}
