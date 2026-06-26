<?php

namespace App\Http\Requests\Payment;

use App\Enums\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;

class InitializePaystackRequest extends FormRequest
{
    // VALID-6: route-model ownership check. The caller must be either the
    // landlord/caretaker who owns the invoice, or the tenant on the invoice's
    // lease — anyone else paying on someone else's invoice is an IDOR.
    public function authorize(): bool
    {
        $user = $this->user();
        $invoice = $this->route('invoice');

        // Only an open invoice can be charged — never a draft/voided/paid one.
        // (rules() caps the amount at the remaining balance, but a voided invoice
        // with no payment still has a positive balance, so it needs this gate.)
        if (! $user || ! $invoice || ! $this->invoiceIsChargeable($invoice)) {
            return false;
        }

        return $this->userOwnsInvoice($user, $invoice);
    }

    private function userOwnsInvoice(mixed $user, mixed $invoice): bool
    {
        return match (true) {
            // Phase-99: a water client pays their own water-connection invoice.
            $user->isScopeOwner(), $user->isCaretaker() => $this->scopeOwnerOrCaretakerOwnsInvoice($user, $invoice),
            $user->isTenant() => $this->tenantOwnsInvoice($user, $invoice),
            $user->isWaterClient() => $this->waterClientOwnsInvoice($user, $invoice),
            default => false,
        };
    }

    private function invoiceIsChargeable(mixed $invoice): bool
    {
        return in_array($invoice->status, [InvoiceStatus::Sent, InvoiceStatus::Partial, InvoiceStatus::Overdue], true);
    }

    private function scopeOwnerOrCaretakerOwnsInvoice(mixed $user, mixed $invoice): bool
    {
        $landlordId = $user->isCaretaker() ? (int) $user->landlord_id : (int) $user->id;

        return (int) $invoice->landlord_id === $landlordId;
    }

    private function tenantOwnsInvoice(mixed $user, mixed $invoice): bool
    {
        $lease = $invoice->lease;

        return $lease && (int) $lease->tenant_id === (int) $user->id;
    }

    private function waterClientOwnsInvoice(mixed $user, mixed $invoice): bool
    {
        return $invoice->isWaterClientInvoice()
            && (int) $invoice->waterConnection?->user_id === (int) $user->id;
    }

    public function rules(): array
    {
        $invoice = $this->route('invoice');
        $maxAmount = $invoice ? ($invoice->total_due - $invoice->amount_paid) : PHP_INT_MAX;

        return [
            'amount' => "required|numeric|min:1|max:{$maxAmount}",
        ];
    }

    public function messages(): array
    {
        return [
            'amount.max' => 'The payment amount cannot exceed the remaining balance.',
        ];
    }
}
