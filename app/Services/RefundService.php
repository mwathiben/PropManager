<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PlatformFee;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RefundService
{
    public function __construct(
        protected PaystackService $paystackService,
        protected MpesaService $mpesaService,
        protected BillingModelService $billingService
    ) {}

    public function initiateRefund(Payment $payment, float $amount, string $reason, int $userId): Refund
    {
        $this->validateRefundEligibility($payment, $amount);

        return DB::transaction(function () use ($payment, $amount, $reason, $userId) {
            return Refund::create([
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'landlord_id' => $payment->landlord_id,
                'amount' => $amount,
                'status' => 'pending',
                'reason' => $reason,
                'payment_method' => $payment->payment_method,
                'initiated_by' => $userId,
            ]);
        });
    }

    public function processRefund(Refund $refund): bool
    {
        $refund->markAsProcessing();

        try {
            $result = match ($refund->payment_method) {
                'paystack' => $this->processPaystackRefund($refund),
                'mobile_money' => $this->processMpesaRefund($refund),
                'cash', 'bank_transfer' => $this->processManualRefund($refund),
                default => throw new \Exception("Unsupported payment method: {$refund->payment_method}"),
            };

            if ($result) {
                $this->handleSuccessfulRefund($refund);

                return true;
            }

            $refund->markAsFailed(['message' => 'Gateway returned failure response']);

            return false;
        } catch (\Exception $e) {
            Log::error('Refund processing failed', [
                'refund_id' => $refund->id,
                'error' => $e->getMessage(),
            ]);

            $refund->markAsFailed(['message' => $e->getMessage()]);

            return false;
        }
    }

    public function cancelRefund(Refund $refund): bool
    {
        if (! $refund->isPending()) {
            return false;
        }

        $refund->cancel();

        return true;
    }

    public function getRefundableAmount(Payment $payment): float
    {
        $existingRefunds = Refund::where('payment_id', $payment->id)
            ->whereIn('status', ['pending', 'approved', 'processing', 'completed'])
            ->sum('amount');

        return max(0, $payment->amount - $existingRefunds);
    }

    private function processPaystackRefund(Refund $refund): bool
    {
        $payment = $refund->payment;
        $result = $this->paystackService->refundTransaction(
            $payment->paystack_reference,
            $refund->amount
        );

        if ($result) {
            $refund->update([
                'paystack_refund_reference' => $result['id'] ?? $result['reference'] ?? null,
            ]);

            return true;
        }

        return false;
    }

    private function processMpesaRefund(Refund $refund): bool
    {
        $payment = $refund->payment;
        $tenant = $payment->lease->tenant;

        if (! $tenant->mobile_number) {
            throw new \Exception('Tenant has no mobile number for M-Pesa refund');
        }

        $result = $this->mpesaService->initiateB2C(
            $tenant->mobile_number,
            $refund->amount,
            "REFUND-{$refund->id}",
            "Refund for payment #{$payment->id}"
        );

        if ($result) {
            $refund->update([
                'mpesa_conversation_id' => $result['conversation_id'],
            ]);

            return true;
        }

        return false;
    }

    private function processManualRefund(Refund $refund): bool
    {
        $refund->update([
            'notes' => ($refund->notes ? $refund->notes."\n" : '')
                .'Manual refund - requires physical cash/bank transfer',
        ]);

        return true;
    }

    private function handleSuccessfulRefund(Refund $refund): void
    {
        DB::transaction(function () use ($refund) {
            $refund->markAsCompleted();

            $invoice = $refund->invoice;
            $newAmountPaid = max(0, $invoice->amount_paid - $refund->amount);

            $newStatus = 'sent';
            if ($newAmountPaid >= $invoice->total_due) {
                $newStatus = 'paid';
            } elseif ($newAmountPaid > 0) {
                $newStatus = 'partial';
            }

            $invoice->update([
                'amount_paid' => $newAmountPaid,
                'status' => $newStatus,
            ]);

            $platformFee = PlatformFee::where('payment_id', $refund->payment_id)->first();
            if ($platformFee) {
                $platformFee->markRefunded();
            }
        });
    }

    private function validateRefundEligibility(Payment $payment, float $amount): void
    {
        $maxRefundable = $this->getRefundableAmount($payment);

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Refund amount must be greater than zero');
        }

        if ($amount > $maxRefundable) {
            throw new \InvalidArgumentException(
                'Refund amount exceeds available balance. Maximum refundable: KES '.number_format($maxRefundable, 2)
            );
        }
    }
}
