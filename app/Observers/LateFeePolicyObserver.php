<?php

namespace App\Observers;

use App\Models\LateFeePolicy;
use App\Services\FinanceCacheService;

class LateFeePolicyObserver
{
    public function created(LateFeePolicy $policy): void
    {
        $this->invalidateCache($policy);
    }

    public function updated(LateFeePolicy $policy): void
    {
        $this->invalidateCache($policy);
    }

    public function deleted(LateFeePolicy $policy): void
    {
        $this->invalidateCache($policy);
    }

    private function invalidateCache(LateFeePolicy $policy): void
    {
        if ($policy->landlord_id) {
            FinanceCacheService::invalidateForLandlord($policy->landlord_id);
        }
    }
}
