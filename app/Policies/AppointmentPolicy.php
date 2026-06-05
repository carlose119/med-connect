<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

/**
 * Appointment-record authorisation.
 *
 * For PR 3 the cancel method returns true for the assigned patient
 * (own appointment) so the policy gate can be wired now; the actual
 * 24h-window enforcement and state transition live in the
 * App\States\Transitions.CancelAppointmentTransition class (which
 * throws CancellationWindowViolationException when violated). PR 4
 * will wire the appointment controller to call $appointment->state->
 * transitionTo(Cancelled::class, $user) after passing the policy.
 *
 * viewAny: admins and doctors can list appointments.
 * view:   admin, assigned doctor, or assigned patient.
 * create: admin or doctor.
 * update: admin or assigned doctor.
 * delete: admin only.
 * cancel: admin, assigned doctor, or assigned patient.
 *
 * Slice 3 (REQ-ADV-3 / design.md Decision 3): each ENUM predicate is
 * OR'd with the corresponding Spatie Role lookup. The ENUM check is
 * the first operand — if it is true, the method short-circuits and
 * returns true regardless of Spatie state. The Spatie branch is a
 * fallback that grants access additively when the ENUM check fails
 * (e.g. a "patient" ENUM user granted Spatie 'admin' can now view
 * + cancel any appointment). The ENUM side is unchanged → the
 * existing PolicyTest still passes.
 */
class AppointmentPolicy
{
    public function viewAny(User $actor): bool
    {
        return ($actor->isAdmin() || $actor->hasRole('admin'))
            || ($actor->isDoctor() || $actor->hasRole('doctor'));
    }

    public function view(User $actor, Appointment $appointment): bool
    {
        if ($actor->isAdmin() || $actor->hasRole('admin')) {
            return true;
        }

        if ($actor->isDoctor() || $actor->hasRole('doctor')) {
            return $actor->id === $appointment->doctor->user_id;
        }

        if ($actor->isPatient() || $actor->hasRole('patient')) {
            return $actor->id === $appointment->patient->user_id;
        }

        return false;
    }

    public function create(User $actor): bool
    {
        return ($actor->isAdmin() || $actor->hasRole('admin'))
            || ($actor->isDoctor() || $actor->hasRole('doctor'));
    }

    public function update(User $actor, Appointment $appointment): bool
    {
        if ($actor->isAdmin() || $actor->hasRole('admin')) {
            return true;
        }

        if ($actor->isDoctor() || $actor->hasRole('doctor')) {
            return $actor->id === $appointment->doctor->user_id;
        }

        return false;
    }

    public function delete(User $actor, Appointment $appointment): bool
    {
        return $actor->isAdmin() || $actor->hasRole('admin');
    }

    /**
     * PR 3 stub: the 24h window is enforced by the state machine
     * transition. The policy only gates "is this user allowed to
     * attempt the cancel action" — the transition class does the
     * fine-grained time check.
     */
    public function cancel(User $actor, Appointment $appointment): bool
    {
        if ($actor->isAdmin() || $actor->hasRole('admin')) {
            return true;
        }

        if ($actor->isDoctor() || $actor->hasRole('doctor')) {
            return $actor->id === $appointment->doctor->user_id;
        }

        if ($actor->isPatient() || $actor->hasRole('patient')) {
            return $actor->id === $appointment->patient->user_id;
        }

        return false;
    }
}
