<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Wire payload for POST /api/medical-histories/{history}/notes.
 *
 * Validation rules for clinical note fields:
 *   - symptoms       : optional, free-text
 *   - physical_exam  : optional, free-text
 *   - diagnosis      : required, free-text
 *   - treatment_notes: optional, free-text
 *
 * Authorisation is handled by MedicalHistoryPolicy@createNote in the
 * controller, not here — the request only validates the shape of the
 * payload.
 */
class CreateMedicalNoteRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'symptoms' => ['nullable', 'string', 'max:10000'],
            'physical_exam' => ['nullable', 'string', 'max:10000'],
            'diagnosis' => ['required', 'string', 'max:10000'],
            'treatment_notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
