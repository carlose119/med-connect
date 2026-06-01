<?php

namespace App\Actions;

use App\Models\MedicalHistory;

/**
 * Idempotent helper that returns the patient's medical history,
 * creating a new row if one does not already exist. Designed to be
 * called from inside the booking transaction so a first-time patient
 * always has a history committed with their first appointment.
 *
 * The `medical_histories` table has a UNIQUE index on `patient_id`, so
 * `firstOrCreate` is safe even under concurrent bookings for the same
 * patient (the second `INSERT` raises a duplicate-key error, which
 * `firstOrCreate` handles by re-reading and returning the existing row).
 */
class EnsureMedicalHistoryAction
{
    public function __invoke(int $patientId): MedicalHistory
    {
        return MedicalHistory::firstOrCreate(
            ['patient_id' => $patientId],
            ['opened_at' => now()],
        );
    }
}
