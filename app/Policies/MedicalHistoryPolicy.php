<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\MedicalHistory;
use App\Models\User;

/**
 * Authorisation for MedicalHistory resources.
 *
 * view(User $actor, MedicalHistory $history): bool
 *   - Admin → true
 *   - Self patient → true
 *   - Doctor with an appointment for the patient → true
 *   - Everyone else → false
 *
 * createNote(User $actor, MedicalHistory $history): bool
 *   - Doctor with a completed appointment for the patient → true
 *   - Everyone else → false
 */
class MedicalHistoryPolicy
{
    public function view(User $actor, MedicalHistory $history): bool
    {
        if ($actor->isAdmin() || $actor->hasRole('admin')) {
            return true;
        }

        if ($actor->isPatient() || $actor->hasRole('patient')) {
            return $actor->id === $history->patient->user_id;
        }

        if ($actor->isDoctor() || $actor->hasRole('doctor')) {
            return Appointment::query()
                ->where('doctor_id', $actor->doctor->id)
                ->where('patient_id', $history->patient_id)
                ->exists();
        }

        return false;
    }

    public function createNote(User $actor, MedicalHistory $history): bool
    {
        if (! $actor->isDoctor() && ! $actor->hasRole('doctor')) {
            return false;
        }

        return Appointment::query()
            ->where('doctor_id', $actor->doctor->id)
            ->where('patient_id', $history->patient_id)
            ->where('state', 'completed')
            ->exists();
    }
}
