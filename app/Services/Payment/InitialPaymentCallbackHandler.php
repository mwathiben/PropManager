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
            $result = DB::transaction(function () use ($data, $reference, $verificationId, &$tenantToNotify, &$verificationForMail) {
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

                $currency = Currency::tryFrom($data['currency'] ?? '') ?? Currency::default();
                $amount = $currency->fromMinorUnits($data['amount']);

                $payment = Payment::create([
                    'landlord_id' => $verification->landlord_id,
                    'lease_id' => $verification->lease_id,
                    'amount' => $amount,
                    'currency' => $currency->value,
                    'payment_method' => 'paystack',
                    'payment_date' => now(),
                    'reference' => $reference,
                    'paystack_reference' => $reference,
                    'notes' => 'Initial payment verification - '.($data['channel'] ?? 'online'),
                ]);

                $this->receiptService->createReceipt($payment);

                $verification->recordPayment($amount);
                $verification->refresh();

                $isVerified = false;

                if ($verification->isFullyPaid()) {
                    $verification->approve(null);
                    $isVerified = true;

                    $verification->load('lease.tenant');
                    $tenant = $verification->lease?->tenant;

                    if ($tenant) {
                        $tenantToNotify = $tenant;
                        $verificationForMail = $verification;
                    }
                }

                return InitialPaymentResult::success($payment, $verification, $amount, $isVerified);
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
}
