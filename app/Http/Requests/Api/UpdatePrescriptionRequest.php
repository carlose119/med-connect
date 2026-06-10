<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Wire payload for PUT /api/prescriptions/{prescription}.
 *
 * Validation rules:
 *   - status              : required, in:cancelled
 *   - cancellation_reason : required, string, max 1000
 *
 * The cancellation_reason is required when status=cancelled.
 */
class UpdatePrescriptionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:cancelled'],
            'cancellation_reason' => ['required', 'string', 'max:1000'],
        ];
    }
}