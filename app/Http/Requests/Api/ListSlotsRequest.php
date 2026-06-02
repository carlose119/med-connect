<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Wire payload for GET /api/doctors/{id}/slots (REQ-API-12).
 *
 *   - date : required, ISO 8601 date (Y-m-d)
 *   - duration : optional, integer, 15..240 minutes (informational;
 *                the slot duration comes from the doctor's published
 *                schedule and the service does the slicing)
 */
class ListSlotsRequest extends FormRequest
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
            'date' => ['required', 'date_format:Y-m-d'],
            'duration' => ['nullable', 'integer', 'min:15', 'max:240'],
        ];
    }
}
