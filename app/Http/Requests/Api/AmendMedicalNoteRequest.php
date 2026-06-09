<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Wire payload for POST /api/medical-notes/{note}/amend.
 *
 * Same field shape as CreateMedicalNoteRequest but all fields are
 * optional since the doctor may only need to correct a subset.
 *
 * Authorisation is handled by MedicalHistoryPolicy@createNote in the
 * controller, not here — the request only validates the shape of the
 * payload.
 */
class AmendMedicalNoteRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'symptoms' => ['nullable', 'string', 'max:10000'],
            'physical_exam' => ['nullable', 'string', 'max:10000'],
            'diagnosis' => ['nullable', 'string', 'max:10000'],
            'treatment_notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
