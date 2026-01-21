<?php

namespace App\Observers;

use App\Models\LateFee;
use App\Services\FinanceCacheService;

class LateFeeObserver
{
    public function created(LateFee $lateFee): void
    {
        $this->invalidateCache($lateFee);
    }

    public function updated(LateFee $lateFee): void
    {
        $this->invalidateCache($lateFee);
    }

    public function deleted(LateFee $lateFee): void
    {
        $this->invalidateCache($lateFee);
    }

    private function invalidateCache(LateFee $lateFee): void
    {
        if ($lateFee->landlord_id) {
            FinanceCacheService::invalidateForLandlord($lateFee->landlord_id);
        }
    }
}
