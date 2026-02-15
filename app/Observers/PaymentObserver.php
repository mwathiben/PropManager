<?php

namespace App\Observers;

use App\Models\Payment;
use App\Services\FinanceCacheService;
use App\Services\PaymentLinkService;

class PaymentObserver
{
    public function __construct(
        protected PaymentLinkService $paymentLinkService
    ) {}

    public function created(Payment $payment): void
    {
        $this->invalidateAndWarmCache($payment);
        $this->revokePaymentLinks($payment);
    }

    public function updated(Payment $payment): void
    {
        $this->invalidateCache($payment);
    }

    public function deleted(Payment $payment): void
    {
        $this->invalidateCache($payment);
    }

    private function invalidateAndWarmCache(Payment $payment): void
    {
        if ($payment->landlord_id) {
            FinanceCacheService::invalidateAndWarm($payment->landlord_id);
        }
    }

    private function invalidateCache(Payment $payment): void
    {
        if ($payment->landlord_id) {
            FinanceCacheService::invalidateForLandlord($payment->landlord_id);
        }
    }

    private function revokePaymentLinks(Payment $payment): void
    {
        if ($payment->invoice_id) {
            $this->paymentLinkService->revokeForInvoice($payment->invoice_id);
        }
    }
}
