<?php

namespace App\Policies;

use App\Models\User;

/**
 * PRIV-12: centralize the same-landlord + role-gate auth check that
 * was previously duplicated inline across 6 TenantController methods
 * (modalData, show, ledger, ledgerPdf, ledgerEmail, outstandingInvoices,
 * refundablePayments). The User model now resolves to this policy for
 * tenant-targeted authorization; non-tenant users (caretakers,
 * landlords with role checks) are still handled by their own gates.
 */
class TenantPolicy
{
    /**
     * Super admins bypass every method.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Common predicate: $actor manages the landlord that owns $tenant.
     * Landlords own the landlord_id == own id; caretakers belong to a
     * landlord and act on their behalf via $actor->landlord_id.
     */
    private function actorManagesTenant(User $actor, User $tenant): bool
    {
        if (! $actor->isLandlord() && ! $actor->isCaretaker()) {
            return false;
        }

        $landlordId = $actor->isCaretaker() ? $actor->landlord_id : $actor->id;

        return (int) $tenant->landlord_id === (int) $landlordId;
    }

    /**
     * Used by show/modalData. Anyone managing the tenant's landlord
     * can read tenant detail.
     */
    public function view(User $actor, User $tenant): bool
    {
        return $this->actorManagesTenant($actor, $tenant);
    }

    /**
     * Used by ledger, ledgerPdf, ledgerEmail, outstandingInvoices,
     * refundablePayments. Same predicate as view today — the ledger
     * is sensitive enough that we keep it on its own ability so a
     * future tightening (e.g. require ledger_access feature flag)
     * can land without sweeping all six methods.
     */
    public function viewLedger(User $actor, User $tenant): bool
    {
        return $this->actorManagesTenant($actor, $tenant);
    }
}
