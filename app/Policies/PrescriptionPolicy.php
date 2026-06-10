<?php

namespace App\Policies;

use App\Models\Prescription;
use App\Models\User;

class PrescriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Prescription $prescription): bool
    {
        return $user->isAdmin()
            || $user->doctor?->id === $prescription->doctor_id
            || $user->patient?->id === $prescription->patient_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Prescription $prescription): bool
    {
        return $this->view($user, $prescription);
    }

    public function delete(User $user, Prescription $prescription): bool
    {
        return false;
    }
}