<?php

namespace App\Observers;

use App\Models\Refund;
use App\Services\FinanceCacheService;

class RefundObserver
{
    public function created(Refund $refund): void
    {
        $this->invalidateCache($refund);
    }

    public function updated(Refund $refund): void
    {
        $this->invalidateCache($refund);
    }

    public function deleted(Refund $refund): void
    {
        $this->invalidateCache($refund);
    }

    private function invalidateCache(Refund $refund): void
    {
        if ($refund->landlord_id) {
            FinanceCacheService::invalidateForLandlord($refund->landlord_id);
        }
    }
}
