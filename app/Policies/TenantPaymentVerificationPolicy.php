<?php

namespace App\Policies;

use App\Models\TenantPaymentVerification;
use App\Models\User;

class TenantPaymentVerificationPolicy
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
        return $user->isLandlord() || $user->isCaretaker();
    }

    public function view(User $user, TenantPaymentVerification $verification): bool
    {
        return $this->ownsVerification($user, $verification);
    }

    public function approve(User $user, TenantPaymentVerification $verification): bool
    {
        return $this->ownsVerification($user, $verification)
            && ! $verification->isVerified();
    }

    public function reject(User $user, TenantPaymentVerification $verification): bool
    {
        return $this->ownsVerification($user, $verification)
            && ! $verification->isVerified();
    }

    /**
     * Phase-19 POLICY-2: tenants submit verifications via the dedicated
     * submission flow (PaymentVerificationController); landlords/caretakers
     * never create on a tenant's behalf. Explicit deny instead of relying
     * on the framework default for missing methods.
     */
    public function create(User $user, ?User $tenant = null): bool
    {
        return false;
    }

    /**
     * Phase-19 POLICY-2: verifications are immutable post-submission.
     * Approve/reject are the only mutating ops and have their own
     * dedicated abilities above.
     */
    public function update(User $user, TenantPaymentVerification $verification): bool
    {
        return false;
    }

    /**
     * Phase-19 POLICY-2: verifications carry compliance/audit weight
     * (Phase-13 DPA-3 lawful_basis). Delete is not exposed to any role.
     */
    public function delete(User $user, TenantPaymentVerification $verification): bool
    {
        return false;
    }

    protected function ownsVerification(User $user, TenantPaymentVerification $verification): bool
    {
        if ($user->isLandlord()) {
            return $verification->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $verification->landlord_id === $user->landlord_id;
        }

        return false;
    }
}
