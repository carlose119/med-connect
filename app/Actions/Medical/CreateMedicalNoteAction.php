<?php

namespace App\Actions\Medical;

use App\Models\Doctor;
use App\Models\MedicalHistory;
use App\Models\MedicalNote;

/**
 * Persist a new MedicalNote for the given history and doctor.
 *
 * Accepts an array of clinical fields (symptoms, physical_exam,
 * diagnosis, treatment_notes). Returns the created MedicalNote.
 */
class CreateMedicalNoteAction
{
    public function __invoke(MedicalHistory $history, Doctor $doctor, array $data): MedicalNote
    {
        return MedicalNote::create([
            'medical_history_id' => $history->id,
            'doctor_id' => $doctor->id,
            'symptoms' => $data['symptoms'] ?? null,
            'physical_exam' => $data['physical_exam'] ?? null,
            'diagnosis' => $data['diagnosis'] ?? null,
            'treatment_notes' => $data['treatment_notes'] ?? null,
        ]);
    }
}
