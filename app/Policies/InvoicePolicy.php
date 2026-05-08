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
        return $user->isLandlord() || $user->isCaretaker() || $user->isTenant();
    }

    /**
     * Determine whether the user can view the invoice.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->isLandlord()) {
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
        return $user->isLandlord() || $user->isCaretaker();
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

        return $user->isLandlord() && $invoice->landlord_id === $user->id;
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
     * Determine whether the user can pay the invoice (tenant only).
     */
    public function pay(User $user, Invoice $invoice): bool
    {
        if (! $user->isTenant()) {
            return false;
        }

        return $invoice->lease?->tenant_id === $user->id
            && in_array($invoice->status, [InvoiceStatus::Sent, InvoiceStatus::Partial, InvoiceStatus::Overdue]);
    }

    /**
     * Check if user can manage the invoice.
     */
    protected function canManage(User $user, Invoice $invoice): bool
    {
        if ($user->isLandlord()) {
            return $invoice->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $invoice->landlord_id === $user->landlord_id;
        }

        return false;
    }
}
