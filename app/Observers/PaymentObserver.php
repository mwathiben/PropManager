<?php

namespace App\Observers;

use App\Models\Payment;
use App\Services\FinanceCacheService;

class PaymentObserver
{
    public function created(Payment $payment): void
    {
        $this->invalidateCache($payment);
    }

    public function updated(Payment $payment): void
    {
        $this->invalidateCache($payment);
    }

    public function deleted(Payment $payment): void
    {
        $this->invalidateCache($payment);
    }

    private function invalidateCache(Payment $payment): void
    {
        if ($payment->landlord_id) {
            FinanceCacheService::invalidateForLandlord($payment->landlord_id);
        }
    }
}
