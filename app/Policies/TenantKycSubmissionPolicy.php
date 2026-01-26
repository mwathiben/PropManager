<?php

namespace App\Policies;

use App\Models\TenantKycSubmission;
use App\Models\User;

class TenantKycSubmissionPolicy
{
    /**
     * Super admins bypass all authorization.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Tenant can view their own submissions.
     * Landlord/caretaker can view submissions for their tenants.
     */
    public function view(User $user, TenantKycSubmission $submission): bool
    {
        if ($user->isTenant()) {
            return $submission->user_id === $user->id;
        }

        if ($user->isLandlord()) {
            return $submission->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $submission->landlord_id === $user->landlord_id;
        }

        return false;
    }

    /**
     * Tenant can create submissions for themselves.
     */
    public function create(User $user): bool
    {
        return $user->isTenant();
    }

    /**
     * Tenant can update their own pending or rejected submissions.
     */
    public function update(User $user, TenantKycSubmission $submission): bool
    {
        if (! $user->isTenant()) {
            return false;
        }

        if ($submission->user_id !== $user->id) {
            return false;
        }

        return $submission->isPending() || $submission->isRejected();
    }

    /**
     * Landlord/caretaker can review pending submissions for their tenants.
     */
    public function review(User $user, TenantKycSubmission $submission): bool
    {
        if (! $submission->isPending()) {
            return false;
        }

        if ($user->isLandlord()) {
            return $submission->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $submission->landlord_id === $user->landlord_id;
        }

        return false;
    }

    /**
     * Tenant can delete their own pending submissions.
     */
    public function delete(User $user, TenantKycSubmission $submission): bool
    {
        if (! $user->isTenant()) {
            return false;
        }

        return $submission->user_id === $user->id && $submission->isPending();
    }
}
