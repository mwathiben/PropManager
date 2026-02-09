<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
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
     * Determine whether the user can view any payments.
     */
    public function viewAny(User $user): bool
    {
        return $user->isLandlord() || $user->isCaretaker() || $user->isTenant();
    }

    /**
     * Determine whether the user can view the payment.
     */
    public function view(User $user, Payment $payment): bool
    {
        if ($user->isLandlord()) {
            return $payment->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $payment->landlord_id === $user->landlord_id;
        }

        if ($user->isTenant()) {
            return $payment->lease?->tenant_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create payments.
     */
    public function create(User $user): bool
    {
        return $user->isLandlord() || $user->isCaretaker() || $user->isTenant();
    }

    /**
     * Determine whether the user can update the payment.
     */
    public function update(User $user, Payment $payment): bool
    {
        // Only landlords can update payments
        return $user->isLandlord() && $payment->landlord_id === $user->id;
    }

    /**
     * Determine whether the user can delete the payment.
     */
    public function delete(User $user, Payment $payment): bool
    {
        return $user->isLandlord() && $payment->landlord_id === $user->id;
    }

    /**
     * Determine whether the user can void the payment.
     */
    public function void(User $user, Payment $payment): bool
    {
        return $user->isLandlord() && $payment->landlord_id === $user->id;
    }

    /**
     * Determine whether the user can download receipt.
     */
    public function downloadReceipt(User $user, Payment $payment): bool
    {
        return $this->view($user, $payment);
    }
}
