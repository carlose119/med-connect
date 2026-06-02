<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Wire payload for GET /api/prescriptions (REQ-API-6).
 *
 * Filters:
 *   - patient_id : nullable, exists in `patients`
 *   - from       : nullable, date (issued_at >= from)
 *   - to         : nullable, date (issued_at <= to)
 *   - per_page   : nullable, integer, 1..100 (default 20)
 */
class ListPrescriptionsRequest extends FormRequest
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
            'patient_id' => ['nullable', 'integer', Rule::exists('patients', 'id')],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
