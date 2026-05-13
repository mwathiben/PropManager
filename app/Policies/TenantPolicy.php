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

    /**
     * Phase-19 POLICY-3: tenants are listable by landlord/caretaker. The
     * existing TenantController::index path already enforces this via
     * route middleware role:landlord,caretaker — POLICY-3 declares the
     * intent at the policy layer for symmetric coverage.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->isLandlord() || $actor->isCaretaker();
    }

    /**
     * Phase-19 POLICY-3: tenant creation is invitation-only. The
     * canonical creation flow is InvitationController (landlord
     * invites → tenant accepts → User row provisioned). Direct
     * tenant creation via $this->authorize('create', User::class)
     * is explicitly denied.
     */
    public function create(User $actor): bool
    {
        return false;
    }

    /**
     * Phase-19 POLICY-3: tenants edit their own profile via the
     * tenant self-service flow (ProfileController). Cross-tenant
     * landlord-side edits of a tenant's User row go through dedicated
     * abilities (e.g. TenantController::update calls authorize('update',
     * $tenant) — explicit gate decision below).
     */
    public function update(User $actor, User $tenant): bool
    {
        return $this->actorManagesTenant($actor, $tenant);
    }

    /**
     * Phase-19 POLICY-3: tenant deletion is the GDPR right-to-erasure
     * path via the deletion-request workflow (Phase-13). Direct delete
     * is denied; landlords mark tenants archived (User::is_archived)
     * for tenancy lifecycle, never delete.
     */
    public function delete(User $actor, User $tenant): bool
    {
        return false;
    }

    /**
     * Phase-19 POLICY-1: tenant force-delete is GDPR right-to-erasure
     * execution. Super-admin only via before(); landlord/caretaker can
     * REQUEST deletion (Phase-13 DPA) but cannot execute it directly.
     */
    public function forceDelete(User $actor, User $tenant): bool
    {
        return false;
    }

    /**
     * Phase-19 POLICY-1: restoring a soft-deleted tenant (e.g. during
     * the DPA grace window before hard-delete) is symmetric with
     * actorManagesTenant — the landlord managing the tenant can undo.
     */
    public function restore(User $actor, User $tenant): bool
    {
        return $this->actorManagesTenant($actor, $tenant);
    }
}
