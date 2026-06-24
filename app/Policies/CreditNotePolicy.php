<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CreditNote;
use App\Models\User;

/**
 * Phase-76 WALLET-DEEP CREDIT-WALLET-3: explicit authorization for credit notes
 * (replaces the inline landlord_id aborts in CreditNoteController). Landlord
 * owns by id; caretaker via landlord_id; super_admin bypasses.
 */
class CreditNotePolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function view(User $user, CreditNote $creditNote): bool
    {
        return $this->owns($user, $creditNote);
    }

    public function approve(User $user, CreditNote $creditNote): bool
    {
        return $this->owns($user, $creditNote);
    }

    public function apply(User $user, CreditNote $creditNote): bool
    {
        return $this->owns($user, $creditNote);
    }

    public function applyToWallet(User $user, CreditNote $creditNote): bool
    {
        return $this->owns($user, $creditNote);
    }

    public function void(User $user, CreditNote $creditNote): bool
    {
        return $this->owns($user, $creditNote);
    }

    private function owns(User $user, CreditNote $creditNote): bool
    {
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        return ($user->isScopeOwner() || $user->isCaretaker()) && $creditNote->landlord_id === $landlordId;
    }
}
