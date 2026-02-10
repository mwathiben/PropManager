<?php

declare(strict_types=1);

namespace App\Services\Payment;

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
        $verificationId = $metadata['verification_id'];
        $verification = TenantPaymentVerification::find($verificationId);

        if (! $verification) {
            return InitialPaymentResult::notFound();
        }

        if ($verification->isVerified()) {
            return InitialPaymentResult::alreadyVerified();
        }

        $reference = $data['reference'] ?? null;
        if (! $reference) {
            return InitialPaymentResult::error('Invalid payment data: missing reference');
        }

        try {
            $result = DB::transaction(function () use ($data, $reference, $verification) {
                $existingPayment = Payment::where('paystack_reference', $reference)
                    ->lockForUpdate()
                    ->first();

                if ($existingPayment) {
                    return InitialPaymentResult::duplicate();
                }

                $amount = $data['amount'] / 100;

                $payment = Payment::create([
                    'landlord_id' => $verification->landlord_id,
                    'lease_id' => $verification->lease_id,
                    'amount' => $amount,
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
                        Mail::to($tenant)->queue(new PaymentVerificationApproved($verification));
                    }
                }

                return InitialPaymentResult::success($payment, $verification, $amount, $isVerified);
            });

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
