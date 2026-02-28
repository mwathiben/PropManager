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
