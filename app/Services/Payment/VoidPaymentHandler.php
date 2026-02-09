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
        if ($payment->is_voided) {
            throw new PaymentException('Payment is already voided.', 'PAYMENT_ALREADY_VOIDED', [
                'payment_id' => $payment->id,
            ]);
        }

        return DB::transaction(function () use ($payment, $reason) {
            $payment->update([
                'is_voided' => true,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            [$invoice, $previousStatus, $newStatus] = $this->recalculateInvoice($payment);

            Log::info('Payment voided', [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'invoice_id' => $payment->invoice_id,
            ]);

            return new VoidPaymentResult($payment, $invoice, $previousStatus, $newStatus);
        });
    }

    private function recalculateInvoice(Payment $payment): array
    {
        if (! $payment->invoice_id) {
            return [null, null, null];
        }

        $invoice = Invoice::lockForUpdate()->find($payment->invoice_id);

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
