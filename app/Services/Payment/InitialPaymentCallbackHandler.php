<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\Currency;
use App\Mail\PaymentVerificationApproved;
use App\Models\Payment;
use App\Models\TenantPaymentVerification;
use App\Services\ReceiptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class InitialPaymentCallbackHandler
{
    public function __construct(
        protected ReceiptService $receiptService
    ) {}

    public function process(array $data, array $metadata): InitialPaymentResult
    {
        $verificationId = $metadata['verification_id'] ?? null;

        if (! $verificationId) {
            Log::warning('Initial payment callback missing verification_id', ['metadata' => $metadata]);

            return InitialPaymentResult::error('Invalid metadata: missing verification_id');
        }

        $reference = $data['reference'] ?? null;
        if (! $reference) {
            return InitialPaymentResult::error('Invalid payment data: missing reference');
        }

        /** @var \App\Models\User|null $tenantToNotify */
        $tenantToNotify = null;
        /** @var TenantPaymentVerification|null $verificationForMail */
        $verificationForMail = null;

        try {
            $ctx = ['data' => $data, 'reference' => $reference, 'verificationId' => $verificationId];
            $result = DB::transaction(function () use ($ctx, &$tenantToNotify, &$verificationForMail) {
                return $this->runTransaction($ctx, $tenantToNotify, $verificationForMail);
            });

            if ($tenantToNotify && $verificationForMail) {
                Mail::to($tenantToNotify)->queue(new PaymentVerificationApproved($verificationForMail));
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Initial payment recording failed', [
                'reference' => $data['reference'] ?? 'unknown',
                'verification_id' => $verificationId,
                'error' => $e->getMessage(),
            ]);

            return InitialPaymentResult::error('Failed to record payment. Please contact support.');
        }
    }

    /**
     * @param  array{data: array, reference: string, verificationId: int|string}  $ctx
     */
    private function runTransaction(
        array $ctx,
        ?object &$tenantToNotify,
        ?TenantPaymentVerification &$verificationForMail
    ): InitialPaymentResult {
        ['data' => $data, 'reference' => $reference, 'verificationId' => $verificationId] = $ctx;

        $verification = TenantPaymentVerification::lockForUpdate()->find($verificationId);

        if (! $verification) {
            return InitialPaymentResult::notFound();
        }

        if ($verification->isVerified()) {
            return InitialPaymentResult::alreadyVerified();
        }

        $existingPayment = Payment::where('paystack_reference', $reference)
            ->lockForUpdate()
            ->first();

        if ($existingPayment) {
            return InitialPaymentResult::duplicate();
        }

        $amount = $this->resolveAmount($data, $reference);
        $currency = Currency::tryFrom($data['currency'] ?? '') ?? Currency::default();

        $payment = $this->createPayment($verification, [
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $currency,
            'channel' => $data['channel'] ?? 'online',
        ]);

        $this->receiptService->createReceipt($payment);

        $verification->recordPayment($amount);
        $verification->refresh();

        $isVerified = $this->approveIfFullyPaid($verification, $tenantToNotify, $verificationForMail);

        return InitialPaymentResult::success($payment, $verification, $amount, $isVerified);
    }

    private function resolveAmount(array $data, string $reference): float|int
    {
        $currency = Currency::tryFrom($data['currency'] ?? '') ?? Currency::default();
        $rawAmount = $data['amount'] ?? 0;

        if (! is_numeric($rawAmount)) {
            Log::warning('Initial payment callback received non-numeric amount', [
                'amount' => $rawAmount,
                'reference' => $reference,
            ]);
            $rawAmount = 0;
        }

        return $currency->fromMinorUnits($rawAmount);
    }

    /**
     * @param  array{reference: string, amount: float|int, currency: Currency, channel: string}  $attrs
     */
    private function createPayment(TenantPaymentVerification $verification, array $attrs): Payment
    {
        return Payment::create([
            'landlord_id' => $verification->landlord_id,
            'lease_id' => $verification->lease_id,
            'amount' => $attrs['amount'],
            'currency' => $attrs['currency']->value,
            'payment_method' => 'paystack',
            'payment_date' => now(),
            'reference' => $attrs['reference'],
            'paystack_reference' => $attrs['reference'],
            'notes' => 'Initial payment verification - '.$attrs['channel'],
        ]);
    }

    private function approveIfFullyPaid(
        TenantPaymentVerification $verification,
        ?object &$tenantToNotify,
        ?TenantPaymentVerification &$verificationForMail
    ): bool {
        if (! $verification->isFullyPaid()) {
            return false;
        }

        $verification->approve(null);
        $verification->load('lease.tenant');
        $tenant = $verification->lease?->tenant;

        if ($tenant) {
            $tenantToNotify = $tenant;
            $verificationForMail = $verification;
        }

        return true;
    }
}
