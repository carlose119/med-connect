<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Wire payload for POST /api/prescriptions.
 *
 * Validation rules:
 *   - appointment_id : required, exists in appointments
 *   - items          : required, array, min 1 item
 *   - items.*.name   : required, string, max 255
 *   - items.*.dosage : nullable, string, max 128
 *   - items.*.frequency: nullable, string, max 128
 *   - items.*.duration : nullable, string, max 128
 *
 * Custom validation (in controller): appointment must be in 'completed' state.
 * The request only validates the shape of the payload.
 */
class CreatePrescriptionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'appointment_id' => ['required', 'integer', 'exists:appointments,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.dosage' => ['nullable', 'string', 'max:128'],
            'items.*.frequency' => ['nullable', 'string', 'max:128'],
            'items.*.duration' => ['nullable', 'string', 'max:128'],
        ];
    }
}