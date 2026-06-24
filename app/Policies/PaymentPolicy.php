<?php

namespace App\Policies;

use App\Models\Invoice;
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
        return $user->isScopeOwner() || $user->isCaretaker() || $user->isTenant();
    }

    /**
     * Determine whether the user can view the payment.
     */
    public function view(User $user, Payment $payment): bool
    {
        if ($user->isScopeOwner()) {
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
    public function create(User $user, ?Invoice $invoice = null): bool
    {
        if ($user->isScopeOwner()) {
            return $invoice === null || $invoice->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $invoice === null || $invoice->landlord_id === $user->landlord_id;
        }

        if ($user->isTenant()) {
            return $invoice !== null && $invoice->lease?->tenant_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can update the payment.
     */
    public function update(User $user, Payment $payment): bool
    {
        // Only scope owners (landlords/managers) can update payments
        return $user->isScopeOwner() && $payment->landlord_id === $user->id;
    }

    /**
     * Determine whether the user can delete the payment.
     */
    public function delete(User $user, Payment $payment): bool
    {
        return $user->isScopeOwner() && $payment->landlord_id === $user->id;
    }

    /**
     * Determine whether the user can void the payment.
     */
    public function void(User $user, Payment $payment): bool
    {
        return $user->isScopeOwner() && $payment->landlord_id === $user->id;
    }

    /**
     * Determine whether the user can download receipt.
     */
    public function downloadReceipt(User $user, Payment $payment): bool
    {
        return $this->view($user, $payment);
    }
}
