<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

/**
 * Patient-record authorisation.
 *
 * Admins can manage every patient record. Doctors can view every
 * patient record (they need it to schedule appointments and write
 * medical notes — actual write access is gated by the appointment
 * state machine in PR 4). Patients can view and update their own
 * record only.
 */
class PatientPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin() || $actor->isDoctor();
    }

    public function view(User $actor, Patient $patient): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        if ($actor->isDoctor()) {
            // Spec: doctors can only view patients they share an
            // appointment with (REQ-API-2 §5). The check is a single
            // exists() query (no N+1) and mirrors the
            // AppointmentPolicy@view pattern. The `?->` nullsafe
            // covers the edge case where a doctor User has no
            // Doctor profile (defensive; should not happen).
            return $actor->doctor?->appointments()
                ->where('patient_id', $patient->id)
                ->exists() ?? false;
        }

        if ($actor->isPatient()) {
            return $actor->id === $patient->user_id;
        }

        return false;
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, Patient $patient): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        if ($actor->isPatient()) {
            return $actor->id === $patient->user_id;
        }

        return false;
    }

    public function delete(User $actor, Patient $patient): bool
    {
        return $actor->isAdmin();
    }
}
