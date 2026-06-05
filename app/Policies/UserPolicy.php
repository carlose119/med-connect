<?php

namespace App\Policies;

use App\Models\User;

/**
 * User-resource authorisation.
 *
 * Only admins can manage user records. Users may view their own record
 * (used for the Sanctum /user endpoint). Doctors and patients cannot
 * view or modify other users' records.
 *
 * Slice 3 (REQ-ADV-3 / design.md Decision 3): each ENUM predicate is
 * OR'd with the corresponding Spatie Role lookup. The ENUM check is
 * the first operand — if it is true, the method short-circuits and
 * returns true regardless of Spatie state. The Spatie branch is a
 * fallback that grants access additively when the ENUM check fails
 * (e.g. a "patient" ENUM user granted Spatie 'admin' can now view +
 * update + create + delete any user record). The ENUM side is
 * unchanged → the existing PolicyTest still passes.
 */
class UserPolicy
{
    /**
     * Admins can list every user; everyone else is denied.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin() || $actor->hasRole('admin');
    }

    /**
     * Admins can view any user; users can view their own record.
     */
    public function view(User $actor, User $target): bool
    {
        return ($actor->isAdmin() || $actor->hasRole('admin')) || $actor->id === $target->id;
    }

    /**
     * Only admins can create users.
     */
    public function create(User $actor): bool
    {
        return $actor->isAdmin() || $actor->hasRole('admin');
    }

    /**
     * Only admins can update user records.
     */
    public function update(User $actor, User $target): bool
    {
        return $actor->isAdmin() || $actor->hasRole('admin');
    }

    /**
     * Only admins can delete user records.
     */
    public function delete(User $actor, User $target): bool
    {
        return $actor->isAdmin() || $actor->hasRole('admin');
    }
}
