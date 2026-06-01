<?php

namespace App\Policies;

use App\Models\User;

/**
 * User-resource authorisation.
 *
 * Only admins can manage user records. Users may view their own record
 * (used for the Sanctum /user endpoint). Doctors and patients cannot
 * view or modify other users' records.
 */
class UserPolicy
{
    /**
     * Admins can list every user; everyone else is denied.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    /**
     * Admins can view any user; users can view their own record.
     */
    public function view(User $actor, User $target): bool
    {
        return $actor->isAdmin() || $actor->id === $target->id;
    }

    /**
     * Only admins can create users.
     */
    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    /**
     * Only admins can update user records.
     */
    public function update(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }

    /**
     * Only admins can delete user records.
     */
    public function delete(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }
}
