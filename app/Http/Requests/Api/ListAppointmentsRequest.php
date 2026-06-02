<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Wire payload for GET /api/appointments (REQ-API-6).
 *
 * Filters:
 *   - from       : nullable, date (start_time >= from)
 *   - to         : nullable, date (start_time <= to)
 *   - doctor_id  : nullable, exists in `doctors`
 *   - patient_id : nullable, exists in `patients`
 *   - state      : nullable, in the appointments state machine
 *                  (pending | confirmed | completed | cancelled | no_show)
 *   - per_page   : nullable, integer, 1..100 (default 20)
 *
 * No `authorize` gate here — the controller applies the role scope
 * (patient → own, doctor → own, admin → all) before the query runs.
 * The FormRequest only validates the shape.
 */
class ListAppointmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'doctor_id' => ['nullable', 'integer', Rule::exists('doctors', 'id')],
            'patient_id' => ['nullable', 'integer', Rule::exists('patients', 'id')],
            'state' => [
                'nullable',
                'string',
                Rule::in(['pending', 'confirmed', 'completed', 'cancelled', 'no_show']),
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
