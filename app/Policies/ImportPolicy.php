<?php

namespace App\Policies;

use App\Models\Import;
use App\Models\User;

class ImportPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isScopeOwner() || $user->isCaretaker();
    }

    public function view(User $user, Import $import): bool
    {
        return $this->isOwner($user, $import);
    }

    public function create(User $user): bool
    {
        return $user->isScopeOwner() || $user->isCaretaker();
    }

    public function reprocess(User $user, Import $import): bool
    {
        return $this->isOwner($user, $import);
    }

    public function delete(User $user, Import $import): bool
    {
        return $this->isOwner($user, $import);
    }

    /**
     * Phase-19 POLICY-4: Imports are immutable post-upload — a CSV
     * succeeded or failed, and mutation corrupts the upload audit
     * lineage. If metadata-amend (e.g. renaming the display label)
     * becomes a real requirement, flip this to $this->isOwner().
     */
    public function update(User $user, Import $import): bool
    {
        return false;
    }

    private function isOwner(User $user, Import $import): bool
    {
        if ($user->isScopeOwner()) {
            return $import->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $import->landlord_id === $user->landlord_id;
        }

        return false;
    }
}
