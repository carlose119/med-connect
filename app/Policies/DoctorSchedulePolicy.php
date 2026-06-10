<?php

namespace App\Policies;

use App\Models\DoctorSchedule;
use App\Models\User;

class DoctorSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DoctorSchedule $schedule): bool
    {
        return $user->isAdmin()
            || $user->doctor?->id === $schedule->doctor_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, DoctorSchedule $schedule): bool
    {
        return $user->isAdmin()
            || $user->doctor?->id === $schedule->doctor_id;
    }

    public function delete(User $user, DoctorSchedule $schedule): bool
    {
        return $user->isAdmin()
            || $user->doctor?->id === $schedule->doctor_id;
    }
}
