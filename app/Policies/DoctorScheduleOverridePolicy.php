<?php

namespace App\Policies;

use App\Models\DoctorScheduleOverride;
use App\Models\User;

class DoctorScheduleOverridePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DoctorScheduleOverride $override): bool
    {
        return $user->isAdmin()
            || $user->doctor?->id === $override->doctor_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, DoctorScheduleOverride $override): bool
    {
        return $user->isAdmin()
            || $user->doctor?->id === $override->doctor_id;
    }

    public function delete(User $user, DoctorScheduleOverride $override): bool
    {
        return $user->isAdmin()
            || $user->doctor?->id === $override->doctor_id;
    }
}
