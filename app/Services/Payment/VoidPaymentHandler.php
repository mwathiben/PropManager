<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\InvoiceStatus;
use App\Exceptions\Payment\PaymentException;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VoidPaymentHandler
{
    public function void(Payment $payment, string $reason): VoidPaymentResult
    {
        return DB::transaction(function () use ($payment, $reason) {
            $payment = Payment::where('id', $payment->id)->lockForUpdate()->first();

            if ($payment->is_voided) {
                throw new PaymentException('Payment is already voided.', 'PAYMENT_ALREADY_VOIDED', [
                    'payment_id' => $payment->id,
                ]);
            }

            $payment->update([
                'is_voided' => true,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            // AUDIT-3: capture the void as a status_changed audit event so
            // the void reason and actor are recoverable from AuditLog alone,
            // not just the void_reason column.
            $payment->logStatusChange('completed', 'voided', $reason);

            [$invoice, $previousStatus, $newStatus] = $this->recalculateInvoice($payment, $reason);

            Log::info('Payment voided', [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'invoice_id' => $payment->invoice_id,
            ]);

            return new VoidPaymentResult($payment, $invoice, $previousStatus, $newStatus);
        });
    }

    private function recalculateInvoice(Payment $payment, string $reason): array
    {
        if (! $payment->invoice_id) {
            return [null, null, null];
        }

        $invoice = Invoice::where('id', $payment->invoice_id)->lockForUpdate()->first();

        if (! $invoice) {
            return [null, null, null];
        }

        $previousStatus = $invoice->status;
        $newAmountPaid = max(0, $invoice->amount_paid - $payment->amount);
        $newStatus = $this->determineInvoiceStatus($invoice, $newAmountPaid);

        $invoice->update([
            'amount_paid' => $newAmountPaid,
            'status' => $newStatus,
        ]);

        // AUDIT-3: emit a status_changed event when the void recalculation
        // moves the invoice to a new state (e.g. Paid → Partial).
        if ($previousStatus !== $newStatus) {
            $invoice->logStatusChange(
                $previousStatus->value,
                $newStatus->value,
                "Recalculated after void of payment #{$payment->id}: {$reason}",
            );
        }

        return [$invoice, $previousStatus, $newStatus];
    }

    private function determineInvoiceStatus(Invoice $invoice, float $newAmountPaid): InvoiceStatus
    {
        if ($invoice->status === InvoiceStatus::Voided) {
            return InvoiceStatus::Voided;
        }

        if ($newAmountPaid <= 0) {
            return InvoiceStatus::Sent;
        }

        if ($newAmountPaid >= $invoice->total_due) {
            return InvoiceStatus::Paid;
        }

        return InvoiceStatus::Partial;
    }
}
