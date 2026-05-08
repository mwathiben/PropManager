<?php

namespace App\Observers;

use App\Models\Refund;
use App\Services\FinanceCacheService;

class RefundObserver
{
    public function created(Refund $refund): void
    {
        if ($refund->landlord_id) {
            FinanceCacheService::invalidateAndWarm($refund->landlord_id);
        }
    }

    public function updated(Refund $refund): void
    {
        $originalLandlordId = $refund->getOriginal('landlord_id');

        if ($originalLandlordId && $originalLandlordId !== $refund->landlord_id) {
            FinanceCacheService::invalidateForLandlord($originalLandlordId);
        }

        if ($refund->landlord_id) {
            FinanceCacheService::invalidateForLandlord($refund->landlord_id);
        }
    }

    public function deleted(Refund $refund): void
    {
        if ($refund->landlord_id) {
            FinanceCacheService::invalidateForLandlord($refund->landlord_id);
        }
    }
}
