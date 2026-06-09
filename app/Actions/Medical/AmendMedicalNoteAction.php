<?php

namespace App\Actions\Medical;

use App\Models\MedicalNote;

/**
 * Create an amended copy of a MedicalNote.
 *
 * Persists a NEW note whose `corrects_note_id` references the original.
 * The original note is never mutated (append-only contract).
 *
 * Throws LogicException if the original note already has corrections —
 * amendments must chain from the latest correction, not the original.
 */
class AmendMedicalNoteAction
{
    public function __invoke(MedicalNote $original, array $data): MedicalNote
    {
        if ($original->corrections()->exists()) {
            throw new \LogicException(
                'The original note has already been corrected. Amend the latest correction instead.'
            );
        }

        return MedicalNote::create([
            'medical_history_id' => $original->medical_history_id,
            'appointment_id' => $original->appointment_id,
            'doctor_id' => $original->doctor_id,
            'corrects_note_id' => $original->id,
            'symptoms' => $data['symptoms'] ?? $original->symptoms,
            'physical_exam' => $data['physical_exam'] ?? $original->physical_exam,
            'diagnosis' => $data['diagnosis'] ?? $original->diagnosis,
            'treatment_notes' => $data['treatment_notes'] ?? $original->treatment_notes,
        ]);
    }
}
