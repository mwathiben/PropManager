<?php

namespace App\Policies;

use App\Models\Invitation;
use App\Models\User;

class InvitationPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any invitations.
     */
    public function viewAny(User $user): bool
    {
        return $user->isLandlord();
    }

    /**
     * Determine whether the user can view the invitation.
     */
    public function view(User $user, Invitation $invitation): bool
    {
        return $user->isLandlord() && $invitation->landlord_id === $user->id;
    }

    /**
     * Determine whether the user can create invitations.
     */
    public function create(User $user): bool
    {
        return $user->isLandlord();
    }

    /**
     * Determine whether the user can update the invitation.
     */
    public function update(User $user, Invitation $invitation): bool
    {
        return $user->isLandlord() && $invitation->landlord_id === $user->id;
    }

    /**
     * Determine whether the user can delete the invitation.
     */
    public function delete(User $user, Invitation $invitation): bool
    {
        return $user->isLandlord() && $invitation->landlord_id === $user->id;
    }

    /**
     * Determine whether the user can resend the invitation.
     */
    public function resend(User $user, Invitation $invitation): bool
    {
        return $user->isLandlord()
            && $invitation->landlord_id === $user->id
            && ! $invitation->isAccepted();
    }
}
