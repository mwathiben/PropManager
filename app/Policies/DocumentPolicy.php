<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
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
     * Determine whether the user can view any documents.
     */
    public function viewAny(User $user): bool
    {
        return $user->isLandlord() || $user->isCaretaker() || $user->isTenant();
    }

    /**
     * Determine whether the user can view the document.
     */
    public function view(User $user, Document $document): bool
    {
        if ($user->isLandlord()) {
            return $document->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $document->landlord_id === $user->landlord_id;
        }

        if ($user->isTenant()) {
            // Tenant can view documents attached to their own user profile or lease
            if ($document->documentable_type === 'App\\Models\\User') {
                return $document->documentable_id === $user->id;
            }
            if ($document->documentable_type === 'App\\Models\\Lease') {
                return $document->documentable?->tenant_id === $user->id;
            }

            return false;
        }

        return false;
    }

    /**
     * Determine whether the user can create documents.
     */
    public function create(User $user): bool
    {
        return $user->isLandlord() || $user->isCaretaker() || $user->isTenant();
    }

    /**
     * Determine whether the user can update the document.
     */
    public function update(User $user, Document $document): bool
    {
        // Only the uploader or landlord can update
        if ($document->uploaded_by === $user->id) {
            return true;
        }

        return $user->isLandlord() && $document->landlord_id === $user->id;
    }

    /**
     * Determine whether the user can delete the document.
     */
    public function delete(User $user, Document $document): bool
    {
        // Only the uploader or landlord can delete
        if ($document->uploaded_by === $user->id) {
            return true;
        }

        return $user->isLandlord() && $document->landlord_id === $user->id;
    }

    /**
     * Determine whether the user can download the document.
     */
    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    /**
     * Phase-19 POLICY-1: super-admin only via before(); explicit deny here
     * so the destructive force-delete path is gated.
     */
    public function forceDelete(User $user, Document $document): bool
    {
        return false;
    }

    /**
     * Phase-19 POLICY-1: restoring mirrors delete() — uploader or landlord
     * who owned the document can undo a soft-delete.
     */
    public function restore(User $user, Document $document): bool
    {
        if ($document->uploaded_by === $user->id) {
            return true;
        }

        return $user->isLandlord() && $document->landlord_id === $user->id;
    }
}
