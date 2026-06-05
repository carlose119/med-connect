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
 *
 * Slice 3 (REQ-ADV-3 / design.md Decision 3): each ENUM predicate is
 * OR'd with the corresponding Spatie Role lookup. The ENUM check is
 * the first operand — if it is true, the method short-circuits and
 * returns true regardless of Spatie state. The Spatie branch is a
 * fallback that grants access additively when the ENUM check fails
 * (e.g. a "patient" ENUM user granted Spatie 'doctor' can now view
 * any patient they share an appointment with). The ENUM side is
 * unchanged → the existing PolicyTest still passes.
 */
class PatientPolicy
{
    public function viewAny(User $actor): bool
    {
        return ($actor->isAdmin() || $actor->hasRole('admin'))
            || ($actor->isDoctor() || $actor->hasRole('doctor'));
    }

    public function view(User $actor, Patient $patient): bool
    {
        if ($actor->isAdmin() || $actor->hasRole('admin')) {
            return true;
        }

        if ($actor->isDoctor() || $actor->hasRole('doctor')) {
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

        if ($actor->isPatient() || $actor->hasRole('patient')) {
            return $actor->id === $patient->user_id;
        }

        return false;
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin() || $actor->hasRole('admin');
    }

    public function update(User $actor, Patient $patient): bool
    {
        if ($actor->isAdmin() || $actor->hasRole('admin')) {
            return true;
        }

        if ($actor->isPatient() || $actor->hasRole('patient')) {
            return $actor->id === $patient->user_id;
        }

        return false;
    }

    public function delete(User $actor, Patient $patient): bool
    {
        return $actor->isAdmin() || $actor->hasRole('admin');
    }
}
