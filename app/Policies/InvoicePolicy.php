<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
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
     * Determine whether the user can view any invoices.
     */
    public function viewAny(User $user): bool
    {
        return $user->isScopeOwner() || $user->isCaretaker() || $user->isTenant();
    }

    /**
     * Determine whether the user can view the invoice.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->isScopeOwner()) {
            return $invoice->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $invoice->landlord_id === $user->landlord_id;
        }

        if ($user->isTenant()) {
            return $invoice->lease?->tenant_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create invoices.
     */
    public function create(User $user): bool
    {
        return $user->isScopeOwner() || $user->isCaretaker();
    }

    /**
     * Determine whether the user can update the invoice.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return $this->canManage($user, $invoice);
    }

    /**
     * Determine whether the user can delete the invoice.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        // Only draft invoices can be deleted
        if ($invoice->status !== InvoiceStatus::Draft) {
            return false;
        }

        return $user->isScopeOwner() && $invoice->landlord_id === $user->id;
    }

    /**
     * Determine whether the user can record a payment.
     */
    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $this->canManage($user, $invoice);
    }

    /**
     * Determine whether the user can send the invoice.
     */
    public function send(User $user, Invoice $invoice): bool
    {
        return $this->canManage($user, $invoice) && $invoice->status === InvoiceStatus::Draft;
    }

    /**
     * Determine whether the user can pay the invoice — the lease's tenant, or
     * (Phase-99) the water connection's client for a water-client invoice.
     */
    public function pay(User $user, Invoice $invoice): bool
    {
        $payable = in_array($invoice->status, [InvoiceStatus::Sent, InvoiceStatus::Partial, InvoiceStatus::Overdue], true);

        if ($user->isTenant()) {
            return $payable && $invoice->lease?->tenant_id === $user->id;
        }

        if ($user->isWaterClient()) {
            return $payable
                && $invoice->isWaterClientInvoice()
                && $invoice->waterConnection?->user_id === $user->id;
        }

        return false;
    }

    /**
     * Phase-19 POLICY-1: super-admin only via before(); explicit deny here
     * so the destructive force-delete path is gated, not framework-defaulted.
     */
    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return false;
    }

    /**
     * Phase-19 POLICY-1: restoring a soft-deleted invoice mirrors the
     * landlord-ownership check from delete() but without the draft-only
     * status guard — restoring undoes a destructive op regardless of the
     * pre-delete status (a Sent invoice that was soft-deleted by mistake
     * should be restorable to its prior state).
     */
    public function restore(User $user, Invoice $invoice): bool
    {
        return $user->isScopeOwner() && $invoice->landlord_id === $user->id;
    }

    /**
     * Check if user can manage the invoice.
     */
    protected function canManage(User $user, Invoice $invoice): bool
    {
        if ($user->isScopeOwner()) {
            return $invoice->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $invoice->landlord_id === $user->landlord_id;
        }

        return false;
    }
}
